<?php

namespace App\Services\Tfp;

use App\Exceptions\SignerNotAuthorizedException;
use App\Exceptions\TfpAobLt12DuplicateException;
use App\Models\LocalUser;
use App\Models\Tfp\TfpAobLt12Facility;
use App\Models\Tfp\TfpAobLt12Item;
use App\Models\Tfp\TfpAobLt12Record;
use App\Models\Tfp\TfpAobLt12Technician;
use App\Services\LocalUserResolver;
use App\Services\RosteringIntegrationService;
use App\Services\WorkOrderService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * TfpAobLt12Service — orchestrates the TFP Performance Check AOB Lantai 1 & 2
 * form on top of the rostering DB and the signature trait.
 *
 * Mirrors TfpAobGroundService closely:
 *   - Personnel are resolved from atoms-rostering at create time
 *     (date + shift_type). Only Support employees are taken as technicians.
 *   - Supervisor TFP = getShiftSupervisorByDivision($shift, $date, 'Support').
 *   - Signatures are immutable, name-matched, and never delegated.
 *   - The service NEVER writes to the rostering DB.
 *   - Items and facilities are seeded from TfpAobLt12Template at create.
 */
class TfpAobLt12Service
{
    public function __construct(
        protected LocalUserResolver $userResolver,
        protected RosteringIntegrationService $rosteringService,
    ) {}

    // ─── Read ──────────────────────────────────────────────────

    public function listRecords(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = TfpAobLt12Record::query()
            ->with([
                'technicians:id,aob_lt12_record_id,technician_id,technician_name,technician_signature,sort_order',
                'manager:id,name',
                'supervisor:id,name',
            ])
            ->withCount('technicians');

        if (!empty($filters['form_type'])) {
            $query->byFormType($filters['form_type']);
        } else {
            $query->byFormType('AOB-LT12');
        }

        if (!empty($filters['date'])) {
            $query->byDate($filters['date']);
        }

        if (!empty($filters['year'])) {
            $query->whereYear('date', (int) $filters['year']);
        }

        if (!empty($filters['shift_type'])) {
            $query->byShift($filters['shift_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $needle = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($needle) {
                $q->where('form_number', 'ILIKE', $needle)
                    ->orWhere('manager_name', 'ILIKE', $needle)
                    ->orWhere('supervisor_name', 'ILIKE', $needle);
            });
        }

        $sortBy  = $filters['sort_by']  ?? 'date';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowed = ['date', 'created_at', 'shift_type', 'status'];
        if (!in_array($sortBy, $allowed, true)) {
            $sortBy = 'date';
        }

        return $query
            ->orderBy($sortBy, $sortDir)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findRecord(int $id): ?TfpAobLt12Record
    {
        return TfpAobLt12Record::query()
            ->with([
                'technicians',
                'items',
                'facilities',
                'manager:id,name',
                'supervisor:id,name',
                'creator:id,name',
            ])
            ->find($id);
    }

    public function findExistingRecord(string $formType, string $date, string $shiftType): ?TfpAobLt12Record
    {
        return TfpAobLt12Record::query()
            ->where('form_type', $formType)
            ->whereDate('date', $date)
            ->where('shift_type', $shiftType)
            ->first();
    }

    // ─── Create ────────────────────────────────────────────────

    /**
     * Create a new TFP AOB Lantai 1 & 2 record + auto-resolve personnel + seed items + facilities.
     *
     * @throws TfpAobLt12DuplicateException when a record already exists.
     * @throws RuntimeException when no TFP technicians are on duty.
     */
    public function createRecord(array $data, ?LocalUser $creator = null): TfpAobLt12Record
    {
        $formType  = $data['form_type']  ?? 'AOB-LT12';
        $date      = $data['date'];
        $shiftType = $data['shift_type'];
        $location  = $data['location']   ?? 'AOB LANTAI 1 & 2';

        // Reject duplicate
        $existing = $this->findExistingRecord($formType, $date, $shiftType);
        if ($existing) {
            throw new TfpAobLt12DuplicateException($existing);
        }

        // Resolve roster personnel BEFORE wrapping in a transaction so that
        // a missing TFP technician fails fast without partial state.
        $rosterContext = $this->resolveRosterContext($shiftType, $date);

        if (empty($rosterContext['technicians'])) {
            throw new RuntimeException(
                'Tidak ada teknisi TFP yang bertugas pada tanggal '
                . $date . ' shift ' . $shiftType
                . '. Pastikan roster sudah dipublish dan terdapat personel Support untuk shift ini.'
            );
        }

        return DB::transaction(function () use (
            $formType, $date, $shiftType, $location, $creator, $rosterContext
        ) {
            $manager    = $rosterContext['manager'];
            $supervisor = $rosterContext['supervisor'];

            // Derive day_name (Indonesian) from date
            $carbonDate = Carbon::parse($date);
            $dayNames   = [
                'Sunday'    => 'Minggu',
                'Monday'    => 'Senin',
                'Tuesday'   => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday'  => 'Kamis',
                'Friday'    => 'Jumat',
                'Saturday'  => 'Sabtu',
            ];
            $dayName = $dayNames[$carbonDate->format('l')] ?? $carbonDate->format('l');

            $record = TfpAobLt12Record::create([
                'form_number'     => $this->generateFormNumber($date),
                'form_type'       => $formType,
                'date'            => $date,
                'day_name'        => $dayName,
                'time_filled'     => now()->format('H:i'),
                'shift_type'      => $shiftType,
                'location'        => $location,
                'status'          => 'ongoing',
                'manager_id'      => $manager?->id,
                'manager_name'    => $manager?->name,
                'supervisor_id'   => $supervisor?->id,
                'supervisor_name' => $supervisor?->name,
                'created_by_id'   => $creator?->id,
                'created_by_name' => $creator?->name,
            ]);

            // Seed technicians
            $sort = 0;
            foreach ($rosterContext['technicians'] as $tech) {
                TfpAobLt12Technician::create([
                    'aob_lt12_record_id' => $record->id,
                    'technician_id'      => $tech['local_id'],
                    'technician_name'    => $tech['name'],
                    'sort_order'         => $sort++,
                ]);
            }

            // Seed items from template
            $itemRows = TfpAobLt12Template::buildItemRows($record->id);
            if (!empty($itemRows)) {
                TfpAobLt12Item::insert($itemRows);
            }

            // Seed facilities from template
            $facilityRows = TfpAobLt12Template::buildFacilityRows($record->id);
            if (!empty($facilityRows)) {
                TfpAobLt12Facility::insert($facilityRows);
            }

            $record->refresh();
            return $record->load([
                'technicians',
                'items',
                'facilities',
                'manager:id,name',
                'supervisor:id,name',
            ]);
        });
    }

    /**
     * Resolve roster personnel for a given shift+date.
     *
     * Only employees with employee_type = 'Support' are accepted as technicians.
     * Supervisor TFP = getShiftSupervisorByDivision($shift, $date, 'Support').
     */
    private function resolveRosterContext(string $shiftType, string $date): array
    {
        $manager     = null;
        $supervisor  = null;
        $technicians = [];

        try {
            $rosterManager = $this->rosteringService->getShiftManager($shiftType, $date);
            if ($rosterManager) {
                $manager = $this->userResolver->ensureLocalUser((int) $rosterManager->user_id);
            }

            $rosterSupervisor = $this->rosteringService->getShiftSupervisorByDivision($shiftType, $date, 'Support');
            if ($rosterSupervisor) {
                $supervisor = $this->userResolver->ensureLocalUser((int) $rosterSupervisor->user_id);
            }

            $personnel   = $this->rosteringService->getShiftPersonnel($shiftType, $date);
            $supportOnly = $personnel->filter(fn ($p) => $p->employee_type === 'Support')->values();

            foreach ($supportOnly as $person) {
                $local = $this->userResolver->ensureLocalUser((int) $person->user_id);
                $technicians[] = [
                    'local_id' => $local?->id,
                    'name'     => $person->name,
                    'user_id'  => (int) $person->user_id,
                ];
            }

            // Exclude supervisor and manager from the technician list.
            $technicians = \App\Services\WorkOrderService::excludeSignerRoles(
                $technicians,
                $rosterSupervisor ? (int) $rosterSupervisor->user_id : null,
                $supervisor?->name,
                $rosterManager ? (int) $rosterManager->user_id : null,
                $manager?->name,
            );
        } catch (\Throwable $e) {
            Log::warning('TfpAobLt12Service: roster lookup failed', [
                'shift_type' => $shiftType,
                'date'       => $date,
                'error'      => $e->getMessage(),
            ]);
        }

        return [
            'manager'     => $manager,
            'supervisor'  => $supervisor,
            'technicians' => $technicians,
        ];
    }

    /**
     * Generate a sequential form number for TFP AOB Lantai 1 & 2 records.
     *
     * Format: TFP-AOBLT12-{YYMMDD}-{SEQ}
     * Example: TFP-AOBLT12-260519-001
     *
     * Rules:
     *   - Prefix: always "TFP-AOBLT12"
     *   - Date:   YYMMDD (2-digit year + 2-digit month + 2-digit day)
     *   - SEQ:    3-digit zero-padded, reset per calendar date
     *   - Counter uses withTrashed() so soft-deleted rows still increment seq
     *
     * @param string $date  Y-m-d
     */
    public function generateFormNumber(string $date): string
    {
        $dateYymmdd = date('ymd', strtotime($date));
        $prefix = 'TFP-AOBLT12-' . $dateYymmdd;

        $count = TfpAobLt12Record::withTrashed()
            ->where('form_number', 'LIKE', $prefix . '%')
            ->count();

        return $prefix . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    // ─── Update ────────────────────────────────────────────────

    /**
     * Update item values on an existing record.
     * Personnel, signatures, dates, and form numbers are NOT touched here.
     *
     * @param array<int, array{
     *   id:int,
     *   panel_a05_app_room?:string|null,
     *   panel_a06_app_room?:string|null,
     *   panel_a07_app_room?:string|null,
     *   panel_a08_gudang_lt1?:string|null,
     *   panel_a22_gudang_lt1?:string|null,
     *   panel_a09_amsc_room?:string|null,
     * }> $items
     */
    public function updateItems(TfpAobLt12Record $record, array $items): TfpAobLt12Record
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');
        }

        return DB::transaction(function () use ($record, $items) {
            $existing = $record->items()->get()->keyBy('id');

            foreach ($items as $payload) {
                if (empty($payload['id']) || !$existing->has($payload['id'])) {
                    continue;
                }

                /** @var TfpAobLt12Item $item */
                $item = $existing->get($payload['id']);

                // Allowed value columns. We strip disabled columns per row so that
                // the frontend (or any client) cannot accidentally write to grey
                // cells. is_disabled_map is the source of truth.
                $allowed = [
                    'panel_a05_app_room',
                    'panel_a06_app_room',
                    'panel_a07_app_room',
                    'panel_a08_gudang_lt1',
                    'panel_a22_gudang_lt1',
                    'panel_a09_amsc_room',
                ];

                $disabledMap = is_array($item->is_disabled_map) ? $item->is_disabled_map : [];
                $effective = array_diff(
                    $allowed,
                    array_keys(array_filter($disabledMap, static fn ($v) => $v === true))
                );

                $item->fill(array_intersect_key($payload, array_flip($effective)));
                $item->save();
            }

            // Refresh the "Jam Pelaksanaan" so it reflects the most recent fill time.
            $record->time_filled = now()->format('H:i');
            $record->save();

            return $record->fresh([
                'technicians',
                'items',
                'facilities',
                'manager:id,name',
                'supervisor:id,name',
            ]);
        });
    }

    /**
     * Update facility condition values on an existing record.
     *
     * @param array<int, array{
     *   id:int,
     *   kondisi?:string|null,
     *   keterangan?:string|null,
     * }> $facilities
     */
    public function updateFacilities(TfpAobLt12Record $record, array $facilities): TfpAobLt12Record
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');
        }

        return DB::transaction(function () use ($record, $facilities) {
            $existing = $record->facilities()->get()->keyBy('id');

            foreach ($facilities as $payload) {
                if (empty($payload['id']) || !$existing->has($payload['id'])) {
                    continue;
                }

                /** @var TfpAobLt12Facility $facility */
                $facility = $existing->get($payload['id']);

                $facility->fill(array_intersect_key($payload, array_flip([
                    'kondisi',
                    'keterangan',
                ])));
                $facility->save();
            }

            // Refresh time_filled to reflect when the user saved this snapshot.
            $record->time_filled = now()->format('H:i');
            $record->save();

            return $record->fresh([
                'technicians',
                'items',
                'facilities',
                'manager:id,name',
                'supervisor:id,name',
            ]);
        });
    }

    // ─── Sign ──────────────────────────────────────────────────

    /**
     * Sign the TFP AOB Lantai 1 & 2 record on behalf of a role.
     */
    public function signRecord(
        TfpAobLt12Record $record,
        string $role,
        string $base64Signature,
        LocalUser $signer,
        ?int $technicianRowId = null,
    ): TfpAobLt12Record {
        $role = strtolower(trim($role));

        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat ditandatangani lagi.');
        }

        return DB::transaction(function () use ($record, $role, $base64Signature, $signer, $technicianRowId) {
            if ($role === 'technician') {
                $this->signTechnicianRow($record, $base64Signature, $signer, $technicianRowId);
            } else {
                $this->signRecordRole($record, $role, $base64Signature, $signer);
            }

            $record->refresh();
            $newStatus = $record->isComplete()
                ? 'completed'
                : ($record->isShiftEnded() ? 'on_hold' : 'ongoing');

            if ($record->status !== $newStatus) {
                $record->status = $newStatus;
                $record->save();
            }

            return $record->fresh([
                'technicians',
                'items',
                'facilities',
                'manager:id,name',
                'supervisor:id,name',
            ]);
        });
    }

    private function signRecordRole(
        TfpAobLt12Record $record,
        string $role,
        string $base64,
        LocalUser $signer,
    ): void {
        $expectedName = match ($role) {
            'manager'    => $record->manager_name,
            'supervisor' => $record->supervisor_name,
            default      => null,
        };

        if (!$expectedName) {
            throw new SignerNotAuthorizedException(
                'Form ini tidak memiliki ' . ($role === 'manager' ? 'Manager Teknik' : 'Supervisor TFP')
                . ' yang ditugaskan, sehingga tanda tangan tidak diperlukan.'
            );
        }

        // Use centralized role-based delegation authorization
        $slotType = \App\Services\SignatureAuthorizationService::slotType($role);
        $targetId = match ($role) {
            'manager'    => $record->manager_id ? (int) $record->manager_id : null,
            'supervisor' => $record->supervisor_id ? (int) $record->supervisor_id : null,
            default      => null,
        };

        \App\Services\SignatureAuthorizationService::authorize($signer, $slotType, $targetId, $expectedName);

        // Use the trait — it enforces immutability + base64 PNG validation.
        $record->saveSignature($role, $base64, $signer->id);
    }

    private function signTechnicianRow(
        TfpAobLt12Record $record,
        string $base64,
        LocalUser $signer,
        ?int $technicianRowId,
    ): void {
        // Use role-based delegation: Manager/Supervisor/Technician can all sign technician slots
        \App\Services\SignatureAuthorizationService::authorize($signer, 'technician', null, null);

        /** @var TfpAobLt12Technician|null $row */
        $row = null;
        if ($technicianRowId) {
            $row = $record->technicians()->where('id', $technicianRowId)->first();
        }
        if (!$row && $signer->id) {
            $row = $record->technicians()->where('technician_id', $signer->id)->first();
        }
        if (!$row) {
            $row = $record->technicians()
                ->get()
                ->first(fn (TfpAobLt12Technician $t) => WorkOrderService::namesMatch($t->technician_name, $signer->name));
        }

        if (!$row) {
            // For delegation: pick first unsigned row
            $row = $record->technicians()->whereNull('technician_signature')->first();
        }

        if (!$row) {
            throw new SignerNotAuthorizedException(
                'Tidak ada slot teknisi yang tersedia untuk ditandatangani pada form ini.'
            );
        }

        if (!empty($row->technician_signature)) {
            throw new RuntimeException('Tanda tangan teknisi sudah tersimpan dan tidak dapat diubah.');
        }

        $this->validateBase64PngSignature($base64);

        $row->technician_signature = $base64;
        $row->technician_signed_by = $signer->id;
        $row->technician_signed_at = now();
        // Audit trail
        if (in_array('technician_signed_by_name', $row->getFillable(), true) || array_key_exists('technician_signed_by_name', $row->getAttributes())) {
            $row->technician_signed_by_name = $signer->name;
            $row->technician_signed_by_role = $signer->role;
        }
        $row->save();
    }

    private function validateBase64PngSignature(string $base64): void
    {
        $prefix = 'data:image/png;base64,';
        if (!str_starts_with($base64, $prefix)) {
            throw new InvalidArgumentException('Signature must be a base64 PNG data URL.');
        }
        $payload = substr($base64, strlen($prefix));
        if ($payload === '') {
            throw new InvalidArgumentException('Signature payload cannot be empty.');
        }
        $decoded = base64_decode($payload, true);
        if ($decoded === false || $decoded === '') {
            throw new InvalidArgumentException('Signature payload is not valid base64.');
        }
    }

    // ─── Delete ────────────────────────────────────────────────

    public function deleteRecord(TfpAobLt12Record $record): void
    {
        $record->delete();
    }
}
