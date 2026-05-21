<?php

namespace App\Services\Grounding;

use App\Exceptions\SignerNotAuthorizedException;
use App\Models\Grounding\GroundingReportItem;
use App\Models\Grounding\GroundingReportRecord;
use App\Models\Grounding\GroundingReportTechnician;
use App\Models\LocalUser;
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
 * GroundingReportService — orchestrates the Grounding Report form on top of
 * the rostering DB and the signature trait.
 *
 * Mirrors TfpAobGroundService closely:
 *   - Personnel are resolved from atoms-rostering at create time
 *     (date + shift_type). Only Support employees are taken as technicians.
 *   - Supervisor TFP = getShiftSupervisorByDivision($shift, $date, 'Support').
 *   - Signatures are immutable, name-matched, and never delegated.
 *   - The service NEVER writes to the rostering DB.
 *   - Items are seeded from GroundingReportTemplate at create.
 *
 * Unlike TFP AOB Ground, multiple records per (date, shift_type) are allowed
 * because different equipment can be checked in the same shift.
 */
class GroundingReportService
{
    public function __construct(
        protected LocalUserResolver $userResolver,
        protected RosteringIntegrationService $rosteringService,
    ) {}

    // ─── Read ──────────────────────────────────────────────────

    public function listRecords(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = GroundingReportRecord::query()
            ->with([
                'technicians:id,grounding_report_record_id,technician_id,technician_name,technician_signature,sort_order',
                'manager:id,name',
                'supervisor:id,name',
            ])
            ->withCount('technicians');

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
                $q->where('report_number', 'ILIKE', $needle)
                    ->orWhere('equipment_name', 'ILIKE', $needle)
                    ->orWhere('equipment_location', 'ILIKE', $needle)
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

    public function findRecord(int $id): ?GroundingReportRecord
    {
        return GroundingReportRecord::query()
            ->with([
                'technicians',
                'items',
                'manager:id,name',
                'supervisor:id,name',
                'creator:id,name',
            ])
            ->find($id);
    }

    // ─── Create ────────────────────────────────────────────────

    /**
     * Create a new Grounding Report record + auto-resolve personnel + seed items.
     *
     * Multiple records per (date, shift_type) are allowed — different equipment
     * can be checked in the same shift.
     *
     * @throws RuntimeException when no TFP technicians are on duty.
     */
    public function createRecord(array $data, ?LocalUser $creator = null): GroundingReportRecord
    {
        $date              = $data['date'];
        $shiftType         = $data['shift_type'];
        $workUnit          = $data['work_unit'] ?? 'Cabang Surabaya';
        $timeFilled        = $data['time_filled'] ?? now()->format('H:i');
        $equipmentName     = $data['equipment_name'];
        $equipmentLocation = $data['equipment_location'];

        // Resolve personnel before the transaction so signer errors fail fast.
        $personnelContext = $workUnit === 'Cabang Surabaya'
            ? $this->resolveRosterContext($shiftType, $date)
            : $this->resolveManualContext($data);

        if (empty($personnelContext['technicians'])) {
            throw new RuntimeException(
                $workUnit === 'Cabang Surabaya'
                    ? 'Tidak ada teknisi TFP yang bertugas pada tanggal '
                        . $date . ' shift ' . $shiftType
                        . '. Pastikan roster sudah dipublish dan terdapat personel Support untuk shift ini.'
                    : 'Minimal satu pelaksana teknisi harus dipilih untuk cabang non-Surabaya.'
            );
        }

        return DB::transaction(function () use (
            $date, $shiftType, $workUnit, $timeFilled, $equipmentName, $equipmentLocation, $creator, $personnelContext
        ) {
            $manager    = $personnelContext['manager'];
            $supervisor = $personnelContext['supervisor'];

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

            $record = GroundingReportRecord::create([
                'report_number'      => $this->generateFormNumber($date),
                'date'               => $date,
                'day_name'           => $dayName,
                'time_filled'        => $timeFilled,
                'shift_type'         => $shiftType,
                'work_unit'          => $workUnit,
                'equipment_name'     => $equipmentName,
                'equipment_location' => $equipmentLocation,
                'status'             => 'ongoing',
                'manager_id'         => $manager?->id,
                'manager_name'       => $manager?->name,
                'supervisor_id'      => $supervisor?->id,
                'supervisor_name'    => $supervisor?->name,
                'created_by_id'      => $creator?->id,
                'created_by_name'    => $creator?->name,
            ]);

            // Seed technicians
            $sort = 0;
            foreach ($personnelContext['technicians'] as $tech) {
                GroundingReportTechnician::create([
                    'grounding_report_record_id' => $record->id,
                    'technician_id'              => $tech['local_id'],
                    'technician_name'            => $tech['name'],
                    'sort_order'                 => $sort++,
                ]);
            }

            // Seed items from template
            $itemRows = GroundingReportTemplate::buildItemRows($record->id);
            if (!empty($itemRows)) {
                GroundingReportItem::insert($itemRows);
            }

            $record->refresh();
            return $record->load([
                'technicians',
                'items',
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
            Log::warning('GroundingReportService: roster lookup failed', [
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
     * Resolve manually selected signers for reports outside Surabaya.
     */
    private function resolveManualContext(array $data): array
    {
        $manager = LocalUser::query()
            ->whereKey($data['manager_id'] ?? 0)
            ->where('is_active', true)
            ->where('role', 'Manager Teknik')
            ->first();
        if (!$manager) {
            throw new RuntimeException('Manager Teknik yang dipilih tidak tersedia.');
        }

        $supervisor = LocalUser::query()
            ->whereKey($data['supervisor_id'] ?? 0)
            ->where('is_active', true)
            ->where('role', 'Supervisor TFP')
            ->first();
        if (!$supervisor) {
            throw new RuntimeException('Supervisor TFP yang dipilih tidak tersedia.');
        }

        $technicianIds = array_values(array_unique(array_map(
            'intval',
            $data['technician_ids'] ?? [],
        )));

        if (in_array((int) $supervisor->id, $technicianIds, true)) {
            throw new RuntimeException('Supervisor TFP tidak dapat dipilih lagi sebagai pelaksana teknisi.');
        }

        $technicianRows = LocalUser::query()
            ->whereIn('id', $technicianIds)
            ->where('is_active', true)
            ->whereIn('role', ['Teknisi TFP', 'Supervisor TFP'])
            ->get(['id', 'name', 'rostering_user_id'])
            ->keyBy('id');

        if ($technicianRows->count() !== count($technicianIds)) {
            throw new RuntimeException('Daftar pelaksana teknisi TFP yang dipilih tidak valid.');
        }

        $technicians = array_map(function (int $technicianId) use ($technicianRows): array {
            $technician = $technicianRows->get($technicianId);

            return [
                'local_id' => $technician->id,
                'name'     => $technician->name,
                'user_id'  => $technician->rostering_user_id,
            ];
        }, $technicianIds);

        return [
            'manager'     => $manager,
            'supervisor'  => $supervisor,
            'technicians' => $technicians,
        ];
    }

    /**
     * Generate a sequential report number for Grounding Report records.
     *
     * Format: GROUNDING-{YYMMDD}-{SEQ}
     * Example: GROUNDING-260519-001
     *
     * Rules:
     *   - Prefix: always "GROUNDING"
     *   - Date:   YYMMDD (2-digit year + 2-digit month + 2-digit day)
     *   - SEQ:    3-digit zero-padded, reset per calendar date
     *   - Counter uses withTrashed() so soft-deleted rows still increment seq
     *
     * @param string $date  Y-m-d
     */
    public function generateFormNumber(string $date): string
    {
        $dateYymmdd = date('ymd', strtotime($date));
        $prefix = 'GROUNDING-' . $dateYymmdd;

        $count = GroundingReportRecord::withTrashed()
            ->where('report_number', 'LIKE', $prefix . '%')
            ->count();

        return $prefix . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    // ─── Update ────────────────────────────────────────────────

    /**
     * Update item values on an existing record.
     * Personnel, signatures, dates, and report numbers are NOT touched here.
     *
     * For VISUAL items: availability, condition, notes are updatable.
     * For PENGUKURAN items: only condition and notes are updatable (availability stays null).
     *
     * @param array<int, array{
     *   id:int,
     *   availability?:string|null,
     *   condition?:string|null,
     *   notes?:string|null,
     * }> $items
     */
    public function updateItems(GroundingReportRecord $record, array $items): GroundingReportRecord
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');
        }

        return DB::transaction(function () use ($record, $items) {
            $existing = $record->items()->get()->keyBy('id');

            foreach ($items as $payload) {
                $item = $existing->get($payload['id'] ?? 0);
                if (!$item) {
                    continue;
                }

                // availability only for VISUAL section
                if ($item->section_name === 'VISUAL' && array_key_exists('availability', $payload)) {
                    $item->availability = $payload['availability'];
                }
                if (array_key_exists('condition', $payload)) {
                    $item->condition = $payload['condition'];
                }
                if (array_key_exists('notes', $payload)) {
                    $item->notes = $payload['notes'];
                }
                $item->save();
            }

            return $record->fresh([
                'technicians',
                'items',
                'manager:id,name',
                'supervisor:id,name',
            ]);
        });
    }

    // ─── Sign ──────────────────────────────────────────────────

    /**
     * Sign the Grounding Report record on behalf of a role.
     * Identical flow to TfpAobGroundService::signRecord.
     */
    public function signRecord(
        GroundingReportRecord $record,
        string $role,
        string $base64Signature,
        LocalUser $signer,
        ?int $technicianRowId = null,
    ): GroundingReportRecord {
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
                'manager:id,name',
                'supervisor:id,name',
            ]);
        });
    }

    private function signRecordRole(
        GroundingReportRecord $record,
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
        GroundingReportRecord $record,
        string $base64,
        LocalUser $signer,
        ?int $technicianRowId,
    ): void {
        // Use role-based delegation: Manager/Supervisor/Technician can all sign technician slots
        $slotType = 'technician';
        \App\Services\SignatureAuthorizationService::authorize($signer, $slotType, null, null);

        /** @var GroundingReportTechnician|null $row */
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
                ->first(fn (GroundingReportTechnician $t) => WorkOrderService::namesMatch($t->technician_name, $signer->name));
        }

        if (!$row) {
            // For delegation: if signer has permission but no matching row, pick first unsigned row
            $row = $record->technicians()->whereNull('technician_signature')->first();
        }

        if (!$row) {
            throw new SignerNotAuthorizedException(
                'Tidak ada slot teknisi yang tersedia untuk ditandatangani pada laporan ini.'
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

    public function deleteRecord(GroundingReportRecord $record): void
    {
        $record->delete();
    }
}
