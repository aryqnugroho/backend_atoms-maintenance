<?php

namespace App\Services\Cnsd;

use App\Exceptions\CnsdLocalizerMeterDuplicateException;
use App\Exceptions\SignerNotAuthorizedException;
use App\Models\Cnsd\CnsdLocalizerMeterItem;
use App\Models\Cnsd\CnsdLocalizerMeterRecord;
use App\Models\Cnsd\CnsdLocalizerMeterTechnician;
use App\Models\LocalUser;
use App\Services\LocalUserResolver;
use App\Services\RosteringIntegrationService;
use App\Services\WorkOrderService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CnsdLocalizerMeterService
{
    public function __construct(
        protected RosteringIntegrationService $rosteringService,
        protected LocalUserResolver $userResolver,
    ) {}

    // ─── List ──────────────────────────────────────────────────

    public function listRecords(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = CnsdLocalizerMeterRecord::query()->withCount('technicians');

        if (!empty($filters['date']))       $query->whereDate('date', $filters['date']);
        if (!empty($filters['year']))       $query->whereRaw('EXTRACT(YEAR FROM date) = ?', [(int) $filters['year']]);
        if (!empty($filters['shift_type'])) $query->where('shift_type', $filters['shift_type']);
        if (!empty($filters['status']))     $query->where('status', $filters['status']);
        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $query->where(fn ($q) => $q->where('form_number', 'ILIKE', $s)
                ->orWhere('manager_name', 'ILIKE', $s)
                ->orWhere('supervisor_name', 'ILIKE', $s));
        }

        $sortBy  = in_array($filters['sort_by'] ?? '', ['date','form_number','shift_type','status','created_at'], true) ? $filters['sort_by'] : 'date';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir)->orderByDesc('id');

        return $query->paginate($perPage);
    }

    // ─── Find ──────────────────────────────────────────────────

    public function findRecord(int $id): ?CnsdLocalizerMeterRecord
    {
        return CnsdLocalizerMeterRecord::with(['technicians', 'items', 'manager:id,name', 'supervisor:id,name', 'creator:id,name'])->find($id);
    }

    public function findExistingRecord(string $formType, string $facility, string $date, string $shiftType): ?CnsdLocalizerMeterRecord
    {
        return CnsdLocalizerMeterRecord::where('form_type', $formType)->where('facility', $facility)->whereDate('date', $date)->where('shift_type', $shiftType)->first();
    }

    // ─── Create ────────────────────────────────────────────────

    public function createRecord(array $data, ?LocalUser $creator = null): CnsdLocalizerMeterRecord
    {
        $formType  = $data['form_type']  ?? 'LOCALIZER-METER';
        $facility  = $data['facility']   ?? 'ILS LOCALIZER';
        $formCode  = $data['form_code']  ?? 'ILS-LLZ';
        $date      = $data['date'];
        $shiftType = $data['shift_type'];
        $location  = $data['location']   ?? 'Kantor Cabang Surabaya';

        $existing = $this->findExistingRecord($formType, $facility, $date, $shiftType);
        if ($existing) throw new CnsdLocalizerMeterDuplicateException($existing);

        $rosterContext = $this->resolveRosterContext($shiftType, $date);
        if (empty($rosterContext['technicians'])) {
            throw new RuntimeException(
                'Tidak ada teknisi CNSD yang bertugas pada tanggal ' . $date . ' shift ' . $shiftType
                . '. Pastikan roster sudah dipublish dan terdapat personel CNS untuk shift ini.'
            );
        }

        return DB::transaction(function () use ($formType, $facility, $formCode, $date, $shiftType, $location, $data, $creator, $rosterContext) {
            $manager    = $rosterContext['manager'];
            $supervisor = $rosterContext['supervisor'];
            $dayNames   = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

            $record = CnsdLocalizerMeterRecord::create([
                'form_number'     => $this->generateFormNumber($date),
                'form_type'       => $formType,
                'facility'        => $facility,
                'form_code'       => $formCode,
                'merk'            => $data['merk']          ?? null,
                'type'            => $data['type']          ?? null,
                'serial_number'   => $data['serial_number'] ?? null,
                'date'            => $date,
                'shift_type'      => $shiftType,
                'day_name'        => $dayNames[(int) now()->format('w')],
                'time_filled'     => now()->format('H:i'),
                'location'        => $location,
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
                CnsdLocalizerMeterTechnician::create([
                    'localizer_meter_record_id' => $record->id,
                    'technician_id'             => $tech['local_id'],
                    'technician_name'           => $tech['name'],
                    'sort_order'                => $sort++,
                ]);
            }

            $itemRows = CnsdLocalizerMeterTemplate::buildItemRows($record->id);
            if (!empty($itemRows)) CnsdLocalizerMeterItem::insert($itemRows);

            $record->refresh();
            return $record->load(['technicians', 'items', 'manager:id,name', 'supervisor:id,name']);
        });
    }

    // ─── Roster ────────────────────────────────────────────────

    private function resolveRosterContext(string $shiftType, string $date): array
    {
        $manager = $supervisor = null;
        $technicians = [];

        try {
            $rosterManager = $this->rosteringService->getShiftManager($shiftType, $date);
            if ($rosterManager) $manager = $this->userResolver->ensureLocalUser((int) $rosterManager->user_id);

            $rosterSupervisor = $this->rosteringService->getShiftSupervisorByDivision($shiftType, $date, 'CNS');
            if ($rosterSupervisor) $supervisor = $this->userResolver->ensureLocalUser((int) $rosterSupervisor->user_id);

            $personnel = $this->rosteringService->getShiftPersonnel($shiftType, $date);
            foreach ($personnel->filter(fn ($p) => $p->employee_type === 'CNS')->values() as $person) {
                $local = $this->userResolver->ensureLocalUser((int) $person->user_id);
                $technicians[] = ['local_id' => $local?->id, 'name' => $person->name, 'user_id' => (int) $person->user_id];
            }

            $technicians = WorkOrderService::excludeSignerRoles(
                $technicians,
                $rosterSupervisor ? (int) $rosterSupervisor->user_id : null,
                $supervisor?->name,
                $rosterManager ? (int) $rosterManager->user_id : null,
                $manager?->name,
            );
        } catch (\Throwable $e) {
            Log::warning('CnsdLocalizerMeterService: roster lookup failed', ['error' => $e->getMessage()]);
        }

        return ['manager' => $manager, 'supervisor' => $supervisor, 'technicians' => $technicians];
    }

    // ─── Form Number ───────────────────────────────────────────

    /** Format: LOCALIZER-{YYMMDD}-{SEQ}  e.g. LOCALIZER-260519-001 */
    public function generateFormNumber(string $date): string
    {
        $prefix = 'LOCALIZER-' . date('ymd', strtotime($date));
        $count  = CnsdLocalizerMeterRecord::withTrashed()->where('form_number', 'LIKE', $prefix . '%')->count();
        return $prefix . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    // ─── Update ────────────────────────────────────────────────

    public function updateItems(CnsdLocalizerMeterRecord $record, array $items): CnsdLocalizerMeterRecord
    {
        return $this->updateRecord($record, ['items' => $items]);
    }

    public function updateRecord(CnsdLocalizerMeterRecord $record, array $data): CnsdLocalizerMeterRecord
    {
        if ($record->status === 'completed') throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');

        return DB::transaction(function () use ($record, $data) {
            foreach (['merk', 'type', 'serial_number'] as $field) {
                if (array_key_exists($field, $data)) $record->{$field} = $data[$field];
            }
            if ($record->isDirty()) $record->save();

            $items = $data['items'] ?? [];
            $existing = $record->items()->get()->keyBy('id');

            foreach ($items as $payload) {
                if (empty($payload['id']) || !$existing->has($payload['id'])) continue;
                $item = $existing->get($payload['id']);
                if ($item->is_header) continue;

                foreach (['hasil_1', 'hasil_2', 'keterangan'] as $col) {
                    if (array_key_exists($col, $payload)) $item->{$col} = $payload[$col];
                }
                $item->save();
            }

            $record->time_filled = now()->format('H:i');
            $record->save();
            $record->refresh();
            return $record->load(['technicians', 'items', 'manager:id,name', 'supervisor:id,name']);
        });
    }

    // ─── Sign ──────────────────────────────────────────────────

    public function signRecord(CnsdLocalizerMeterRecord $record, string $role, string $base64, LocalUser $signer, ?int $techRowId = null): CnsdLocalizerMeterRecord
    {
        $this->validateSignature($base64);
        if ($record->status === 'completed') throw new RuntimeException('Form sudah completed, tidak bisa ditandatangani lagi.');

        if ($role === 'technician') {
            $this->signTechnicianRow($record, $base64, $signer, $techRowId);
        } else {
            $this->signRecordRole($record, $role, $base64, $signer);
        }

        $record->refresh()->load('technicians');
        if ($record->isComplete()) { $record->status = 'completed'; $record->save(); }
        return $record->load(['technicians', 'items', 'manager:id,name', 'supervisor:id,name']);
    }

    private function signRecordRole(CnsdLocalizerMeterRecord $record, string $role, string $base64, LocalUser $signer): void
    {
        $map = [
            'manager'    => ['name_col' => 'manager_name',    'sig_col' => 'manager_signature',    'by_col' => 'manager_signed_by',    'at_col' => 'manager_signed_at'],
            'supervisor' => ['name_col' => 'supervisor_name', 'sig_col' => 'supervisor_signature', 'by_col' => 'supervisor_signed_by', 'at_col' => 'supervisor_signed_at'],
        ];
        if (!isset($map[$role])) throw new \InvalidArgumentException("Role '$role' tidak valid.");
        $cfg = $map[$role];
        $expectedName = $record->{$cfg['name_col']};

        // Use centralized role-based delegation authorization
        $slotType = \App\Services\SignatureAuthorizationService::slotType($role);
        $targetId = match ($role) {
            'manager'    => $record->manager_id ? (int) $record->manager_id : null,
            'supervisor' => $record->supervisor_id ? (int) $record->supervisor_id : null,
            default      => null,
        };

        \App\Services\SignatureAuthorizationService::authorize($signer, $slotType, $targetId, $expectedName);

        if (!empty($record->{$cfg['sig_col']})) throw new RuntimeException("Tanda tangan $role sudah ada dan tidak dapat diubah.");
        $record->{$cfg['sig_col']} = $base64;
        $record->{$cfg['by_col']}  = $signer->id;
        $record->{$cfg['at_col']}  = now();
        $record->save();
    }

    private function signTechnicianRow(CnsdLocalizerMeterRecord $record, string $base64, LocalUser $signer, ?int $techRowId): void
    {
        // Use role-based delegation: Manager/Supervisor/Technician can all sign technician slots
        \App\Services\SignatureAuthorizationService::authorize($signer, 'technician', null, null);

        $row = $techRowId ? $record->technicians()->find($techRowId) : null;
        if (!$row) $row = $record->technicians()->where('technician_id', $signer->id)->first();
        if (!$row) $row = $record->technicians->first(fn ($t) => WorkOrderService::namesMatch($t->technician_name, $signer->name));
        if (!$row) {
            // For delegation: pick first unsigned row
            $row = $record->technicians()->whereNull('technician_signature')->first();
        }
        if (!$row) throw new SignerNotAuthorizedException('Tidak ada slot teknisi yang tersedia untuk ditandatangani pada form ini.');
        if (!empty($row->technician_signature)) throw new RuntimeException('Baris teknisi ini sudah ditandatangani dan tidak dapat diubah.');
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

    private function validateSignature(string $base64): void
    {
        if (!str_starts_with($base64, 'data:image/png;base64,')) throw new \InvalidArgumentException('Signature harus berupa data URL PNG base64.');
        if (base64_decode(substr($base64, strlen('data:image/png;base64,')), true) === false) throw new \InvalidArgumentException('Data base64 signature tidak valid.');
    }

    public function deleteRecord(CnsdLocalizerMeterRecord $record): void { $record->delete(); }
}
