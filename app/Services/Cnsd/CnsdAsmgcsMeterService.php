<?php

namespace App\Services\Cnsd;

use App\Exceptions\CnsdAsmgcsMeterDuplicateException;
use App\Exceptions\SignerNotAuthorizedException;
use App\Models\Cnsd\CnsdAsmgcsMeterItem;
use App\Models\Cnsd\CnsdAsmgcsMeterRecord;
use App\Models\Cnsd\CnsdAsmgcsMeterTechnician;
use App\Models\LocalUser;
use App\Services\LocalUserResolver;
use App\Services\RosteringIntegrationService;
use App\Services\WorkOrderService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * CnsdAsmgcsMeterService — business logic for CNSD ASMGCS Meter Reading (SAAB).
 * Mirrors the VCCS Freq service pattern.
 */
class CnsdAsmgcsMeterService
{
    public function __construct(
        protected RosteringIntegrationService $rosteringService,
        protected LocalUserResolver $userResolver,
    ) {}

    // ─── List ──────────────────────────────────────────────────

    public function listRecords(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = CnsdAsmgcsMeterRecord::query()->withCount('technicians');

        if (!empty($filters['form_type']))  $query->where('form_type', $filters['form_type']);
        if (!empty($filters['date']))       $query->whereDate('date', $filters['date']);
        if (!empty($filters['year']))       $query->whereRaw('EXTRACT(YEAR FROM date) = ?', [(int) $filters['year']]);
        if (!empty($filters['shift_type'])) $query->where('shift_type', $filters['shift_type']);
        if (!empty($filters['status']))     $query->where('status', $filters['status']);
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('form_number', 'ILIKE', $search)
                  ->orWhere('manager_name', 'ILIKE', $search)
                  ->orWhere('supervisor_name', 'ILIKE', $search);
            });
        }

        $sortBy  = $filters['sort_by']  ?? 'date';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $allowed = ['date', 'form_number', 'shift_type', 'status', 'created_at'];
        if (in_array($sortBy, $allowed, true)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }
        $query->orderByDesc('id');

        return $query->paginate($perPage);
    }

    // ─── Find ──────────────────────────────────────────────────

    public function findRecord(int $id): ?CnsdAsmgcsMeterRecord
    {
        return CnsdAsmgcsMeterRecord::with(['technicians', 'items', 'manager:id,name', 'supervisor:id,name', 'creator:id,name'])
            ->find($id);
    }

    public function findExistingRecord(string $formType, string $facility, string $date, string $shiftType): ?CnsdAsmgcsMeterRecord
    {
        return CnsdAsmgcsMeterRecord::where('form_type', $formType)
            ->where('facility', $facility)
            ->whereDate('date', $date)
            ->where('shift_type', $shiftType)
            ->first();
    }

    // ─── Create ────────────────────────────────────────────────

    public function createRecord(array $data, ?LocalUser $creator = null): CnsdAsmgcsMeterRecord
    {
        $formType      = $data['form_type']      ?? 'ASMGCS-METER';
        $facility      = $data['facility']       ?? 'ASMGCS';
        $date          = $data['date'];
        $shiftType     = $data['shift_type'];
        $location      = $data['location']       ?? 'Kantor Cabang Surabaya / Cabang Surabaya';
        $merk          = array_key_exists('merk', $data) ? $data['merk'] : 'SAAB';
        $type          = array_key_exists('type', $data) ? $data['type'] : null;
        $serialNumber  = array_key_exists('serial_number', $data) ? $data['serial_number'] : null;

        $existing = $this->findExistingRecord($formType, $facility, $date, $shiftType);
        if ($existing) {
            throw new CnsdAsmgcsMeterDuplicateException($existing);
        }

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

            $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            $dayName  = $dayNames[(int) now()->format('w')];

            $record = CnsdAsmgcsMeterRecord::create([
                'form_number'     => $this->generateFormNumber($formType, $facility, $date),
                'form_type'       => $formType,
                'facility'        => $facility,
                'date'            => $date,
                'shift_type'      => $shiftType,
                'day_name'        => $dayName,
                'time_filled'     => now()->format('H:i'),
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

            $sort = 0;
            foreach ($rosterContext['technicians'] as $tech) {
                CnsdAsmgcsMeterTechnician::create([
                    'asmgcs_meter_record_id' => $record->id,
                    'technician_id'          => $tech['local_id'],
                    'technician_name'        => $tech['name'],
                    'sort_order'             => $sort++,
                ]);
            }

            $itemRows = CnsdAsmgcsMeterTemplate::buildItemRows($record->id);
            if (!empty($itemRows)) {
                CnsdAsmgcsMeterItem::insert($itemRows);
            }

            $record->refresh();
            return $record->load(['technicians', 'items', 'manager:id,name', 'supervisor:id,name']);
        });
    }

    // ─── Roster ────────────────────────────────────────────────

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

            $technicians = WorkOrderService::excludeSignerRoles(
                $technicians,
                $rosterSupervisor ? (int) $rosterSupervisor->user_id : null,
                $supervisor?->name,
                $rosterManager ? (int) $rosterManager->user_id : null,
                $manager?->name,
            );
        } catch (\Throwable $e) {
            Log::warning('CnsdAsmgcsMeterService: roster lookup failed', [
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

    // ─── Form Number ───────────────────────────────────────────

    /**
     * Format: ASMGCS-{YYMMDD}-{SEQ}
     */
    public function generateFormNumber(string $formType, string $facility, string $date): string
    {
        $dateYymmdd = date('ymd', strtotime($date));
        $prefix = 'ASMGCS-' . $dateYymmdd;

        $count = CnsdAsmgcsMeterRecord::withTrashed()
            ->where('form_number', 'LIKE', $prefix . '%')
            ->count();

        return $prefix . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    // ─── Update ────────────────────────────────────────────────

    public function updateItems(CnsdAsmgcsMeterRecord $record, array $items): CnsdAsmgcsMeterRecord
    {
        return $this->updateRecord($record, ['items' => $items]);
    }

    public function updateRecord(CnsdAsmgcsMeterRecord $record, array $data): CnsdAsmgcsMeterRecord
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');
        }

        return DB::transaction(function () use ($record, $data) {
            foreach (['merk', 'type', 'serial_number'] as $field) {
                if (array_key_exists($field, $data)) {
                    $record->{$field} = $data[$field];
                }
            }
            if ($record->isDirty()) {
                $record->save();
            }

            if (!empty($data['items']) && is_array($data['items'])) {
                $existing = $record->items()->get()->keyBy('id');

                foreach ($data['items'] as $payload) {
                    if (empty($payload['id']) || !$existing->has($payload['id'])) {
                        continue;
                    }

                    /** @var CnsdAsmgcsMeterItem $item */
                    $item = $existing->get($payload['id']);

                    if ($item->is_blocked) continue;

                    $fillable = ['hasil_a', 'hasil_b', 'hasil', 'keterangan'];
                    foreach ($fillable as $col) {
                        if (array_key_exists($col, $payload)) {
                            $item->{$col} = $payload[$col];
                        }
                    }
                    $item->save();
                }
            }

            $record->time_filled = now()->format('H:i');
            $record->save();

            $record->refresh();
            return $record->load(['technicians', 'items', 'manager:id,name', 'supervisor:id,name']);
        });
    }

    // ─── Sign ──────────────────────────────────────────────────

    public function signRecord(
        CnsdAsmgcsMeterRecord $record,
        string $role,
        string $base64Signature,
        LocalUser $signer,
        ?int $technicianRowId = null,
    ): CnsdAsmgcsMeterRecord {
        $this->validateBase64PngSignature($base64Signature);

        if ($record->status === 'completed') {
            throw new RuntimeException('Form sudah completed, tidak bisa ditandatangani lagi.');
        }

        if ($role === 'technician') {
            $this->signTechnicianRow($record, $base64Signature, $signer, $technicianRowId);
        } else {
            $this->signRecordRole($record, $role, $base64Signature, $signer);
        }

        $record->refresh();
        if ($record->isComplete()) {
            $record->status = 'completed';
            $record->save();
        }

        return $record->load(['technicians', 'items', 'manager:id,name', 'supervisor:id,name']);
    }

    private function signRecordRole(
        CnsdAsmgcsMeterRecord $record,
        string $role,
        string $base64Signature,
        LocalUser $signer,
    ): void {
        $roleMap = [
            'manager'    => ['name_col' => 'manager_name',    'sig_col' => 'manager_signature',    'by_col' => 'manager_signed_by',    'at_col' => 'manager_signed_at'],
            'supervisor' => ['name_col' => 'supervisor_name', 'sig_col' => 'supervisor_signature', 'by_col' => 'supervisor_signed_by', 'at_col' => 'supervisor_signed_at'],
        ];

        if (!isset($roleMap[$role])) {
            throw new \InvalidArgumentException("Role '$role' tidak valid. Gunakan: manager, supervisor, technician.");
        }

        $config = $roleMap[$role];
        $expectedName = $record->{$config['name_col']};

        $slotType = \App\Services\SignatureAuthorizationService::slotType($role);
        $targetId = match ($role) {
            'manager'    => $record->manager_id ? (int) $record->manager_id : null,
            'supervisor' => $record->supervisor_id ? (int) $record->supervisor_id : null,
            default      => null,
        };

        \App\Services\SignatureAuthorizationService::authorize($signer, $slotType, $targetId, $expectedName);

        if (!empty($record->{$config['sig_col']})) {
            throw new RuntimeException("Tanda tangan $role sudah ada dan tidak dapat diubah.");
        }

        $record->{$config['sig_col']} = $base64Signature;
        $record->{$config['by_col']}  = $signer->id;
        $record->{$config['at_col']}  = now();
        $record->save();
    }

    private function signTechnicianRow(
        CnsdAsmgcsMeterRecord $record,
        string $base64Signature,
        LocalUser $signer,
        ?int $technicianRowId,
    ): void {
        \App\Services\SignatureAuthorizationService::authorize($signer, 'technician', null, null);

        $row = null;
        if ($technicianRowId) {
            $row = $record->technicians()->find($technicianRowId);
        }

        if (!$row) {
            $row = $record->technicians()->where('technician_id', $signer->id)->first();
        }

        if (!$row) {
            $row = $record->technicians->first(function ($t) use ($signer) {
                return WorkOrderService::namesMatch($t->technician_name, $signer->name);
            });
        }

        if (!$row) {
            $row = $record->technicians()->whereNull('technician_signature')->first();
        }

        if (!$row) {
            throw new SignerNotAuthorizedException(
                'Tidak ada slot teknisi yang tersedia untuk ditandatangani pada form ini.'
            );
        }

        if (!empty($row->technician_signature)) {
            throw new RuntimeException('Baris teknisi ini sudah ditandatangani dan tidak dapat diubah.');
        }

        $row->technician_signature = $base64Signature;
        $row->technician_signed_by = $signer->id;
        $row->technician_signed_at = now();
        if (in_array('technician_signed_by_name', $row->getFillable(), true) || array_key_exists('technician_signed_by_name', $row->getAttributes())) {
            $row->technician_signed_by_name = $signer->name;
            $row->technician_signed_by_role = $signer->role;
        }
        $row->save();
    }

    private function validateBase64PngSignature(string $base64): void
    {
        if (!str_starts_with($base64, 'data:image/png;base64,')) {
            throw new \InvalidArgumentException('Signature harus berupa data URL PNG base64 (data:image/png;base64,...).');
        }

        $data = substr($base64, strlen('data:image/png;base64,'));
        if (base64_decode($data, true) === false) {
            throw new \InvalidArgumentException('Data base64 signature tidak valid.');
        }
    }

    // ─── Delete ────────────────────────────────────────────────

    public function deleteRecord(CnsdAsmgcsMeterRecord $record): void
    {
        $record->delete();
    }
}
