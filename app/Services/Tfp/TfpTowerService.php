<?php

namespace App\Services\Tfp;

use App\Exceptions\SignerNotAuthorizedException;
use App\Exceptions\TfpTowerDuplicateException;
use App\Models\LocalUser;
use App\Models\Tfp\TfpTowerFacility;
use App\Models\Tfp\TfpTowerItem;
use App\Models\Tfp\TfpTowerRecord;
use App\Models\Tfp\TfpTowerTechnician;
use App\Services\LocalUserResolver;
use App\Services\RosteringIntegrationService;
use App\Services\WorkOrderService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class TfpTowerService
{
    public function __construct(
        protected LocalUserResolver $userResolver,
        protected RosteringIntegrationService $rosteringService,
    ) {}

    // ─── Read ──────────────────────────────────────────────────

    public function listRecords(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = TfpTowerRecord::query()
            ->with(['technicians:id,tower_record_id,technician_id,technician_name,technician_signature,sort_order', 'manager:id,name', 'supervisor:id,name'])
            ->withCount('technicians');

        $query->byFormType($filters['form_type'] ?? 'TOWER');

        if (!empty($filters['date']))       $query->byDate($filters['date']);
        if (!empty($filters['year']))       $query->whereYear('date', (int) $filters['year']);
        if (!empty($filters['shift_type'])) $query->byShift($filters['shift_type']);
        if (!empty($filters['status']))     $query->where('status', $filters['status']);

        if (!empty($filters['search'])) {
            $needle = '%' . $filters['search'] . '%';
            $query->where(fn ($q) => $q->where('form_number', 'ILIKE', $needle)
                ->orWhere('manager_name', 'ILIKE', $needle)
                ->orWhere('supervisor_name', 'ILIKE', $needle));
        }

        $sortBy  = in_array($filters['sort_by'] ?? 'date', ['date', 'created_at', 'shift_type', 'status'], true) ? ($filters['sort_by'] ?? 'date') : 'date';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDir)->orderByDesc('id')->paginate($perPage);
    }

    public function findRecord(int $id): ?TfpTowerRecord
    {
        return TfpTowerRecord::query()
            ->with(['technicians', 'items', 'facilities', 'manager:id,name', 'supervisor:id,name', 'creator:id,name'])
            ->find($id);
    }

    public function findExistingRecord(string $formType, string $date, string $shiftType): ?TfpTowerRecord
    {
        return TfpTowerRecord::query()
            ->where('form_type', $formType)->whereDate('date', $date)->where('shift_type', $shiftType)
            ->first();
    }

    // ─── Create ────────────────────────────────────────────────

    public function createRecord(array $data, ?LocalUser $creator = null): TfpTowerRecord
    {
        $formType  = $data['form_type']  ?? 'TOWER';
        $date      = $data['date'];
        $shiftType = $data['shift_type'];
        $location  = $data['location']   ?? 'GEDUNG TOWER';

        $existing = $this->findExistingRecord($formType, $date, $shiftType);
        if ($existing) throw new TfpTowerDuplicateException($existing);

        $rosterContext = $this->resolveRosterContext($shiftType, $date);

        if (empty($rosterContext['technicians'])) {
            throw new RuntimeException(
                'Tidak ada teknisi TFP yang bertugas pada tanggal ' . $date . ' shift ' . $shiftType
                . '. Pastikan roster sudah dipublish dan terdapat personel Support untuk shift ini.'
            );
        }

        return DB::transaction(function () use ($formType, $date, $shiftType, $location, $creator, $rosterContext) {
            $manager    = $rosterContext['manager'];
            $supervisor = $rosterContext['supervisor'];

            $carbonDate = Carbon::parse($date);
            $dayNames   = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
            $dayName    = $dayNames[$carbonDate->format('l')] ?? $carbonDate->format('l');

            $record = TfpTowerRecord::create([
                'form_number'     => $this->generateFormNumber($date),
                'form_type'       => $formType,
                'date'            => $date,
                'day_name'        => $dayName,
                'time_filled'     => now()->format('H:i'),
                'shift_type'      => $shiftType,
                'location'        => $location,
                'columns_config'  => TfpTowerTemplate::defaultColumnsConfig(),
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
                TfpTowerTechnician::create([
                    'tower_record_id' => $record->id,
                    'technician_id'   => $tech['local_id'],
                    'technician_name' => $tech['name'],
                    'sort_order'      => $sort++,
                ]);
            }

            $itemRows = TfpTowerTemplate::buildItemRows($record->id);
            if (!empty($itemRows)) TfpTowerItem::insert($itemRows);

            $facilityRows = TfpTowerTemplate::buildFacilityRows($record->id);
            if (!empty($facilityRows)) TfpTowerFacility::insert($facilityRows);

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

            $technicians = WorkOrderService::excludeSignerRoles(
                $technicians,
                $rosterSupervisor ? (int) $rosterSupervisor->user_id : null,
                $supervisor?->name,
                $rosterManager ? (int) $rosterManager->user_id : null,
                $manager?->name,
            );
        } catch (\Throwable $e) {
            Log::warning('TfpTowerService: roster lookup failed', ['shift_type' => $shiftType, 'date' => $date, 'error' => $e->getMessage()]);
        }
        return ['manager' => $manager, 'supervisor' => $supervisor, 'technicians' => $technicians];
    }

    public function generateFormNumber(string $date): string
    {
        $dateYymmdd = date('ymd', strtotime($date));
        $prefix = 'TFP-TOWER-' . $dateYymmdd;
        $count  = TfpTowerRecord::withTrashed()->where('form_number', 'LIKE', $prefix . '%')->count();
        return $prefix . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    // ─── Update items + facilities ─────────────────────────────

    public function updateItems(TfpTowerRecord $record, array $items, ?string $timeOverride = null): TfpTowerRecord
    {
        if ($record->status === 'completed') throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');

        $allowedKeys = $this->cellKeysFromConfig($record);

        return DB::transaction(function () use ($record, $items, $timeOverride, $allowedKeys) {
            $existing = $record->items()->get()->keyBy('id');

            foreach ($items as $payload) {
                if (empty($payload['id']) || !$existing->has($payload['id'])) continue;

                /** @var TfpTowerItem $item */
                $item = $existing->get($payload['id']);
                $incoming = is_array($payload['values'] ?? null) ? $payload['values'] : [];

                $disabledMap = is_array($item->is_disabled_map) ? $item->is_disabled_map : [];
                $disabledKeys = array_keys(array_filter($disabledMap, static fn ($v) => $v === true));

                $clean = [];
                foreach ($incoming as $cellKey => $cellVal) {
                    if (!is_string($cellKey)) continue;
                    if (in_array($cellKey, $disabledKeys, true)) continue;
                    if (!empty($allowedKeys) && !in_array($cellKey, $allowedKeys, true)) continue;
                    $stringVal = $cellVal === null ? null : trim((string) $cellVal);
                    if ($stringVal === null || $stringVal === '') continue;
                    $clean[$cellKey] = mb_substr($stringVal, 0, 100);
                }

                $item->values = empty($clean) ? null : $clean;
                $item->save();
            }

            $record->time_filled = $timeOverride ?: now()->format('H:i');
            $record->save();

            return $this->fresh($record);
        });
    }

    public function updateFacilities(TfpTowerRecord $record, array $facilities, ?string $timeOverride = null): TfpTowerRecord
    {
        if ($record->status === 'completed') throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');

        return DB::transaction(function () use ($record, $facilities, $timeOverride) {
            $existing = $record->facilities()->get()->keyBy('id');
            foreach ($facilities as $payload) {
                if (empty($payload['id']) || !$existing->has($payload['id'])) continue;
                $facility = $existing->get($payload['id']);
                $facility->fill(array_intersect_key($payload, array_flip(['kondisi', 'keterangan'])));
                $facility->save();
            }
            $record->time_filled = $timeOverride ?: now()->format('H:i');
            $record->save();
            return $this->fresh($record);
        });
    }

    private function cellKeysFromConfig(TfpTowerRecord $record): array
    {
        $config = is_array($record->columns_config) ? $record->columns_config : [];
        $keys = [];
        foreach ($config as $panel) {
            $pid = $panel['id'] ?? null;
            $subs = $panel['sub_columns'] ?? [];
            if (!$pid || !is_array($subs)) continue;
            foreach ($subs as $sub) {
                $sk = $sub['key'] ?? null;
                if ($sk) $keys[] = $pid . '.' . $sk;
            }
        }
        return $keys;
    }

    // ─── Structural edit (parameters) ──────────────────────────

    public function addParameter(TfpTowerRecord $record, array $data): TfpTowerRecord
    {
        if ($record->status === 'completed') throw new RuntimeException('Form yang sudah completed tidak dapat diubah strukturnya.');

        $maxSort = (int) ($record->items()->max('sort_order') ?? -1);

        TfpTowerItem::create([
            'tower_record_id'  => $record->id,
            'parameter_number' => $data['parameter_number'] ?? (string) ($maxSort + 2),
            'parameter_name'   => trim((string) $data['parameter_name']),
            'unit'             => isset($data['unit']) ? trim((string) $data['unit']) : null,
            'values'           => null,
            'is_disabled_map'  => null,
            'merge_map'        => null,
            'sort_order'       => $maxSort + 1,
        ]);

        return $this->fresh($record);
    }

    public function updateParameterStructure(TfpTowerRecord $record, int $paramId, array $data): TfpTowerRecord
    {
        if ($record->status === 'completed') throw new RuntimeException('Form yang sudah completed tidak dapat diubah strukturnya.');

        /** @var TfpTowerItem|null $item */
        $item = $record->items()->where('id', $paramId)->first();
        if (!$item) throw new InvalidArgumentException('Parameter tidak ditemukan.');

        $patch = [];
        if (array_key_exists('parameter_name', $data)) {
            $patch['parameter_name'] = trim((string) $data['parameter_name']);
        }
        if (array_key_exists('parameter_number', $data)) {
            $patch['parameter_number'] = $data['parameter_number'] !== null && $data['parameter_number'] !== ''
                ? trim((string) $data['parameter_number']) : null;
        }
        if (array_key_exists('unit', $data)) {
            $patch['unit'] = $data['unit'] !== null && $data['unit'] !== ''
                ? trim((string) $data['unit']) : null;
        }

        if (!empty($patch)) { $item->fill($patch); $item->save(); }
        return $this->fresh($record);
    }

    public function deleteParameter(TfpTowerRecord $record, int $paramId): TfpTowerRecord
    {
        if ($record->status === 'completed') throw new RuntimeException('Form yang sudah completed tidak dapat diubah strukturnya.');
        $deleted = $record->items()->where('id', $paramId)->delete();
        if ($deleted === 0) throw new InvalidArgumentException('Parameter tidak ditemukan.');
        return $this->fresh($record);
    }

    public function reorderParameters(TfpTowerRecord $record, array $orderedIds): TfpTowerRecord
    {
        if ($record->status === 'completed') throw new RuntimeException('Form yang sudah completed tidak dapat diubah strukturnya.');
        DB::transaction(function () use ($record, $orderedIds) {
            foreach ($orderedIds as $index => $id) {
                $record->items()->where('id', $id)->update(['sort_order' => $index]);
            }
        });
        return $this->fresh($record);
    }

    // ─── Structural edit (facilities) ──────────────────────────

    public function addFacility(TfpTowerRecord $record, array $data): TfpTowerRecord
    {
        if ($record->status === 'completed') throw new RuntimeException('Form yang sudah completed tidak dapat diubah strukturnya.');
        $maxSort = (int) ($record->facilities()->max('sort_order') ?? -1);
        TfpTowerFacility::create([
            'tower_record_id' => $record->id,
            'facility_name'   => trim((string) $data['facility_name']),
            'kondisi'         => null, 'keterangan' => null,
            'sort_order'      => $maxSort + 1,
        ]);
        return $this->fresh($record);
    }

    public function updateFacilityStructure(TfpTowerRecord $record, int $facilityId, array $data): TfpTowerRecord
    {
        if ($record->status === 'completed') throw new RuntimeException('Form yang sudah completed tidak dapat diubah strukturnya.');
        /** @var TfpTowerFacility|null $facility */
        $facility = $record->facilities()->where('id', $facilityId)->first();
        if (!$facility) throw new InvalidArgumentException('Fasilitas tidak ditemukan.');
        if (array_key_exists('facility_name', $data)) {
            $facility->facility_name = trim((string) $data['facility_name']);
            $facility->save();
        }
        return $this->fresh($record);
    }

    public function deleteFacility(TfpTowerRecord $record, int $facilityId): TfpTowerRecord
    {
        if ($record->status === 'completed') throw new RuntimeException('Form yang sudah completed tidak dapat diubah strukturnya.');
        $deleted = $record->facilities()->where('id', $facilityId)->delete();
        if ($deleted === 0) throw new InvalidArgumentException('Fasilitas tidak ditemukan.');
        return $this->fresh($record);
    }

    public function reorderFacilities(TfpTowerRecord $record, array $orderedIds): TfpTowerRecord
    {
        if ($record->status === 'completed') throw new RuntimeException('Form yang sudah completed tidak dapat diubah strukturnya.');
        DB::transaction(function () use ($record, $orderedIds) {
            foreach ($orderedIds as $index => $id) {
                $record->facilities()->where('id', $id)->update(['sort_order' => $index]);
            }
        });
        return $this->fresh($record);
    }

    private function fresh(TfpTowerRecord $record): TfpTowerRecord
    {
        return $record->fresh(['technicians', 'items', 'facilities', 'manager:id,name', 'supervisor:id,name']);
    }

    public function saveStructure(TfpTowerRecord $record, array $columnsConfig, array $itemPatches): TfpTowerRecord
    {
        if ($record->status === 'completed') throw new RuntimeException('Form yang sudah completed tidak dapat diubah strukturnya.');

        $normalized = $this->normalizeColumnsConfig($columnsConfig);
        if (empty($normalized)) throw new InvalidArgumentException('Minimal harus ada satu panel dengan satu sub-kolom.');

        $allowedKeys = [];
        foreach ($normalized as $panel) {
            foreach ($panel['sub_columns'] as $sub) {
                $allowedKeys[] = $panel['id'] . '.' . $sub['key'];
            }
        }

        return DB::transaction(function () use ($record, $normalized, $itemPatches, $allowedKeys) {
            $record->columns_config = $normalized;
            $record->save();

            $existing = $record->items()->get()->keyBy('id');

            foreach ($itemPatches as $patch) {
                if (empty($patch['id']) || !$existing->has($patch['id'])) continue;

                /** @var TfpTowerItem $item */
                $item = $existing->get($patch['id']);

                if (array_key_exists('is_disabled_map', $patch)) {
                    $map = is_array($patch['is_disabled_map']) ? $patch['is_disabled_map'] : [];
                    $clean = [];
                    foreach ($map as $k => $v) {
                        if (is_string($k) && in_array($k, $allowedKeys, true) && $v === true) {
                            $clean[$k] = true;
                        }
                    }
                    $item->is_disabled_map = empty($clean) ? null : $clean;
                }

                if (array_key_exists('merge_map', $patch)) {
                    $map = is_array($patch['merge_map']) ? $patch['merge_map'] : [];
                    $clean = [];
                    foreach ($map as $k => $v) {
                        $span = (int) $v;
                        if (is_string($k) && in_array($k, $allowedKeys, true) && $span >= 2) {
                            $clean[$k] = $span;
                        }
                    }
                    $item->merge_map = empty($clean) ? null : $clean;
                }

                $values = is_array($item->values) ? $item->values : [];
                $pruned = array_intersect_key($values, array_flip($allowedKeys));
                if (count($pruned) !== count($values)) {
                    $item->values = empty($pruned) ? null : $pruned;
                }

                $item->save();
            }

            $patchedIds = array_filter(array_map(fn ($p) => $p['id'] ?? null, $itemPatches));
            foreach ($existing as $item) {
                if (in_array($item->id, $patchedIds, true)) continue;
                $values = is_array($item->values) ? $item->values : [];
                $pruned = array_intersect_key($values, array_flip($allowedKeys));
                if (count($pruned) !== count($values)) {
                    $item->values = empty($pruned) ? null : $pruned;
                    $item->save();
                }
            }

            return $this->fresh($record);
        });
    }

    private function normalizeColumnsConfig(array $raw): array
    {
        $out = [];
        $seenIds = [];

        foreach ($raw as $panel) {
            $id    = isset($panel['id'])    ? $this->slug((string) $panel['id'])    : null;
            $label = isset($panel['label']) ? trim((string) $panel['label']) : '';
            $subs  = isset($panel['sub_columns']) && is_array($panel['sub_columns']) ? $panel['sub_columns'] : [];

            if (!$id || $label === '' || empty($subs)) continue;
            if (in_array($id, $seenIds, true)) continue;

            $cleanSubs = [];
            $seenSubKeys = [];
            foreach ($subs as $sub) {
                $sk = isset($sub['key'])   ? $this->slug((string) $sub['key'])   : null;
                $sl = isset($sub['label']) ? trim((string) $sub['label']) : '';
                if (!$sk || $sl === '' || in_array($sk, $seenSubKeys, true)) continue;
                $cleanSubs[] = ['key' => $sk, 'label' => mb_substr($sl, 0, 60)];
                $seenSubKeys[] = $sk;
            }

            if (empty($cleanSubs)) continue;

            $out[] = ['id' => $id, 'label' => mb_substr($label, 0, 80), 'sub_columns' => $cleanSubs];
            $seenIds[] = $id;
        }

        return $out;
    }

    private function slug(string $raw): string
    {
        $s = mb_strtolower(trim($raw));
        $s = preg_replace('/[^a-z0-9]+/u', '_', $s) ?? '';
        $s = preg_replace('/_+/', '_', $s) ?? '';
        return trim($s, '_');
    }

    // ─── Sign ──────────────────────────────────────────────────

    public function signRecord(TfpTowerRecord $record, string $role, string $base64Signature, LocalUser $signer, ?int $technicianRowId = null): TfpTowerRecord
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

            return $this->fresh($record);
        });
    }

    private function signRecordRole(TfpTowerRecord $record, string $role, string $base64, LocalUser $signer): void
    {
        $expectedName = match ($role) {
            'manager'    => $record->manager_name,
            'supervisor' => $record->supervisor_name,
            default      => null,
        };
        if (!$expectedName) throw new SignerNotAuthorizedException('Form ini tidak memiliki ' . ($role === 'manager' ? 'Manager Teknik' : 'Supervisor TFP') . ' yang ditugaskan.');

        $slotType = \App\Services\SignatureAuthorizationService::slotType($role);
        $targetId = match ($role) {
            'manager'    => $record->manager_id ? (int) $record->manager_id : null,
            'supervisor' => $record->supervisor_id ? (int) $record->supervisor_id : null,
            default      => null,
        };

        \App\Services\SignatureAuthorizationService::authorize($signer, $slotType, $targetId, $expectedName);

        $record->saveSignature($role, $base64, $signer->id);
    }

    private function signTechnicianRow(TfpTowerRecord $record, string $base64, LocalUser $signer, ?int $technicianRowId): void
    {
        \App\Services\SignatureAuthorizationService::authorize($signer, 'technician', null, null);

        $row = null;
        if ($technicianRowId) $row = $record->technicians()->where('id', $technicianRowId)->first();
        if (!$row && $signer->id) $row = $record->technicians()->where('technician_id', $signer->id)->first();
        if (!$row) $row = $record->technicians()->get()->first(fn ($t) => WorkOrderService::namesMatch($t->technician_name, $signer->name));

        if (!$row) $row = $record->technicians()->whereNull('technician_signature')->first();
        if (!$row) throw new SignerNotAuthorizedException('Tidak ada slot teknisi yang tersedia untuk ditandatangani pada form ini.');
        if (!empty($row->technician_signature)) throw new RuntimeException('Tanda tangan teknisi sudah tersimpan dan tidak dapat diubah.');

        $this->validateBase64PngSignature($base64);
        $row->technician_signature = $base64;
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
        $prefix = 'data:image/png;base64,';
        if (!str_starts_with($base64, $prefix)) throw new InvalidArgumentException('Signature must be a base64 PNG data URL.');
        $payload = substr($base64, strlen($prefix));
        if ($payload === '' || base64_decode($payload, true) === false) throw new InvalidArgumentException('Signature payload is not valid base64.');
    }

    public function deleteRecord(TfpTowerRecord $record): void { $record->delete(); }
}
