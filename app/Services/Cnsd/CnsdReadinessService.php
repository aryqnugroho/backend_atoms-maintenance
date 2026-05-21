<?php

namespace App\Services\Cnsd;

use App\Exceptions\CnsdReadinessDuplicateException;
use App\Exceptions\SignerNotAuthorizedException;
use App\Models\Cnsd\CnsdReadinessItem;
use App\Models\Cnsd\CnsdReadinessRecord;
use App\Models\Cnsd\CnsdReadinessTechnician;
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
 * CnsdReadinessService — orchestrates Form EQ-1 (and any future CNSD readiness
 * forms) on top of the rostering DB and the signature trait.
 *
 * Mirrors the Work Order pattern:
 *   - Service owns the create/update/sign flows.
 *   - Personnel are resolved from atoms-rostering at create time
 *     (date + shift_type + facility = 'CNSD').
 *   - Signatures are immutable, name-matched, and never delegated.
 *   - The service NEVER writes to the rostering DB.
 */
class CnsdReadinessService
{
    public function __construct(
        protected LocalUserResolver $userResolver,
        protected RosteringIntegrationService $rosteringService,
    ) {}

    // ─── Read ──────────────────────────────────────────────────

    /**
     * List CNSD readiness records with optional filters and pagination.
     */
    public function listRecords(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = CnsdReadinessRecord::query()
            ->with([
                'technicians:id,readiness_record_id,technician_id,technician_name,technician_signature,sort_order',
                'manager:id,name',
                'supervisor:id,name',
            ])
            ->withCount('technicians');

        if (!empty($filters['form_type'])) {
            $query->byFormType($filters['form_type']);
        } else {
            // EQ-1 is the only supported form right now. Restrict by default
            // so future form_types do not leak into the EQ-1 list view.
            $query->byFormType('EQ-1');
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

        $sortBy = $filters['sort_by']  ?? 'date';
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

    public function findRecord(int $id): ?CnsdReadinessRecord
    {
        return CnsdReadinessRecord::query()
            ->with([
                'technicians',
                'items',
                'manager:id,name',
                'supervisor:id,name',
                'creator:id,name',
            ])
            ->find($id);
    }

    /**
     * Find an active record for the same (form_type, facility, date, shift_type).
     */
    public function findExistingRecord(string $formType, string $facility, string $date, string $shiftType): ?CnsdReadinessRecord
    {
        return CnsdReadinessRecord::query()
            ->where('form_type', $formType)
            ->where('facility', $facility)
            ->whereDate('date', $date)
            ->where('shift_type', $shiftType)
            ->first();
    }

    // ─── Create ────────────────────────────────────────────────

    /**
     * Create a new CNSD readiness record + auto-resolve personnel + seed items.
     *
     * @throws RuntimeException when no CNSD technicians are on duty.
     */
    public function createRecord(array $data, ?LocalUser $creator = null): CnsdReadinessRecord
    {
        $formType  = $data['form_type']  ?? 'EQ-1';
        $facility  = $data['facility']   ?? 'CNSD';
        $date      = $data['date'];
        $shiftType = $data['shift_type'];
        $location  = $data['location'] ?? 'CABANG SURABAYA';
        $room      = $data['room']     ?? 'Main Equipment Room';

        // Reject duplicate — controller will surface this as HTTP 409 along with
        // the existing record so the frontend can redirect the user.
        $existing = $this->findExistingRecord($formType, $facility, $date, $shiftType);
        if ($existing) {
            throw new CnsdReadinessDuplicateException($existing);
        }

        // Resolve roster personnel BEFORE wrapping in a transaction so that
        // a missing CNSD technician fails fast without committing partial state.
        $rosterContext = $this->resolveRosterContext($shiftType, $date);

        if (empty($rosterContext['technicians'])) {
            throw new RuntimeException(
                'Tidak ada teknisi CNSD yang bertugas pada tanggal '
                . $date . ' shift ' . $shiftType
                . '. Pastikan roster sudah dipublish dan terdapat personel CNS untuk shift ini.'
            );
        }

        return DB::transaction(function () use (
            $formType, $facility, $date, $shiftType, $location, $room, $creator, $rosterContext
        ) {
            $manager    = $rosterContext['manager'];
            $supervisor = $rosterContext['supervisor'];

            $record = CnsdReadinessRecord::create([
                'form_number'     => $this->generateFormNumber($formType, $facility, $date),
                'form_type'       => $formType,
                'facility'        => $facility,
                'date'            => $date,
                'shift_type'      => $shiftType,
                'location'        => $location,
                'room'            => $room,
                // Seed sections_meta from the template so per-record renames
                // and column-label edits survive without touching the template.
                'sections_meta'   => match ($formType) {
                    'EQ-1'  => CnsdEq1Template::sectionMeta(),
                    default => [],
                },
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
                CnsdReadinessTechnician::create([
                    'readiness_record_id' => $record->id,
                    'technician_id'       => $tech['local_id'],
                    'technician_name'     => $tech['name'],
                    'sort_order'          => $sort++,
                ]);
            }

            // Seed items from EQ-1 template (only EQ-1 is supported right now)
            $itemRows = match ($formType) {
                'EQ-1'  => CnsdEq1Template::buildItemRows($record->id),
                default => [],
            };

            if (!empty($itemRows)) {
                CnsdReadinessItem::insert($itemRows);
            }

            $record->refresh();
            return $record->load(['technicians', 'items', 'manager:id,name', 'supervisor:id,name']);
        });
    }

    /**
     * Resolve roster personnel for a given shift+date.
     *
     * Returns:
     *   - manager     : LocalUser|null (Manager Teknik on duty)
     *   - supervisor  : LocalUser|null (Supervisor CNSD on duty)
     *   - technicians : array<int, array{local_id:int|null,name:string,user_id:int}>
     *
     * Notes:
     *   - Only employees with employee_type = 'CNS' are accepted as technicians.
     *   - Supervisor CNSD = a CNS on shift with grade >= 13. They are *also*
     *     listed as a technician on the EQ-1 form because they sign individually.
     */
    private function resolveRosterContext(string $shiftType, string $date): array
    {
        $manager    = null;
        $supervisor = null;
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
            Log::warning('CnsdReadinessService: roster lookup failed', [
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
     * Generate a sequential form number in the same family as Work Order numbers.
     * Format: {FORM_TYPE}-CNSD-YYYYMMDD-SEQ
     * Example: EQ-1-CNSD-20260517-001
     */
    public function generateFormNumber(string $formType, string $facility, string $date): string
    {
        $dateCompact = str_replace('-', '', $date);
        $prefix = "{$formType}-{$facility}-{$dateCompact}";

        $count = CnsdReadinessRecord::withTrashed()
            ->where('form_number', 'LIKE', $prefix . '%')
            ->count();

        return $prefix . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    // ─── Update ────────────────────────────────────────────────

    /**
     * Update item values on an existing record. Personnel, signatures, dates,
     * and form numbers are NOT touched here.
     *
     * @param array<int, array{
     *   id:int,
     *   status_peralatan?:string|null,
     *   kondisi_operasional_1?:string|null,
     *   kondisi_operasional_2?:string|null,
     *   keterangan?:string|null
     * }> $items
     */
    public function updateItems(CnsdReadinessRecord $record, array $items): CnsdReadinessRecord
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');
        }

        return DB::transaction(function () use ($record, $items) {
            $existing = $record->items()->get()->keyBy('id');

            foreach ($items as $payload) {
                if (empty($payload['id']) || !$existing->has($payload['id'])) {
                    continue; // ignore unknown item IDs (cannot create new items via update)
                }

                /** @var CnsdReadinessItem $item */
                $item = $existing->get($payload['id']);
                $item->fill(array_intersect_key($payload, array_flip([
                    'status_peralatan',
                    'kondisi_operasional_1',
                    'kondisi_operasional_2',
                    'keterangan',
                ])));
                $item->save();
            }

            return $record->fresh(['technicians', 'items', 'manager:id,name', 'supervisor:id,name']);
        });
    }

    // ─── Structural edits (Manager / Supervisor only) ──────────

    /**
     * Add a new item row to the given section. The section must already exist
     * in $record->sections_meta. New rows append to the end of the section.
     *
     * Returns the created CnsdReadinessItem.
     */
    public function addItem(
        CnsdReadinessRecord $record,
        string $sectionName,
        array $payload,
    ): CnsdReadinessItem {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');
        }

        $sectionsMeta = is_array($record->sections_meta) ? $record->sections_meta : [];
        $sectionExists = collect($sectionsMeta)->contains(fn ($s) => ($s['name'] ?? null) === $sectionName);
        if (!$sectionExists) {
            throw new InvalidArgumentException('Section tidak ditemukan: ' . $sectionName);
        }

        // Compute new sort_order — last item in section + 1, or after all items
        // if section is empty.
        $maxSortInSection = (int) $record->items()
            ->where('section_name', $sectionName)
            ->max('sort_order');
        $maxSortOverall = (int) $record->items()->max('sort_order');
        $newSort = max($maxSortInSection, $maxSortOverall) + 1;

        return CnsdReadinessItem::create([
            'readiness_record_id'    => $record->id,
            'section_name'           => $sectionName,
            'item_number'            => $payload['item_number']          ?? null,
            'equipment_name'         => $payload['equipment_name'],
            'sub_equipment_name'     => $payload['sub_equipment_name']   ?? null,
            'status_peralatan'       => $payload['status_peralatan']     ?? null,
            'kondisi_operasional_1'  => $payload['kondisi_operasional_1'] ?? null,
            'kondisi_operasional_2'  => $payload['kondisi_operasional_2'] ?? null,
            'keterangan'             => $payload['keterangan']           ?? null,
            'sort_order'             => $newSort,
        ]);
    }

    /**
     * Update structural fields of an item (equipment_name, sub_equipment_name,
     * item_number). Value fields (status, kondisi, keterangan) are NOT touched
     * here — use updateItems() for that.
     */
    public function updateItemStructure(
        CnsdReadinessRecord $record,
        int $itemId,
        array $payload,
    ): CnsdReadinessItem {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');
        }

        /** @var CnsdReadinessItem|null $item */
        $item = $record->items()->where('id', $itemId)->first();
        if (!$item) {
            throw new InvalidArgumentException('Item tidak ditemukan: ' . $itemId);
        }

        $item->fill(array_intersect_key($payload, array_flip([
            'item_number',
            'equipment_name',
            'sub_equipment_name',
        ])));
        $item->save();
        return $item;
    }

    /**
     * Delete an item row.
     */
    public function deleteItem(CnsdReadinessRecord $record, int $itemId): void
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');
        }

        $record->items()->where('id', $itemId)->delete();
    }

    /**
     * Rename a section heading. Updates both `sections_meta` JSON on the record
     * and every item.section_name that matches the old name. Column labels can
     * also be updated in the same call.
     */
    public function renameSection(
        CnsdReadinessRecord $record,
        string $oldName,
        array $payload,
    ): CnsdReadinessRecord {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');
        }

        $newName = isset($payload['name']) ? trim((string) $payload['name']) : $oldName;
        if ($newName === '') {
            throw new InvalidArgumentException('Nama section tidak boleh kosong.');
        }

        $sectionsMeta = is_array($record->sections_meta) ? $record->sections_meta : [];
        $found = false;
        foreach ($sectionsMeta as $idx => $section) {
            if (($section['name'] ?? null) === $oldName) {
                $sectionsMeta[$idx]['name'] = $newName;
                if (array_key_exists('columns_label_1', $payload)) {
                    $sectionsMeta[$idx]['columns_label_1'] = $payload['columns_label_1'];
                }
                if (array_key_exists('columns_label_2', $payload)) {
                    $sectionsMeta[$idx]['columns_label_2'] = $payload['columns_label_2'];
                }
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new InvalidArgumentException('Section tidak ditemukan: ' . $oldName);
        }

        // Block name collision with another section.
        if ($newName !== $oldName) {
            $collision = collect($sectionsMeta)
                ->filter(fn ($s, $i) => ($s['name'] ?? null) === $newName)
                ->count();
            if ($collision > 1) {
                throw new InvalidArgumentException('Nama section "' . $newName . '" sudah dipakai oleh section lain.');
            }
        }

        return DB::transaction(function () use ($record, $oldName, $newName, $sectionsMeta) {
            $record->sections_meta = $sectionsMeta;
            $record->save();

            if ($newName !== $oldName) {
                $record->items()
                    ->where('section_name', $oldName)
                    ->update(['section_name' => $newName]);
            }

            return $record->fresh(['technicians', 'items', 'manager:id,name', 'supervisor:id,name']);
        });
    }

    // ─── Sign ──────────────────────────────────────────────────

    /**
     * Sign the readiness record on behalf of a role.
     *
     * Roles:
     *   - 'manager'    → manager_signature column
     *   - 'supervisor' → supervisor_signature column
     *   - 'technician' → cnsd_readiness_technicians row matching $technicianRowId
     *
     * Signatures are immutable. Wrong-name attempts throw
     * SignerNotAuthorizedException (mapped to HTTP 403 by the controller).
     */
    public function signRecord(
        CnsdReadinessRecord $record,
        string $role,
        string $base64Signature,
        LocalUser $signer,
        ?int $technicianRowId = null,
    ): CnsdReadinessRecord {
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

    /**
     * Sign manager_* or supervisor_* columns on the record.
     */
    private function signRecordRole(
        CnsdReadinessRecord $record,
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

    /**
     * Sign one specific cnsd_readiness_technicians row.
     */
    private function signTechnicianRow(
        CnsdReadinessRecord $record,
        string $base64,
        LocalUser $signer,
        ?int $technicianRowId,
    ): void {
        // Use role-based delegation: Manager/Supervisor/Technician can all sign technician slots
        \App\Services\SignatureAuthorizationService::authorize($signer, 'technician', null, null);

        // Resolve technician row — prefer explicit row id, fall back to ID match,
        // then to name match.
        /** @var CnsdReadinessTechnician|null $row */
        $row = null;
        if ($technicianRowId) {
            $row = $record->technicians()->where('id', $technicianRowId)->first();
        }
        if (!$row && $signer->id) {
            $row = $record->technicians()->where('technician_id', $signer->id)->first();
        }
        if (!$row) {
            // Last-resort: tolerant name match
            $row = $record->technicians()
                ->get()
                ->first(fn (CnsdReadinessTechnician $t) => WorkOrderService::namesMatch($t->technician_name, $signer->name));
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

    /**
     * Re-export the trait's base64 PNG validator. Mirrors HasSignature::validateBase64PngSignature
     * but accessible to the per-row technician sign path.
     */
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

    public function deleteRecord(CnsdReadinessRecord $record): void
    {
        $record->delete();
    }
}
