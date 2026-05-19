<?php

namespace App\Services\Tfp;

use App\Exceptions\SignerNotAuthorizedException;
use App\Exceptions\TfpGlidepathDuplicateException;
use App\Models\LocalUser;
use App\Models\Tfp\TfpGlidepathFacility;
use App\Models\Tfp\TfpGlidepathItem;
use App\Models\Tfp\TfpGlidepathRecord;
use App\Models\Tfp\TfpGlidepathTechnician;
use App\Services\LocalUserResolver;
use App\Services\RosteringIntegrationService;
use App\Services\WorkOrderService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class TfpGlidepathService
{
    public function __construct(
        protected LocalUserResolver $userResolver,
        protected RosteringIntegrationService $rosteringService,
    ) {}

    public function listRecords(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = TfpGlidepathRecord::query()
            ->with(['technicians:id,glidepath_record_id,technician_id,technician_name,technician_signature,sort_order', 'manager:id,name', 'supervisor:id,name'])
            ->withCount('technicians');
        $query->byFormType($filters['form_type'] ?? 'GLIDEPATH');
        if (!empty($filters['date']))       $query->byDate($filters['date']);
        if (!empty($filters['year']))       $query->whereYear('date', (int) $filters['year']);
        if (!empty($filters['shift_type'])) $query->byShift($filters['shift_type']);
        if (!empty($filters['status']))     $query->where('status', $filters['status']);
        if (!empty($filters['search'])) {
            $needle = '%' . $filters['search'] . '%';
            $query->where(fn ($q) => $q->where('form_number', 'ILIKE', $needle)->orWhere('manager_name', 'ILIKE', $needle)->orWhere('supervisor_name', 'ILIKE', $needle));
        }
        $sortBy  = in_array($filters['sort_by'] ?? 'date', ['date', 'created_at', 'shift_type', 'status'], true) ? ($filters['sort_by'] ?? 'date') : 'date';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        return $query->orderBy($sortBy, $sortDir)->orderByDesc('id')->paginate($perPage);
    }

    public function findRecord(int $id): ?TfpGlidepathRecord
    {
        return TfpGlidepathRecord::query()
            ->with(['technicians', 'items', 'facilities', 'manager:id,name', 'supervisor:id,name', 'creator:id,name'])
            ->find($id);
    }

    public function findExistingRecord(string $formType, string $date, string $shiftType): ?TfpGlidepathRecord
    {
        return TfpGlidepathRecord::query()->where('form_type', $formType)->whereDate('date', $date)->where('shift_type', $shiftType)->first();
    }

    public function createRecord(array $data, ?LocalUser $creator = null): TfpGlidepathRecord
    {
        $formType  = $data['form_type']  ?? 'GLIDEPATH';
        $date      = $data['date'];
        $shiftType = $data['shift_type'];
        $location  = $data['location']   ?? 'GEDUNG GLIDE PATH';

        $existing = $this->findExistingRecord($formType, $date, $shiftType);
        if ($existing) throw new TfpGlidepathDuplicateException($existing);

        $rosterContext = $this->resolveRosterContext($shiftType, $date);
        if (empty($rosterContext['technicians'])) {
            throw new RuntimeException('Tidak ada teknisi TFP yang bertugas pada tanggal ' . $date . ' shift ' . $shiftType . '. Pastikan roster sudah dipublish dan terdapat personel Support untuk shift ini.');
        }

        return DB::transaction(function () use ($formType, $date, $shiftType, $location, $creator, $rosterContext) {
            $manager    = $rosterContext['manager'];
            $supervisor = $rosterContext['supervisor'];
            $carbonDate = Carbon::parse($date);
            $dayNames   = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
            $dayName    = $dayNames[$carbonDate->format('l')] ?? $carbonDate->format('l');

            $record = TfpGlidepathRecord::create([
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

            $sort = 0;
            foreach ($rosterContext['technicians'] as $tech) {
                TfpGlidepathTechnician::create(['glidepath_record_id' => $record->id, 'technician_id' => $tech['local_id'], 'technician_name' => $tech['name'], 'sort_order' => $sort++]);
            }

            $itemRows = TfpGlidepathTemplate::buildItemRows($record->id);
            if (!empty($itemRows)) TfpGlidepathItem::insert($itemRows);

            $facilityRows = TfpGlidepathTemplate::buildFacilityRows($record->id);
            if (!empty($facilityRows)) TfpGlidepathFacility::insert($facilityRows);

            $record->refresh();
            return $record->load(['technicians', 'items', 'facilities', 'manager:id,name', 'supervisor:id,name']);
        });
    }

    private function resolveRosterContext(string $shiftType, string $date): array
    {
        $manager = $supervisor = null;
        $technicians = [];
        try {
            $rosterManager = $this->rosteringService->getShiftManager($shiftType, $date);
            if ($rosterManager) $manager = $this->userResolver->ensureLocalUser((int) $rosterManager->user_id);
            $rosterSupervisor = $this->rosteringService->getShiftSupervisorByDivision($shiftType, $date, 'Support');
            if ($rosterSupervisor) $supervisor = $this->userResolver->ensureLocalUser((int) $rosterSupervisor->user_id);
            $personnel   = $this->rosteringService->getShiftPersonnel($shiftType, $date);
            $supportOnly = $personnel->filter(fn ($p) => $p->employee_type === 'Support')->values();
            foreach ($supportOnly as $person) {
                $local = $this->userResolver->ensureLocalUser((int) $person->user_id);
                $technicians[] = ['local_id' => $local?->id, 'name' => $person->name, 'user_id' => (int) $person->user_id];
            }
        } catch (\Throwable $e) {
            Log::warning('TfpGlidepathService: roster lookup failed', ['shift_type' => $shiftType, 'date' => $date, 'error' => $e->getMessage()]);
        }
        return ['manager' => $manager, 'supervisor' => $supervisor, 'technicians' => $technicians];
    }

    public function generateFormNumber(string $date): string
    {
        $dateYymmdd = date('ymd', strtotime($date));
        $prefix = 'TFP-GP-' . $dateYymmdd;
        $count  = TfpGlidepathRecord::withTrashed()->where('form_number', 'LIKE', $prefix . '%')->count();
        return $prefix . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    public function updateItems(TfpGlidepathRecord $record, array $items): TfpGlidepathRecord
    {
        if ($record->status === 'completed') throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');
        return DB::transaction(function () use ($record, $items) {
            $existing = $record->items()->get()->keyBy('id');
            $allowed  = ['panel_gp01'];
            foreach ($items as $payload) {
                if (empty($payload['id']) || !$existing->has($payload['id'])) continue;
                $item        = $existing->get($payload['id']);
                $disabledMap = is_array($item->is_disabled_map) ? $item->is_disabled_map : [];
                $effective   = array_diff($allowed, array_keys(array_filter($disabledMap, static fn ($v) => $v === true)));
                $item->fill(array_intersect_key($payload, array_flip($effective)));
                $item->save();
            }
            $record->time_filled = now()->format('H:i');
            $record->save();
            return $record->fresh(['technicians', 'items', 'facilities', 'manager:id,name', 'supervisor:id,name']);
        });
    }

    public function updateFacilities(TfpGlidepathRecord $record, array $facilities): TfpGlidepathRecord
    {
        if ($record->status === 'completed') throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');
        return DB::transaction(function () use ($record, $facilities) {
            $existing = $record->facilities()->get()->keyBy('id');
            foreach ($facilities as $payload) {
                if (empty($payload['id']) || !$existing->has($payload['id'])) continue;
                $facility = $existing->get($payload['id']);
                $facility->fill(array_intersect_key($payload, array_flip(['kondisi', 'keterangan'])));
                $facility->save();
            }
            $record->time_filled = now()->format('H:i');
            $record->save();
            return $record->fresh(['technicians', 'items', 'facilities', 'manager:id,name', 'supervisor:id,name']);
        });
    }

    public function signRecord(TfpGlidepathRecord $record, string $role, string $base64Signature, LocalUser $signer, ?int $technicianRowId = null): TfpGlidepathRecord
    {
        $role = strtolower(trim($role));
        if ($record->status === 'completed') throw new RuntimeException('Form yang sudah completed tidak dapat ditandatangani lagi.');
        return DB::transaction(function () use ($record, $role, $base64Signature, $signer, $technicianRowId) {
            if ($role === 'technician') {
                $this->signTechnicianRow($record, $base64Signature, $signer, $technicianRowId);
            } else {
                $this->signRecordRole($record, $role, $base64Signature, $signer);
            }
            $record->refresh();
            $newStatus = $record->isComplete() ? 'completed' : ($record->isShiftEnded() ? 'on_hold' : 'ongoing');
            if ($record->status !== $newStatus) { $record->status = $newStatus; $record->save(); }
            return $record->fresh(['technicians', 'items', 'facilities', 'manager:id,name', 'supervisor:id,name']);
        });
    }

    private function signRecordRole(TfpGlidepathRecord $record, string $role, string $base64, LocalUser $signer): void
    {
        $expectedName = match ($role) { 'manager' => $record->manager_name, 'supervisor' => $record->supervisor_name, default => null };
        if (!$expectedName) throw new SignerNotAuthorizedException('Form ini tidak memiliki ' . ($role === 'manager' ? 'Manager Teknik' : 'Supervisor TFP') . ' yang ditugaskan.');

        // Use centralized role-based delegation authorization
        $slotType = \App\Services\SignatureAuthorizationService::slotType($role);
        $targetId = match ($role) {
            'manager'    => $record->manager_id ? (int) $record->manager_id : null,
            'supervisor' => $record->supervisor_id ? (int) $record->supervisor_id : null,
            default      => null,
        };

        \App\Services\SignatureAuthorizationService::authorize($signer, $slotType, $targetId, $expectedName);

        $record->saveSignature($role, $base64, $signer->id);
    }

    private function signTechnicianRow(TfpGlidepathRecord $record, string $base64, LocalUser $signer, ?int $technicianRowId): void
    {
        // Use role-based delegation: Manager/Supervisor/Technician can all sign technician slots
        \App\Services\SignatureAuthorizationService::authorize($signer, 'technician', null, null);

        $row = null;
        if ($technicianRowId) $row = $record->technicians()->where('id', $technicianRowId)->first();
        if (!$row && $signer->id) $row = $record->technicians()->where('technician_id', $signer->id)->first();
        if (!$row) $row = $record->technicians()->get()->first(fn ($t) => WorkOrderService::namesMatch($t->technician_name, $signer->name));
        if (!$row) {
            // For delegation: pick first unsigned row
            $row = $record->technicians()->whereNull('technician_signature')->first();
        }
        if (!$row) throw new SignerNotAuthorizedException('Tidak ada slot teknisi yang tersedia untuk ditandatangani pada form ini.');
        if (!empty($row->technician_signature)) throw new RuntimeException('Tanda tangan teknisi sudah tersimpan dan tidak dapat diubah.');
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
        if (!str_starts_with($base64, $prefix)) throw new InvalidArgumentException('Signature must be a base64 PNG data URL.');
        $payload = substr($base64, strlen($prefix));
        if ($payload === '' || base64_decode($payload, true) === false) throw new InvalidArgumentException('Signature payload is not valid base64.');
    }

    public function deleteRecord(TfpGlidepathRecord $record): void { $record->delete(); }
}
