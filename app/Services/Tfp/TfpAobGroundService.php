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
                'columns_config'  => TfpAobGroundTemplate::defaultColumnsConfig(),
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

            // Exclude supervisor and manager from the technician list.
            // A person assigned as Supervisor or Manager Teknik must not appear
            // again in the Pelaksana Teknisi rows.
            $technicians = \App\Services\WorkOrderService::excludeSignerRoles(
                $technicians,
                $rosterSupervisor ? (int) $rosterSupervisor->user_id : null,
                $supervisor?->name,
                $rosterManager ? (int) $rosterManager->user_id : null,
                $manager?->name,
            );
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
     * Each item payload carries a `values` map keyed by composite
     * "panel_id.sub_col_key" cell keys. Cells that the per-item
     * is_disabled_map marks as disabled are silently dropped so that the
     * frontend cannot accidentally write to grey cells.
     *
     * @param array<int, array{ id:int, values?: array<string, string|null> }> $items
     */
    public function updateItems(TfpAobGroundRecord $record, array $items, ?string $timeOverride = null): TfpAobGroundRecord
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');
        }

        // Build the set of allowed cell keys from columns_config so the server
        // also rejects keys for panels the user has not defined.
        $allowedKeys = $this->cellKeysFromConfig($record);

        return DB::transaction(function () use ($record, $items, $timeOverride, $allowedKeys) {
            $existing = $record->items()->get()->keyBy('id');

            foreach ($items as $payload) {
                if (empty($payload['id']) || !$existing->has($payload['id'])) {
                    continue;
                }

                /** @var TfpAobGroundItem $item */
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
                    if ($stringVal === null || $stringVal === '') {
                        continue; // skip — we don't persist blank cells
                    }
                    $clean[$cellKey] = mb_substr($stringVal, 0, 100);
                }

                $item->values = empty($clean) ? null : $clean;
                $item->save();
            }

            // Refresh the "Jam Pelaksanaan" so it reflects when this set of readings was
            // taken. The user can override this via the editable time input on the
            // detail page (paper form treats time as "when this snapshot was taken").
            $record->time_filled = $timeOverride ?: now()->format('H:i');
            $record->save();

            return $this->fresh($record);
        });
    }

    /**
     * Compute the set of allowed cell keys from the record's columns_config.
     * Returns an empty array if columns_config is missing (treated as "anything goes",
     * mainly useful for legacy data without a config yet).
     *
     * @return string[]
     */
    private function cellKeysFromConfig(TfpAobGroundRecord $record): array
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

    /**
     * Update facility condition values on an existing record.
     *
     * @param array<int, array{
     *   id:int,
     *   kondisi?:string|null,
     *   keterangan?:string|null,
     * }> $facilities
     */
    public function updateFacilities(TfpAobGroundRecord $record, array $facilities, ?string $timeOverride = null): TfpAobGroundRecord
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');
        }

        return DB::transaction(function () use ($record, $facilities, $timeOverride) {
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
            $record->time_filled = $timeOverride ?: now()->format('H:i');
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

    // ─── Structural edit (parameters) ──────────────────────────
    //
    // Edit Mode is restricted to Manager Teknik / Supervisor TFP / Admin —
    // enforced at the controller layer. The service trusts the caller and
    // operates on data only.

    public function addParameter(TfpAobGroundRecord $record, array $data): TfpAobGroundRecord
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah strukturnya.');
        }

        $maxSort = (int) ($record->items()->max('sort_order') ?? -1);

        TfpAobGroundItem::create([
            'aob_ground_record_id' => $record->id,
            'parameter_number'     => $data['parameter_number'] ?? (string) ($maxSort + 2),
            'parameter_name'       => trim((string) $data['parameter_name']),
            'unit'                 => isset($data['unit']) ? trim((string) $data['unit']) : null,
            // New parameters default to "all cells enabled, no merges" — Manager
            // can toggle these later via the structure editor.
            'values'               => null,
            'is_disabled_map'      => null,
            'merge_map'            => null,
            'sort_order'           => $maxSort + 1,
        ]);

        return $this->fresh($record);
    }

    public function updateParameterStructure(TfpAobGroundRecord $record, int $paramId, array $data): TfpAobGroundRecord
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah strukturnya.');
        }

        /** @var TfpAobGroundItem|null $item */
        $item = $record->items()->where('id', $paramId)->first();
        if (!$item) {
            throw new InvalidArgumentException('Parameter tidak ditemukan.');
        }

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

        if (!empty($patch)) {
            $item->fill($patch);
            $item->save();
        }

        return $this->fresh($record);
    }

    public function deleteParameter(TfpAobGroundRecord $record, int $paramId): TfpAobGroundRecord
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah strukturnya.');
        }

        $deleted = $record->items()->where('id', $paramId)->delete();
        if ($deleted === 0) {
            throw new InvalidArgumentException('Parameter tidak ditemukan.');
        }

        return $this->fresh($record);
    }

    /**
     * Reorder parameters by passing the new id sequence top-to-bottom.
     * Items not in the list keep their existing sort_order — only the
     * supplied ids are renumbered 0..N-1.
     *
     * @param  array<int, int>  $orderedIds
     */
    public function reorderParameters(TfpAobGroundRecord $record, array $orderedIds): TfpAobGroundRecord
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah strukturnya.');
        }

        DB::transaction(function () use ($record, $orderedIds) {
            foreach ($orderedIds as $index => $id) {
                $record->items()->where('id', $id)->update(['sort_order' => $index]);
            }
        });

        return $this->fresh($record);
    }

    // ─── Structural edit (facilities) ──────────────────────────

    public function addFacility(TfpAobGroundRecord $record, array $data): TfpAobGroundRecord
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah strukturnya.');
        }

        $maxSort = (int) ($record->facilities()->max('sort_order') ?? -1);

        TfpAobGroundFacility::create([
            'aob_ground_record_id' => $record->id,
            'facility_name'        => trim((string) $data['facility_name']),
            'kondisi'              => null,
            'keterangan'           => null,
            'sort_order'           => $maxSort + 1,
        ]);

        return $this->fresh($record);
    }

    public function updateFacilityStructure(TfpAobGroundRecord $record, int $facilityId, array $data): TfpAobGroundRecord
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah strukturnya.');
        }

        /** @var TfpAobGroundFacility|null $facility */
        $facility = $record->facilities()->where('id', $facilityId)->first();
        if (!$facility) {
            throw new InvalidArgumentException('Fasilitas tidak ditemukan.');
        }

        if (array_key_exists('facility_name', $data)) {
            $facility->facility_name = trim((string) $data['facility_name']);
            $facility->save();
        }

        return $this->fresh($record);
    }

    public function deleteFacility(TfpAobGroundRecord $record, int $facilityId): TfpAobGroundRecord
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah strukturnya.');
        }

        $deleted = $record->facilities()->where('id', $facilityId)->delete();
        if ($deleted === 0) {
            throw new InvalidArgumentException('Fasilitas tidak ditemukan.');
        }

        return $this->fresh($record);
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function reorderFacilities(TfpAobGroundRecord $record, array $orderedIds): TfpAobGroundRecord
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah strukturnya.');
        }

        DB::transaction(function () use ($record, $orderedIds) {
            foreach ($orderedIds as $index => $id) {
                $record->facilities()->where('id', $id)->update(['sort_order' => $index]);
            }
        });

        return $this->fresh($record);
    }

    private function fresh(TfpAobGroundRecord $record): TfpAobGroundRecord
    {
        return $record->fresh([
            'technicians',
            'items',
            'facilities',
            'manager:id,name',
            'supervisor:id,name',
        ]);
    }

    /**
     * Batch save the structural edit made in Edit Mode:
     *   - columns_config: new panel/sub-column layout (replaces existing)
     *   - items[].is_disabled_map: per-item disabled cells (keyed by composite)
     *   - items[].merge_map:       per-item merged cells  (key → colspan int)
     *
     * Values are NOT touched here. After saving, cells whose keys are no longer
     * present in columns_config become orphaned in the `values` JSON; we strip
     * them so the next fetch returns only valid cells.
     *
     * @param array $columnsConfig  new columns_config array
     * @param array $itemPatches    [{id, is_disabled_map, merge_map}]
     */
    public function saveStructure(TfpAobGroundRecord $record, array $columnsConfig, array $itemPatches): TfpAobGroundRecord
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah strukturnya.');
        }

        $normalized = $this->normalizeColumnsConfig($columnsConfig);
        if (empty($normalized)) {
            throw new InvalidArgumentException('Minimal harus ada satu panel dengan satu sub-kolom.');
        }

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

            // Apply per-item patches and prune orphaned values
            foreach ($itemPatches as $patch) {
                if (empty($patch['id']) || !$existing->has($patch['id'])) continue;

                /** @var TfpAobGroundItem $item */
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

                // Prune orphan values whose keys are no longer in columns_config
                $values = is_array($item->values) ? $item->values : [];
                $prunedValues = array_intersect_key($values, array_flip($allowedKeys));
                if (count($prunedValues) !== count($values)) {
                    $item->values = empty($prunedValues) ? null : $prunedValues;
                }

                $item->save();
            }

            // Also prune items not in $itemPatches (only orphan-strip, no flag change)
            $patchedIds = array_filter(array_map(fn ($p) => $p['id'] ?? null, $itemPatches));
            foreach ($existing as $item) {
                if (in_array($item->id, $patchedIds, true)) continue;
                $values = is_array($item->values) ? $item->values : [];
                $prunedValues = array_intersect_key($values, array_flip($allowedKeys));
                if (count($prunedValues) !== count($values)) {
                    $item->values = empty($prunedValues) ? null : $prunedValues;
                    $item->save();
                }
            }

            return $this->fresh($record);
        });
    }

    /**
     * Normalize columns_config: ensure ids are slugged & unique, sub_columns
     * have keys & labels, and the array is re-indexed. Drops malformed entries.
     */
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

            $out[] = [
                'id'          => $id,
                'label'       => mb_substr($label, 0, 80),
                'sub_columns' => $cleanSubs,
            ];
            $seenIds[] = $id;
        }

        return $out;
    }

    /**
     * Slug a string into a stable key: lowercase, replace non-alnum with "_",
     * collapse repeats, trim leading/trailing underscores.
     */
    private function slug(string $raw): string
    {
        $s = mb_strtolower(trim($raw));
        $s = preg_replace('/[^a-z0-9]+/u', '_', $s) ?? '';
        $s = preg_replace('/_+/', '_', $s) ?? '';
        return trim($s, '_');
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
        TfpAobGroundRecord $record,
        string $base64,
        LocalUser $signer,
        ?int $technicianRowId,
    ): void {
        // Use role-based delegation: Manager/Supervisor/Technician can all sign technician slots
        \App\Services\SignatureAuthorizationService::authorize($signer, 'technician', null, null);

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

    public function deleteRecord(TfpAobGroundRecord $record): void
    {
        $record->delete();
    }
}
