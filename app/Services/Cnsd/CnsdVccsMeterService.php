<?php

namespace App\Services\Cnsd;

use App\Exceptions\CnsdVccsMeterDuplicateException;
use App\Exceptions\SignerNotAuthorizedException;
use App\Models\Cnsd\CnsdVccsMeterItem;
use App\Models\Cnsd\CnsdVccsMeterRecord;
use App\Models\Cnsd\CnsdVccsMeterTechnician;
use App\Models\LocalUser;
use App\Services\LocalUserResolver;
use App\Services\RosteringIntegrationService;
use App\Services\WorkOrderService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * CnsdVccsMeterService — business logic for CNSD VCCS LES Meter Reading.
 *
 * Mirrors CnsdAmscMeterService pattern: create, list, update items + metadata,
 * sign, delete. Roster integration identical to other CNSD modules (CNS-only).
 */
class CnsdVccsMeterService
{
    public function __construct(
        protected RosteringIntegrationService $rosteringService,
        protected LocalUserResolver $userResolver,
    ) {}

    // ─── List ──────────────────────────────────────────────────

    public function listRecords(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = CnsdVccsMeterRecord::query()->withCount('technicians');

        if (!empty($filters['form_type'])) {
            $query->where('form_type', $filters['form_type']);
        }
        if (!empty($filters['date'])) {
            $query->whereDate('date', $filters['date']);
        }
        if (!empty($filters['year'])) {
            $query->whereRaw('EXTRACT(YEAR FROM date) = ?', [(int) $filters['year']]);
        }
        if (!empty($filters['shift_type'])) {
            $query->where('shift_type', $filters['shift_type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
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

    public function findRecord(int $id): ?CnsdVccsMeterRecord
    {
        return CnsdVccsMeterRecord::with(['technicians', 'items', 'manager:id,name', 'supervisor:id,name', 'creator:id,name'])
            ->find($id);
    }

    public function findExistingRecord(string $formType, string $facility, string $date, string $shiftType): ?CnsdVccsMeterRecord
    {
        return CnsdVccsMeterRecord::where('form_type', $formType)
            ->where('facility', $facility)
            ->whereDate('date', $date)
            ->where('shift_type', $shiftType)
            ->first();
    }

    // ─── Create ────────────────────────────────────────────────

    public function createRecord(array $data, ?LocalUser $creator = null): CnsdVccsMeterRecord
    {
        $formType      = $data['form_type']      ?? 'VCCS-METER';
        $facility      = $data['facility']       ?? 'VCCS';
        $date          = $data['date'];
        $shiftType     = $data['shift_type'];
        $location      = $data['location']       ?? 'Kantor Cabang Surabaya / Cabang Surabaya';
        $merk          = array_key_exists('merk', $data) ? $data['merk'] : 'LES';
        $type          = array_key_exists('type', $data) ? $data['type'] : null;
        $serialNumber  = array_key_exists('serial_number', $data) ? $data['serial_number'] : null;

        $existing = $this->findExistingRecord($formType, $facility, $date, $shiftType);
        if ($existing) {
            throw new CnsdVccsMeterDuplicateException($existing);
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

            $record = CnsdVccsMeterRecord::create([
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
                CnsdVccsMeterTechnician::create([
                    'vccs_meter_record_id' => $record->id,
                    'technician_id'        => $tech['local_id'],
                    'technician_name'      => $tech['name'],
                    'sort_order'           => $sort++,
                ]);
            }

            $itemRows = CnsdVccsMeterTemplate::buildItemRows($record->id);
            if (!empty($itemRows)) {
                CnsdVccsMeterItem::insert($itemRows);
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
            Log::warning('CnsdVccsMeterService: roster lookup failed', [
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
     * Format: VCCS-{YYMMDD}-{SEQ}
     * Example: VCCS-260523-001
     */
    public function generateFormNumber(string $formType, string $facility, string $date): string
    {
        $dateYymmdd = date('ymd', strtotime($date));
        $prefix = 'VCCS-' . $dateYymmdd;

        $count = CnsdVccsMeterRecord::withTrashed()
            ->where('form_number', 'LIKE', $prefix . '%')
            ->count();

        return $prefix . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    // ─── Update ────────────────────────────────────────────────

    public function updateItems(CnsdVccsMeterRecord $record, array $items): CnsdVccsMeterRecord
    {
        return $this->updateRecord($record, ['items' => $items]);
    }

    public function updateRecord(CnsdVccsMeterRecord $record, array $data): CnsdVccsMeterRecord
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

                    /** @var CnsdVccsMeterItem $item */
                    $item = $existing->get($payload['id']);

                    if ($item->is_blocked) {
                        continue;
                    }

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
        CnsdVccsMeterRecord $record,
        string $role,
        string $base64Signature,
        LocalUser $signer,
        ?int $technicianRowId = null,
    ): CnsdVccsMeterRecord {
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
        CnsdVccsMeterRecord $record,
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
        CnsdVccsMeterRecord $record,
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

    public function deleteRecord(CnsdVccsMeterRecord $record): void
    {
        $record->delete();
    }
}
