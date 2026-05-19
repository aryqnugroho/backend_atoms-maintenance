<?php

namespace App\Services\Cnsd;

use App\Exceptions\CnsdGlidepathMeterDuplicateException;
use App\Exceptions\SignerNotAuthorizedException;
use App\Models\Cnsd\CnsdGlidepathMeterItem;
use App\Models\Cnsd\CnsdGlidepathMeterRecord;
use App\Models\Cnsd\CnsdGlidepathMeterTechnician;
use App\Models\LocalUser;
use App\Services\LocalUserResolver;
use App\Services\RosteringIntegrationService;
use App\Services\WorkOrderService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CnsdGlidepathMeterService
{
    public function __construct(
        protected RosteringIntegrationService $rosteringService,
        protected LocalUserResolver $userResolver,
    ) {}

    // ─── List ──────────────────────────────────────────────────

    public function listRecords(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = CnsdGlidepathMeterRecord::query()->withCount('technicians');

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

        $sortBy  = in_array($filters['sort_by']  ?? '', ['date','form_number','shift_type','status','created_at'], true) ? $filters['sort_by'] : 'date';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir)->orderByDesc('id');

        return $query->paginate($perPage);
    }

    // ─── Find ──────────────────────────────────────────────────

    public function findRecord(int $id): ?CnsdGlidepathMeterRecord
    {
        return CnsdGlidepathMeterRecord::with(['technicians', 'items', 'manager:id,name', 'supervisor:id,name', 'creator:id,name'])->find($id);
    }

    public function findExistingRecord(string $formType, string $facility, string $date, string $shiftType): ?CnsdGlidepathMeterRecord
    {
        return CnsdGlidepathMeterRecord::where('form_type', $formType)->where('facility', $facility)->whereDate('date', $date)->where('shift_type', $shiftType)->first();
    }

    // ─── Create ────────────────────────────────────────────────

    public function createRecord(array $data, ?LocalUser $creator = null): CnsdGlidepathMeterRecord
    {
        $formType  = $data['form_type']  ?? 'GLIDEPATH-METER';
        $facility  = $data['facility']   ?? 'GLIDEPATH';
        $formCode  = $data['form_code']  ?? 'ILS-GP';
        $date      = $data['date'];
        $shiftType = $data['shift_type'];
        $location  = $data['location']   ?? 'Kantor Cabang Surabaya';

        $existing = $this->findExistingRecord($formType, $facility, $date, $shiftType);
        if ($existing) throw new CnsdGlidepathMeterDuplicateException($existing);

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

            $record = CnsdGlidepathMeterRecord::create([
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
                CnsdGlidepathMeterTechnician::create([
                    'glidepath_meter_record_id' => $record->id,
                    'technician_id'             => $tech['local_id'],
                    'technician_name'           => $tech['name'],
                    'sort_order'                => $sort++,
                ]);
            }

            $itemRows = CnsdGlidepathMeterTemplate::buildItemRows($record->id);
            if (!empty($itemRows)) CnsdGlidepathMeterItem::insert($itemRows);

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
            Log::warning('CnsdGlidepathMeterService: roster lookup failed', ['error' => $e->getMessage()]);
        }

        return ['manager' => $manager, 'supervisor' => $supervisor, 'technicians' => $technicians];
    }

    // ─── Form Number ───────────────────────────────────────────

    /** Format: GLIDEPATH-{YYMMDD}-{SEQ}  e.g. GLIDEPATH-260519-001 */
    public function generateFormNumber(string $date): string
    {
        $prefix = 'GLIDEPATH-' . date('ymd', strtotime($date));
        $count  = CnsdGlidepathMeterRecord::withTrashed()->where('form_number', 'LIKE', $prefix . '%')->count();
        return $prefix . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    // ─── Update ────────────────────────────────────────────────

    public function updateItems(CnsdGlidepathMeterRecord $record, array $items): CnsdGlidepathMeterRecord
    {
        if ($record->status === 'completed') throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');

        return DB::transaction(function () use ($record, $items) {
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

    public function signRecord(CnsdGlidepathMeterRecord $record, string $role, string $base64, LocalUser $signer, ?int $techRowId = null): CnsdGlidepathMeterRecord
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

    private function signRecordRole(CnsdGlidepathMeterRecord $record, string $role, string $base64, LocalUser $signer): void
    {
        $map = [
            'manager'    => ['check' => fn ($u) => $u->isManager(),                                    'name_col' => 'manager_name',    'sig_col' => 'manager_signature',    'by_col' => 'manager_signed_by',    'at_col' => 'manager_signed_at'],
            'supervisor' => ['check' => fn ($u) => $u->isSupervisor() || $u->isSupervisorCnsd(),       'name_col' => 'supervisor_name', 'sig_col' => 'supervisor_signature', 'by_col' => 'supervisor_signed_by', 'at_col' => 'supervisor_signed_at'],
        ];
        if (!isset($map[$role])) throw new \InvalidArgumentException("Role '$role' tidak valid.");
        $cfg = $map[$role];
        if (!($cfg['check'])($signer)) throw new SignerNotAuthorizedException("Anda tidak memiliki role yang sesuai untuk menandatangani sebagai $role.");
        $expectedName = $record->{$cfg['name_col']};
        if (!$expectedName || !WorkOrderService::namesMatch($expectedName, $signer->name)) throw new SignerNotAuthorizedException("Nama Anda tidak cocok dengan $role yang tercatat pada form ini.");
        if (!empty($record->{$cfg['sig_col']})) throw new RuntimeException("Tanda tangan $role sudah ada dan tidak dapat diubah.");
        $record->{$cfg['sig_col']} = $base64;
        $record->{$cfg['by_col']}  = $signer->id;
        $record->{$cfg['at_col']}  = now();
        $record->save();
    }

    private function signTechnicianRow(CnsdGlidepathMeterRecord $record, string $base64, LocalUser $signer, ?int $techRowId): void
    {
        if (!$signer->isTeknisi() && !$signer->isTeknisiCnsd()) throw new SignerNotAuthorizedException('Anda tidak memiliki role Teknisi CNSD.');
        $row = $techRowId ? $record->technicians()->find($techRowId) : null;
        if (!$row) $row = $record->technicians()->where('technician_id', $signer->id)->first();
        if (!$row) $row = $record->technicians->first(fn ($t) => WorkOrderService::namesMatch($t->technician_name, $signer->name));
        if (!$row) throw new SignerNotAuthorizedException('Nama Anda tidak terdaftar sebagai teknisi pada form ini.');
        if (!WorkOrderService::namesMatch($row->technician_name, $signer->name)) throw new SignerNotAuthorizedException('Nama Anda tidak cocok dengan baris teknisi yang dituju.');
        if (!empty($row->technician_signature)) throw new RuntimeException('Baris teknisi ini sudah ditandatangani dan tidak dapat diubah.');
        $row->technician_signature = $base64;
        $row->technician_signed_by = $signer->id;
        $row->technician_signed_at = now();
        $row->save();
    }

    private function validateSignature(string $base64): void
    {
        if (!str_starts_with($base64, 'data:image/png;base64,')) throw new \InvalidArgumentException('Signature harus berupa data URL PNG base64.');
        if (base64_decode(substr($base64, strlen('data:image/png;base64,')), true) === false) throw new \InvalidArgumentException('Data base64 signature tidak valid.');
    }

    // ─── Delete ────────────────────────────────────────────────

    public function deleteRecord(CnsdGlidepathMeterRecord $record): void { $record->delete(); }
}
