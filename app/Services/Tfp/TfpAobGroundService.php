<?php

namespace App\Services\Tfp;

use App\Exceptions\SignerNotAuthorizedException;
use App\Exceptions\TfpAobGroundDuplicateException;
use App\Models\LocalUser;
use App\Models\Tfp\TfpAobGroundFacility;
use App\Models\Tfp\TfpAobGroundItem;
use App\Models\Tfp\TfpAobGroundRecord;
use App\Models\Tfp\TfpAobGroundTechnician;
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
 * TfpAobGroundService — orchestrates the TFP Performance Check AOB Lantai Ground
 * form on top of the rostering DB and the signature trait.
 *
 * Mirrors CnsdRecorderMeterService closely:
 *   - Personnel are resolved from atoms-rostering at create time
 *     (date + shift_type). Only Support employees are taken as technicians.
 *   - Supervisor TFP = getShiftSupervisorByDivision($shift, $date, 'Support').
 *   - Signatures are immutable, name-matched, and never delegated.
 *   - The service NEVER writes to the rostering DB.
 *   - Items and facilities are seeded from TfpAobGroundTemplate at create.
 */
class TfpAobGroundService
{
    public function __construct(
        protected LocalUserResolver $userResolver,
        protected RosteringIntegrationService $rosteringService,
    ) {}

    // ─── Read ──────────────────────────────────────────────────

    public function listRecords(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = TfpAobGroundRecord::query()
            ->with([
                'technicians:id,aob_ground_record_id,technician_id,technician_name,technician_signature,sort_order',
                'manager:id,name',
                'supervisor:id,name',
            ])
            ->withCount('technicians');

        if (!empty($filters['form_type'])) {
            $query->byFormType($filters['form_type']);
        } else {
            $query->byFormType('AOB-GROUND');
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

    public function findRecord(int $id): ?TfpAobGroundRecord
    {
        return TfpAobGroundRecord::query()
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

    public function findExistingRecord(string $formType, string $date, string $shiftType): ?TfpAobGroundRecord
    {
        return TfpAobGroundRecord::query()
            ->where('form_type', $formType)
            ->whereDate('date', $date)
            ->where('shift_type', $shiftType)
            ->first();
    }

    // ─── Create ────────────────────────────────────────────────

    /**
     * Create a new TFP AOB Ground record + auto-resolve personnel + seed items + facilities.
     *
     * @throws TfpAobGroundDuplicateException when a record already exists.
     * @throws RuntimeException when no TFP technicians are on duty.
     */
    public function createRecord(array $data, ?LocalUser $creator = null): TfpAobGroundRecord
    {
        $formType  = $data['form_type']  ?? 'AOB-GROUND';
        $date      = $data['date'];
        $shiftType = $data['shift_type'];
        $location  = $data['location']   ?? 'AOB LANTAI GROUND';

        // Reject duplicate
        $existing = $this->findExistingRecord($formType, $date, $shiftType);
        if ($existing) {
            throw new TfpAobGroundDuplicateException($existing);
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

            $record = TfpAobGroundRecord::create([
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
                TfpAobGroundTechnician::create([
                    'aob_ground_record_id' => $record->id,
                    'technician_id'        => $tech['local_id'],
                    'technician_name'      => $tech['name'],
                    'sort_order'           => $sort++,
                ]);
            }

            // Seed items from template
            $itemRows = TfpAobGroundTemplate::buildItemRows($record->id);
            if (!empty($itemRows)) {
                TfpAobGroundItem::insert($itemRows);
            }

            // Seed facilities from template
            $facilityRows = TfpAobGroundTemplate::buildFacilityRows($record->id);
            if (!empty($facilityRows)) {
                TfpAobGroundFacility::insert($facilityRows);
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

            $personnel    = $this->rosteringService->getShiftPersonnel($shiftType, $date);
            $supportOnly  = $personnel->filter(fn ($p) => $p->employee_type === 'Support')->values();

            foreach ($supportOnly as $person) {
                $local = $this->userResolver->ensureLocalUser((int) $person->user_id);
                $technicians[] = [
                    'local_id' => $local?->id,
                    'name'     => $person->name,
                    'user_id'  => (int) $person->user_id,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('TfpAobGroundService: roster lookup failed', [
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
     * Generate a sequential form number for TFP AOB Ground records.
     *
     * Format: TFP-AOBLTGND-{YYMMDD}-{SEQ}
     * Example: TFP-AOBLTGND-260519-001
     *
     * Rules:
     *   - Prefix: always "TFP-AOBLTGND"
     *   - Date:   YYMMDD (2-digit year + 2-digit month + 2-digit day)
     *   - SEQ:    3-digit zero-padded, reset per calendar date
     *   - Counter uses withTrashed() so soft-deleted rows still increment seq
     *
     * @param string $date  Y-m-d
     */
    public function generateFormNumber(string $date): string
    {
        $dateYymmdd = date('ymd', strtotime($date));
        $prefix = 'TFP-AOBLTGND-' . $dateYymmdd;

        $count = TfpAobGroundRecord::withTrashed()
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
     *   panel_cos_a03_input?:string|null,
     *   panel_cos_a03_output?:string|null,
     *   panel_ats_a12_input?:string|null,
     *   panel_ats_a12_output?:string|null,
     *   ups_tescom_a_input?:string|null,
     *   ups_tescom_a_output?:string|null,
     *   ups_tescom_b_input?:string|null,
     *   ups_tescom_b_output?:string|null,
     * }> $items
     */
    public function updateItems(TfpAobGroundRecord $record, array $items): TfpAobGroundRecord
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

                /** @var TfpAobGroundItem $item */
                $item = $existing->get($payload['id']);

                // Allowed value columns. We strip disabled columns per row so that
                // the frontend (or any client) cannot accidentally write to grey
                // cells. is_disabled_map is the source of truth.
                $allowed = [
                    'panel_cos_a03_input',
                    'panel_cos_a03_output',
                    'panel_ats_a12_input',
                    'panel_ats_a12_output',
                    'ups_tescom_a_input',
                    'ups_tescom_a_output',
                    'ups_tescom_b_input',
                    'ups_tescom_b_output',
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
            // The user's official paper form treats the time as "when this set of
            // readings was taken" — every Simpan Perubahan is a new fill.
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
    public function updateFacilities(TfpAobGroundRecord $record, array $facilities): TfpAobGroundRecord
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

                /** @var TfpAobGroundFacility $facility */
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
     * Sign the TFP AOB Ground record on behalf of a role.
     * Identical flow to CnsdRecorderMeterService::signRecord.
     */
    public function signRecord(
        TfpAobGroundRecord $record,
        string $role,
        string $base64Signature,
        LocalUser $signer,
        ?int $technicianRowId = null,
    ): TfpAobGroundRecord {
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
        TfpAobGroundRecord $record,
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

        $roleOk = match ($role) {
            'manager'    => $signer->isManager(),
            'supervisor' => $signer->isSupervisorTfp() || $signer->isSupervisor(),
            default      => false,
        };
        if (!$roleOk) {
            throw new SignerNotAuthorizedException(sprintf(
                'Hanya %s yang berhak menandatangani role ini.',
                $role === 'manager' ? 'Manager Teknik' : 'Supervisor TFP'
            ));
        }

        if (!WorkOrderService::namesMatch($expectedName, $signer->name)) {
            throw new SignerNotAuthorizedException(sprintf(
                'Tanda tangan hanya dapat dilakukan oleh %s. Tidak boleh diwakilkan.',
                $expectedName
            ));
        }

        // Use the trait — it enforces immutability + base64 PNG validation.
        $record->saveSignature($role, $base64, $signer->id);
    }

    private function signTechnicianRow(
        TfpAobGroundRecord $record,
        string $base64,
        LocalUser $signer,
        ?int $technicianRowId,
    ): void {
        if (!$signer->isTeknisi() && !$signer->isSupervisorTfp()) {
            throw new SignerNotAuthorizedException('Hanya teknisi TFP yang berhak menandatangani role ini.');
        }

        /** @var TfpAobGroundTechnician|null $row */
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
                ->first(fn (TfpAobGroundTechnician $t) => WorkOrderService::namesMatch($t->technician_name, $signer->name));
        }

        if (!$row) {
            throw new SignerNotAuthorizedException(
                'Anda bukan bagian dari teknisi TFP yang bertugas di shift ini, sehingga tidak dapat menandatangani.'
            );
        }

        if (!WorkOrderService::namesMatch($row->technician_name, $signer->name)) {
            throw new SignerNotAuthorizedException(sprintf(
                'Tanda tangan hanya dapat dilakukan oleh %s. Tidak boleh diwakilkan.',
                $row->technician_name
            ));
        }

        if (!empty($row->technician_signature)) {
            throw new RuntimeException('Tanda tangan teknisi sudah tersimpan dan tidak dapat diubah.');
        }

        $this->validateBase64PngSignature($base64);

        $row->technician_signature = $base64;
        $row->technician_signed_by = $signer->id;
        $row->technician_signed_at = now();
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

    public function deleteRecord(TfpAobGroundRecord $record): void
    {
        $record->delete();
    }
}
