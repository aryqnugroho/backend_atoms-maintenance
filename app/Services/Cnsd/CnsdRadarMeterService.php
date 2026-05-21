<?php

namespace App\Services\Cnsd;

use App\Exceptions\CnsdRadarMeterDuplicateException;
use App\Exceptions\SignerNotAuthorizedException;
use App\Models\Cnsd\CnsdRadarMeterItem;
use App\Models\Cnsd\CnsdRadarMeterRecord;
use App\Models\Cnsd\CnsdRadarMeterTechnician;
use App\Models\LocalUser;
use App\Services\LocalUserResolver;
use App\Services\RosteringIntegrationService;
use App\Services\WorkOrderService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * CnsdRadarMeterService — orchestrates the Radar Meter Reading form on top of
 * the rostering DB and the signature trait.
 *
 * Mirrors CnsdReadinessService (EQ-1) closely:
 *   - Personnel are resolved from atoms-rostering at create time
 *     (date + shift_type + facility = 'CNSD'). Only CNS technicians are taken.
 *   - Signatures are immutable, name-matched, and never delegated.
 *   - The service NEVER writes to the rostering DB.
 *   - Items are seeded from CnsdRadarMeterTemplate at create.
 */
class CnsdRadarMeterService
{
    public function __construct(
        protected LocalUserResolver $userResolver,
        protected RosteringIntegrationService $rosteringService,
    ) {}

    // ─── Read ──────────────────────────────────────────────────

    public function listRecords(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = CnsdRadarMeterRecord::query()
            ->with([
                'technicians:id,radar_meter_record_id,technician_id,technician_name,technician_signature,sort_order',
                'manager:id,name',
                'supervisor:id,name',
            ])
            ->withCount('technicians');

        if (!empty($filters['form_type'])) {
            $query->byFormType($filters['form_type']);
        } else {
            // Restrict to RADAR-METER by default so future Radar variants don't
            // leak into this list view.
            $query->byFormType('RADAR-METER');
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
                    ->orWhere('supervisor_name', 'ILIKE', $needle)
                    ->orWhere('serial_number', 'ILIKE', $needle);
            });
        }

        $sortBy = $filters['sort_by'] ?? 'date';
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

    public function findRecord(int $id): ?CnsdRadarMeterRecord
    {
        return CnsdRadarMeterRecord::query()
            ->with([
                'technicians',
                'items',
                'manager:id,name',
                'supervisor:id,name',
                'creator:id,name',
            ])
            ->find($id);
    }

    public function findExistingRecord(string $formType, string $facility, string $date, string $shiftType): ?CnsdRadarMeterRecord
    {
        return CnsdRadarMeterRecord::query()
            ->where('form_type', $formType)
            ->where('facility', $facility)
            ->whereDate('date', $date)
            ->where('shift_type', $shiftType)
            ->first();
    }

    // ─── Create ────────────────────────────────────────────────

    /**
     * Create a new Radar Meter Reading record + auto-resolve personnel + seed items.
     *
     * @throws CnsdRadarMeterDuplicateException when a record already exists.
     * @throws RuntimeException when no CNSD technicians are on duty.
     */
    public function createRecord(array $data, ?LocalUser $creator = null): CnsdRadarMeterRecord
    {
        $formType      = $data['form_type']     ?? 'RADAR-METER';
        $facility      = $data['facility']      ?? 'RADAR';
        $date          = $data['date'];
        $shiftType     = $data['shift_type'];
        $location      = $data['location']      ?? 'CABANG SURABAYA';
        $merk          = $data['merk']          ?? 'ELDIS';
        $type          = $data['type']          ?? '5SR-N-I FL2000';
        $serialNumber  = $data['serial_number'] ?? null;

        // Reject duplicate
        $existing = $this->findExistingRecord($formType, $facility, $date, $shiftType);
        if ($existing) {
            throw new CnsdRadarMeterDuplicateException($existing);
        }

        // Resolve roster personnel BEFORE wrapping in a transaction so that
        // a missing CNSD technician fails fast without partial state.
        $rosterContext = $this->resolveRosterContext($shiftType, $date);

        if (empty($rosterContext['technicians'])) {
            throw new RuntimeException(
                'Tidak ada teknisi CNSD yang bertugas pada tanggal '
                . $date . ' shift ' . $shiftType
                . '. Pastikan roster sudah dipublish dan terdapat personel CNS untuk shift ini.'
            );
        }

        return DB::transaction(function () use (
            $formType, $facility, $date, $shiftType, $location, $merk, $type, $serialNumber, $creator, $rosterContext
        ) {
            $manager    = $rosterContext['manager'];
            $supervisor = $rosterContext['supervisor'];

            $record = CnsdRadarMeterRecord::create([
                'form_number'     => $this->generateFormNumber($formType, $facility, $date),
                'form_type'       => $formType,
                'facility'        => $facility,
                'date'            => $date,
                'shift_type'      => $shiftType,
                'location'        => $location,
                'merk'            => $merk,
                'type'            => $type,
                'serial_number'   => $serialNumber,
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
                CnsdRadarMeterTechnician::create([
                    'radar_meter_record_id' => $record->id,
                    'technician_id'         => $tech['local_id'],
                    'technician_name'       => $tech['name'],
                    'sort_order'            => $sort++,
                ]);
            }

            // Seed items from Radar template
            $itemRows = match ($formType) {
                'RADAR-METER' => CnsdRadarMeterTemplate::buildItemRows($record->id),
                default       => [],
            };

            if (!empty($itemRows)) {
                CnsdRadarMeterItem::insert($itemRows);
            }

            $record->refresh();
            return $record->load(['technicians', 'items', 'manager:id,name', 'supervisor:id,name']);
        });
    }

    /**
     * Resolve roster personnel for a given shift+date.
     *
     * Identical filter rules to EQ-1: only employees with employee_type = 'CNS'
     * are accepted as technicians. Supervisor CNSD = a CNS on shift with
     * grade >= 13.
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

            $rosterSupervisor = $this->rosteringService->getShiftSupervisorByDivision($shiftType, $date, 'CNS');
            if ($rosterSupervisor) {
                $supervisor = $this->userResolver->ensureLocalUser((int) $rosterSupervisor->user_id);
            }

            $personnel = $this->rosteringService->getShiftPersonnel($shiftType, $date);
            $cnsOnly   = $personnel->filter(fn ($p) => $p->employee_type === 'CNS')->values();

            foreach ($cnsOnly as $person) {
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
            Log::warning('CnsdRadarMeterService: roster lookup failed', [
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
     * Generate a sequential form number for Radar Meter records.
     *
     * Format: RADAR-{YYMMDD}-{SEQ}
     * Example: RADAR-260517-001
     *
     * Rules:
     *   - Prefix: always "RADAR"
     *   - Date:   YYMMDD (2-digit year + 2-digit month + 2-digit day)
     *   - SEQ:    3-digit zero-padded, reset per calendar date
     *   - Data lama dengan format lama tetap bisa tampil dan dicari (form_number
     *     ILIKE search di listRecords sudah cover kedua format).
     *
     * @param string $formType  (not used in new format, kept for signature compat)
     * @param string $facility  (not used in new format, kept for signature compat)
     * @param string $date      Y-m-d
     */
    public function generateFormNumber(string $formType, string $facility, string $date): string
    {
        // YYMMDD — 2-digit year, 2-digit month, 2-digit day
        $dateYymmdd = date('ymd', strtotime($date));
        $prefix = 'RADAR-' . $dateYymmdd;

        $count = CnsdRadarMeterRecord::withTrashed()
            ->where('form_number', 'LIKE', $prefix . '%')
            ->count();

        return $prefix . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    // ─── Update ────────────────────────────────────────────────

    /**
     * Update item values + equipment metadata on an existing record. Personnel,
     * signatures, date, shift, and form numbers are NOT touched here.
     *
     * @param array{
     *   merk?:string|null,
     *   type?:string|null,
     *   serial_number?:string|null,
     *   items?: array<int, array{
     *     id:int,
     *     kondisi_teknis_tx1?:string|null,
     *     kondisi_teknis_tx2?:string|null,
     *     hasil?:string|null,
     *     keterangan?:string|null,
     *   }>
     * } $data
     */
    public function updateRecord(CnsdRadarMeterRecord $record, array $data): CnsdRadarMeterRecord
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');
        }

        return DB::transaction(function () use ($record, $data) {
            // Equipment metadata fields (header on paper form)
            foreach (['merk', 'type', 'serial_number'] as $field) {
                if (array_key_exists($field, $data)) {
                    $record->{$field} = $data[$field];
                }
            }
            if ($record->isDirty()) {
                $record->save();
            }

            // Item value updates
            if (!empty($data['items']) && is_array($data['items'])) {
                $existing = $record->items()->get()->keyBy('id');

                foreach ($data['items'] as $payload) {
                    if (empty($payload['id']) || !$existing->has($payload['id'])) {
                        continue; // ignore unknown item IDs
                    }

                    /** @var CnsdRadarMeterItem $item */
                    $item = $existing->get($payload['id']);
                    $item->fill(array_intersect_key($payload, array_flip([
                        'kondisi_teknis_tx1',
                        'kondisi_teknis_tx2',
                        'hasil',
                        'keterangan',
                    ])));
                    $item->save();
                }
            }

            return $record->fresh(['technicians', 'items', 'manager:id,name', 'supervisor:id,name']);
        });
    }

    /**
     * Legacy alias for backward-compatibility. Prefer `updateRecord()`.
     */
    public function updateItems(CnsdRadarMeterRecord $record, array $items): CnsdRadarMeterRecord
    {
        return $this->updateRecord($record, ['items' => $items]);
    }

    // ─── Sign ──────────────────────────────────────────────────

    /**
     * Sign the radar meter record on behalf of a role.
     * Identical flow to CnsdReadinessService::signRecord.
     */
    public function signRecord(
        CnsdRadarMeterRecord $record,
        string $role,
        string $base64Signature,
        LocalUser $signer,
        ?int $technicianRowId = null,
    ): CnsdRadarMeterRecord {
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

            return $record->fresh(['technicians', 'items', 'manager:id,name', 'supervisor:id,name']);
        });
    }

    private function signRecordRole(
        CnsdRadarMeterRecord $record,
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
                'Form ini tidak memiliki ' . ($role === 'manager' ? 'Manager Teknik' : 'Supervisor CNSD')
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
        CnsdRadarMeterRecord $record,
        string $base64,
        LocalUser $signer,
        ?int $technicianRowId,
    ): void {
        // Use role-based delegation: Manager/Supervisor/Technician can all sign technician slots
        \App\Services\SignatureAuthorizationService::authorize($signer, 'technician', null, null);

        /** @var CnsdRadarMeterTechnician|null $row */
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
                ->first(fn (CnsdRadarMeterTechnician $t) => WorkOrderService::namesMatch($t->technician_name, $signer->name));
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

    public function deleteRecord(CnsdRadarMeterRecord $record): void
    {
        $record->delete();
    }
}
