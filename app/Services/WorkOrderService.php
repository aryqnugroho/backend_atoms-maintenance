<?php

namespace App\Services;

use App\Exceptions\SignerNotAuthorizedException;
use App\Models\LocalUser;
use App\Models\WorkOrder\WorkOrder;
use App\Models\WorkOrder\WorkOrderOutput;
use App\Models\WorkOrder\WorkOrderPersonnel;
use App\Services\LocalUserResolver;
use InvalidArgumentException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WorkOrderService
{
    public function __construct(
        protected LocalUserResolver $userResolver
    ) {}

    /**
     * List work orders with filtering, sorting, and pagination.
     *
     * Visibility rules:
     *   - Admin, Manager Teknik, General Manager, Supervisor: see everything.
     *   - Teknisi CNSD / Teknisi TFP: see every WO in their division
     *     (CNSD or TFP) regardless of whether they're assigned. Updates/feedback
     *     are still policy-gated (only their own assigned WOs are editable).
     */
    public function listWorkOrders(array $filters, LocalUser $user, int $perPage = 15): LengthAwarePaginator
    {
        $query = WorkOrder::with([
            'personnel.user:id,name,role',
            'outputs',
            'manager:id,name',
            'supervisor:id,name',
            'creator:id,name,role',
        ]);

        // Teknisi: scoped to their division so they see the team's WOs, not
        // only their own. Detail-page edit/feedback remains assignment-gated
        // by WorkOrderPolicy::update.
        if ($user->isTeknisi()) {
            $division = $user->getRoleDivision();
            if ($division) {
                $query->where('division', $division);
            }
        }

        // Apply filters
        if (!empty($filters['division'])) {
            $query->byDivision($filters['division']);
        }

        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (!empty($filters['shift_date'])) {
            $query->byShiftDate($filters['shift_date']);
        }

        if (!empty($filters['shift_type'])) {
            $query->byShiftType($filters['shift_type']);
        }

        if (!empty($filters['wo_type'])) {
            $query->byType($filters['wo_type']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $searchPattern = '%' . $search . '%';
                $q->where('wo_number', 'ILIKE', $searchPattern)
                  ->orWhere('description', 'ILIKE', $searchPattern);
            });
        }

        // Year filter — extract year from shift_date
        if (!empty($filters['year'])) {
            $query->whereYear('shift_date', (int) $filters['year']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $allowedSorts = ['created_at', 'shift_date', 'wo_number', 'status', 'division'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $result = $query->paginate($perPage);

        // Recalculate stale statuses on read (shift ended but still marked ongoing)
        $this->recalculateStaleStatuses($result->items());

        return $result;
    }

    /**
     * Get a single work order with all relationships.
     */
    public function getWorkOrder(int $id): ?WorkOrder
    {
        $wo = WorkOrder::with([
            'personnel.user:id,name,role',
            'outputs',
            'manager:id,name',
            'supervisor:id,name',
            'assignedTechnician:id,name',
            'creator:id,name,role',
            'mtSigner:id,name',
            'supervisorSigner:id,name',
            'technicianSigner:id,name',
        ])->find($id);

        if ($wo) {
            $this->recalculateStaleStatuses([$wo]);
        }

        return $wo;
    }

    /**
     * Create a new work order with personnel and outputs.
     */
    public function createWorkOrder(array $data, LocalUser $creator): WorkOrder
    {
        return DB::transaction(function () use ($data, $creator) {
            $isGmDirective = ($data['wo_type'] ?? null) === 'gm_directive';

            // ── Step 1: translate every user-id field in the *incoming* payload
            //           from rostering_user_id to local_users.id. Frontend always
            //           sends rostering ids; backend stores local_users.id.
            $data = $this->mapPayloadRosteringIdsToLocal($data);

            // ── Step 1.5: enforce one-per-shift-per-division for shift WOs,
            //             and one-per-technician for personal WOs.
            //             Must run AFTER ID mapping so assigned_technician_id is local.
            //             GM directives are not deduped (a GM may issue multiple).
            if (!$isGmDirective) {
                $this->assertNoDuplicate($data);
            }

            // ── Step 2 & 3: auto-fill manager/supervisor + shift personnel from
            //              rostering. SKIPPED for GM directives — the GM picks
            //              MT (required) and Supervisor (optional) explicitly,
            //              and no technicians are assigned.
            if (!$isGmDirective) {
                $data = $this->resolveShiftPersonnelFromRostering($data);
                $data = $this->autoFillShiftPersonnelFromRostering($data);
            }

            // Generate WO number
            $woNumber = $this->generateWoNumber($data['division']);

            // Snapshot manager and supervisor names
            $manager = !empty($data['manager_id']) ? LocalUser::find($data['manager_id']) : null;
            $supervisor = !empty($data['supervisor_id']) ? LocalUser::find($data['supervisor_id']) : null;
            $hasSupervisor = array_key_exists('has_supervisor', $data)
                ? (bool) $data['has_supervisor']
                : $supervisor !== null;

            // GM directives never carry a technician slot.
            $selectedTechnician = $isGmDirective ? null : $this->selectTechnicianForShift($data);

            $workOrder = WorkOrder::create([
                'wo_number' => $woNumber,
                'wo_type' => $data['wo_type'],
                'division' => $data['division'],
                'shift_type' => $data['shift_type'],
                'shift_date' => $data['shift_date'],
                'shift_id' => $data['shift_id'] ?? null,
                'description' => $data['description'],
                'status' => 'ongoing',
                'manager_id' => $data['manager_id'] ?? null,
                'supervisor_id' => $hasSupervisor ? ($data['supervisor_id'] ?? null) : null,
                'assigned_technician_id' => $selectedTechnician?->id,
                'has_supervisor' => $hasSupervisor,
                'manager_name_snapshot' => $manager ? $manager->name : '',
                'supervisor_name_snapshot' => $hasSupervisor && $supervisor ? $supervisor->name : null,
                'mt_name' => $manager ? $manager->name : null,
                'supervisor_name' => $hasSupervisor && $supervisor ? $supervisor->name : null,
                'technician_name' => $selectedTechnician?->name,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'completion_status' => $data['completion_status'] ?? null,
                'notes_kendala' => $data['notes_kendala'] ?? null,
                'notes_usulan' => $data['notes_usulan'] ?? null,
                'notes_pemberi_tugas' => $data['notes_pemberi_tugas'] ?? null,
                'created_by' => $creator->id,
            ]);

            // Sync personnel (GM directives have none).
            if (!$isGmDirective && !empty($data['personnel'])) {
                $this->syncPersonnel($workOrder, $data['personnel']);
            }

            // Sync output types (GM directives have none).
            if (!$isGmDirective && !empty($data['output_types'])) {
                $this->syncOutputs($workOrder, $data['output_types'], $data['output_other'] ?? null);
            }

            // Reload relationships
            $workOrder->load([
                'personnel.user:id,name,role',
                'outputs',
                'manager:id,name',
                'supervisor:id,name',
                'assignedTechnician:id,name',
                'creator:id,name,role',
                'mtSigner:id,name',
                'supervisorSigner:id,name',
                'technicianSigner:id,name',
            ]);

            return $workOrder;
        });
    }

    /**
     * Auto-fill personnel array for shift WOs when the frontend didn't send any.
     *
     * For `wo_type = 'shift'`, the personnel list is *deterministic* given a
     * shift_date + shift_type + division (= all CNS or all Support technicians
     * working that shift). We treat the rostering DB as the source of truth and
     * recover gracefully when the frontend omitted the array.
     *
     * For `wo_type = 'personal'` we don't synthesize anything — that path
     * requires the user to explicitly select a single technician.
     */
    private function autoFillShiftPersonnelFromRostering(array $data): array
    {
        if (($data['wo_type'] ?? null) !== 'shift') {
            return $data;
        }
        if (!empty($data['personnel'])) {
            return $data;
        }
        if (empty($data['shift_type']) || empty($data['shift_date']) || empty($data['division'])) {
            return $data;
        }

        try {
            $rosteringService = app(\App\Services\RosteringIntegrationService::class);
            $shiftPersonnel = $rosteringService->getShiftPersonnel($data['shift_type'], $data['shift_date']);

            $employeeType = $data['division'] === 'CNSD' ? 'CNS' : 'Support';
            $filtered = $shiftPersonnel->filter(fn ($p) => $p->employee_type === $employeeType)->values();

            if ($filtered->isEmpty()) {
                return $data;
            }

            $personnel = [];
            foreach ($filtered as $i => $p) {
                $local = $this->userResolver->ensureLocalUser((int) $p->user_id);
                if ($local) {
                    $personnel[] = [
                        'user_id'    => $local->id,
                        'role_label' => 'Teknisi ' . ($i + 1),
                    ];
                }
            }

            if (!empty($personnel)) {
                $data['personnel'] = $personnel;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info(
                'WorkOrderService: shift personnel auto-fill skipped',
                ['error' => $e->getMessage()]
            );
        }

        return $data;
    }

    /**
     * Translate every user-id field in the payload from rostering_user_id to
     * local_users.id, creating local_users rows on-the-fly via LocalUserResolver.
     *
     * Affected fields: manager_id, supervisor_id, assigned_technician_id,
     * personnel[].user_id.
     *
     * The frontend identifies people by their source-of-truth rostering_user_id
     * (the IDs returned by GET /api/v1/personnel/shift-today). Maintenance stores
     * foreign keys against local_users.id, so we map at the boundary.
     */
    private function mapPayloadRosteringIdsToLocal(array $data): array
    {
        // Collect all rostering_user_ids referenced in the payload
        $allIds = [];
        foreach (['manager_id', 'supervisor_id', 'assigned_technician_id'] as $field) {
            if (!empty($data[$field])) {
                $allIds[] = (int) $data[$field];
            }
        }
        if (!empty($data['personnel']) && is_array($data['personnel'])) {
            foreach ($data['personnel'] as $person) {
                if (!empty($person['user_id'])) {
                    $allIds[] = (int) $person['user_id'];
                }
            }
        }

        if (empty($allIds)) {
            return $data;
        }

        $map = $this->userResolver->resolveLocalIds($allIds);

        // Translate each scalar id field — fall back to the original value if the
        // ID was already a local_users.id (e.g. the SSO-cached creator).
        foreach (['manager_id', 'supervisor_id', 'assigned_technician_id'] as $field) {
            if (!empty($data[$field])) {
                $rid = (int) $data[$field];
                if (isset($map[$rid])) {
                    $data[$field] = $map[$rid];
                } elseif (!LocalUser::whereKey($rid)->exists()) {
                    // Unresolvable — drop to avoid FK violation
                    $data[$field] = null;
                }
            }
        }

        if (!empty($data['personnel']) && is_array($data['personnel'])) {
            $data['personnel'] = array_values(array_filter(array_map(function ($person) use ($map) {
                $rid = (int) ($person['user_id'] ?? 0);
                if (!$rid) return null;
                if (isset($map[$rid])) {
                    $person['user_id'] = $map[$rid];
                    return $person;
                }
                if (LocalUser::whereKey($rid)->exists()) {
                    return $person; // already a local id
                }
                return null;
            }, $data['personnel'])));
        }

        return $data;
    }

    /**
     * Update an existing work order.
     */
    public function updateWorkOrder(WorkOrder $workOrder, array $data): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $data) {
            $oldStatus = $workOrder->status;

            // Update main fields
            $updateFields = [];
            $allowedFields = [
                'description',
                'start_time', 'end_time', 'completion_status',
                'notes_kendala', 'notes_usulan', 'notes_pemberi_tugas',
            ];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateFields[$field] = $data[$field];
                }
            }

            if (!empty($updateFields)) {
                $workOrder->update($updateFields);
            }

            // Sync output types if provided
            if (isset($data['output_types'])) {
                $this->syncOutputs($workOrder, $data['output_types'], $data['output_other'] ?? null);
            }

            $workOrder->status = $workOrder->recalculateStatus();
            if ($workOrder->status === 'completed' && $oldStatus !== 'completed') {
                $workOrder->closed_at = now();
            }
            $workOrder->save();

            // ── Auto-add logbook note when WO transitions to on_hold ──────────
            if ($workOrder->status === 'on_hold' && $oldStatus !== 'on_hold') {
                $this->addLogbookNoteForOnHold($workOrder);
            }

            // Reload relationships
            $workOrder->load([
                'personnel.user:id,name,role',
                'outputs',
                'manager:id,name',
                'supervisor:id,name',
                'assignedTechnician:id,name',
                'creator:id,name,role',
                'mtSigner:id,name',
                'supervisorSigner:id,name',
                'technicianSigner:id,name',
            ]);

            return $workOrder;
        });
    }

    /**
     * When a Work Order transitions to on_hold, auto-add a note to the
     * logbook for that date+shift. Uses the correct logbook based on division:
     * - CNSD → LogbookCnsd
     * - TFP  → LogbookTfp
     *
     * Time is set to the shift end time (not current time) for chronological order.
     * If no logbook exists for that date, one is auto-created.
     */
    private function addLogbookNoteForOnHold(WorkOrder $workOrder): void
    {
        try {
            $date = $workOrder->shift_date->format('Y-m-d');
            $shift = $workOrder->shift_type;
            $division = $workOrder->division;

            // Determine shift end time for the note timestamp
            $shiftEndTimes = [
                'pagi'  => '13:00',
                'siang' => '19:00',
                'malam' => '07:00',
            ];
            $noteTime = $shiftEndTimes[$shift] ?? now()->format('H:i');

            // Build the note activity text
            $completionLabel = match ($workOrder->completion_status) {
                'belum_selesai_dilanjut' => 'Belum Selesai (Dilanjutkan)',
                'tidak_bisa' => 'Tidak Dapat Diselesaikan',
                default => 'On Hold',
            };

            $activity = "[WO {$workOrder->wo_number}] {$completionLabel}";
            if ($workOrder->notes_kendala) {
                $activity .= " — Kendala: {$workOrder->notes_kendala}";
            }

            if ($division === 'CNSD') {
                $this->addNoteToCnsdLogbook($date, $shift, $noteTime, $activity);
            } else {
                $this->addNoteToTfpLogbook($date, $shift, $noteTime, $activity);
            }
        } catch (\Throwable $e) {
            // Non-critical — log and continue. WO update should not fail
            // because logbook integration had an issue.
            \Illuminate\Support\Facades\Log::info(
                'WorkOrderService: logbook note for on_hold skipped',
                ['wo_id' => $workOrder->id, 'error' => $e->getMessage()]
            );
        }
    }

    private function addNoteToCnsdLogbook(string $date, string $shift, string $time, string $activity): void
    {
        $logbookService = app(\App\Services\Logbook\LogbookCnsdService::class);
        $logbook = \App\Models\Logbook\LogbookCnsd::whereDate('date', $date)->first();

        if (!$logbook) {
            $logbook = $logbookService->createLogbook($date);
        }

        $logbookService->addNote($logbook, $shift, $time, $activity, null);
    }

    private function addNoteToTfpLogbook(string $date, string $shift, string $time, string $activity): void
    {
        $logbookService = app(\App\Services\Logbook\LogbookTfpService::class);
        $logbook = \App\Models\Logbook\LogbookTfp::whereDate('date', $date)->first();

        if (!$logbook) {
            $logbook = $logbookService->createLogbook($date);
        }

        $logbookService->addNote($logbook, $shift, $time, $activity, null);
    }

    /**
     * Save an immutable signature and recalculate status.
     */
    public function signWorkOrder(WorkOrder $workOrder, string $role, string $signature, LocalUser $signer): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $role, $signature, $signer) {
            $role = strtolower(trim($role));

            if ($workOrder->status === 'completed') {
                throw new RuntimeException('Completed work orders cannot be signed.');
            }

            if (!in_array($role, $workOrder->getRequiredSignatures(), true)) {
                throw new InvalidArgumentException('This signature role is not required for this work order.');
            }

            $this->assertSignerCanSignRole($workOrder, $role, $signer);

            $oldStatus = $workOrder->status;
            $workOrder->saveSignature($role, $signature, $signer->id);
            $workOrder->refresh();

            if ($workOrder->status === 'completed' && $oldStatus !== 'completed') {
                $workOrder->closed_at = now();
                $workOrder->save();
            }

            return $this->getWorkOrder($workOrder->id);
        });
    }

    /**
     * Soft-delete a work order.
     */
    public function deleteWorkOrder(WorkOrder $workOrder): bool
    {
        return $workOrder->delete();
    }

    /**
     * Recalculate status for work orders that are still 'ongoing' but whose
     * shift has already ended. Persists the change and triggers logbook note
     * if transitioning to on_hold.
     *
     * Called lazily on read (list/detail) so status stays accurate without
     * needing a cron job.
     *
     * @param  iterable<WorkOrder>  $workOrders
     */
    private function recalculateStaleStatuses(iterable $workOrders): void
    {
        foreach ($workOrders as $wo) {
            if ($wo->status !== 'ongoing') {
                continue;
            }

            $newStatus = $wo->recalculateStatus();
            if ($newStatus !== 'ongoing' && $newStatus !== $wo->status) {
                $oldStatus = $wo->status;
                $wo->status = $newStatus;
                $wo->save();

                // Trigger logbook note on transition to on_hold
                if ($newStatus === 'on_hold') {
                    $this->addLogbookNoteForOnHold($wo);
                }
            }
        }
    }

    /**
     * Enforce work order creation limits per shift:
     *
     * - WO Shift: max 1 per shift_date + shift_type + division.
     *   (CNSD gets 1 shift WO, TFP gets 1 shift WO per shift)
     * - WO Personal: allowed multiple, but only 1 per assigned_technician_id
     *   per shift_date + shift_type. (different technicians = different WOs OK)
     *
     * @throws \RuntimeException when a duplicate would be created
     */
    private function assertNoDuplicate(array $data): void
    {
        $woType    = $data['wo_type'] ?? 'shift';
        $shiftDate = $data['shift_date'] ?? null;
        $shiftType = $data['shift_type'] ?? null;
        $division  = $data['division'] ?? null;

        if (!$shiftDate || !$shiftType || !$division) {
            return; // Can't check without these fields
        }

        if ($woType === 'shift') {
            // Only 1 shift WO per date+shift+division
            $exists = WorkOrder::where('wo_type', 'shift')
                ->where('shift_date', $shiftDate)
                ->where('shift_type', $shiftType)
                ->where('division', $division)
                ->exists();

            if ($exists) {
                throw new RuntimeException(
                    "Work Order Shift untuk divisi {$division} pada shift " .
                    strtoupper($shiftType) . " tanggal {$shiftDate} sudah ada. " .
                    "Hanya boleh 1 WO Shift per divisi per shift."
                );
            }
        } elseif ($woType === 'personal') {
            // Only 1 personal WO per technician per date+shift
            $assignedTechnicianId = $data['assigned_technician_id'] ?? null;

            if ($assignedTechnicianId) {
                $exists = WorkOrder::where('wo_type', 'personal')
                    ->where('shift_date', $shiftDate)
                    ->where('shift_type', $shiftType)
                    ->where('assigned_technician_id', $assignedTechnicianId)
                    ->exists();

                if ($exists) {
                    $techName = LocalUser::find($assignedTechnicianId)?->name ?? 'Teknisi';
                    throw new RuntimeException(
                        "Work Order Personal untuk {$techName} pada shift " .
                        strtoupper($shiftType) . " tanggal {$shiftDate} sudah ada. " .
                        "Pilih teknisi yang berbeda."
                    );
                }
            }
        }
    }

    /**
     * Generate a sequential work order number.
     *
     * Format: WO-{DIVISI}-{YYYYMMDD}-{SEQ}
     * Example: WO-CNSD-20260516-001
     *
     * This format is sortable (YYYYMMDD sorts chronologically as a string),
     * compact, and consistent with ISO date conventions.
     *
     * Data existing dengan format lama (WO-{DIV}-{DD}-{MM}-{YYYY}-{SEQ})
     * tetap dapat ditampilkan dan dicari — search di backend menggunakan ILIKE
     * sehingga kedua format bisa ditemukan.
     */
    public function generateWoNumber(string $division): string
    {
        $today = now();
        $dateStr = $today->format('Ymd');      // e.g. 20260516
        $prefix  = 'WO-' . $division . '-' . $dateStr;
        $likePrefix = $prefix . '%';

        $count = WorkOrder::withTrashed()
            ->where('wo_number', 'LIKE', $likePrefix)
            ->count();

        $seq = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        return $prefix . '-' . $seq;
    }

    /**
     * Attempt to auto-resolve manager_id and supervisor_id from atoms-rostering
     * when they are not explicitly provided in the Work Order creation request.
     *
     * Strategy:
     * - If manager_id is missing: look up the MT on duty for this shift/date in rostering.
     *   If found and a matching local_user exists (by rostering_user_id), use it.
     * - If supervisor_id is missing: look up the supervisor-level CNS for this shift/date.
     *   If found and a matching local_user exists, use it. Sets has_supervisor accordingly.
     *
     * Falls back gracefully — if rostering has no published roster or local_users cache
     * doesn't have the rostering user, the original $data is returned unchanged.
     *
     * @param  array  $data  Validated Work Order creation data
     * @return array  $data with manager_id / supervisor_id / has_supervisor potentially filled
     */
    private function resolveShiftPersonnelFromRostering(array $data): array
    {
        // Only attempt resolution if shift_type and shift_date are present
        if (empty($data['shift_type']) || empty($data['shift_date'])) {
            return $data;
        }

        try {
            $rosteringService = app(\App\Services\RosteringIntegrationService::class);
            $shiftType = $data['shift_type'];
            $shiftDate = $data['shift_date'];
            $division  = $data['division'] ?? null;

            // ── Auto-resolve Manager Teknik ──────────────────────────────────────
            if (empty($data['manager_id'])) {
                $rosteringManager = $rosteringService->getShiftManager($shiftType, $shiftDate);
                if ($rosteringManager) {
                    $localManager = $this->userResolver->ensureLocalUser((int) $rosteringManager->user_id);
                    if ($localManager && $localManager->is_active) {
                        $data['manager_id'] = $localManager->id;
                    }
                }
            }

            // ── Auto-resolve Supervisor (per-division) ───────────────────────────
            $supervisorExplicitlyDisabled = array_key_exists('has_supervisor', $data)
                && $data['has_supervisor'] === false;

            if (!$supervisorExplicitlyDisabled && empty($data['supervisor_id'])) {
                // Pick supervisor matching the WO's division when known.
                $rosteringSupervisor = null;
                if ($division === 'CNSD') {
                    $rosteringSupervisor = $rosteringService->getShiftSupervisorByDivision($shiftType, $shiftDate, 'CNS');
                } elseif ($division === 'TFP') {
                    $rosteringSupervisor = $rosteringService->getShiftSupervisorByDivision($shiftType, $shiftDate, 'Support');
                } else {
                    $rosteringSupervisor = $rosteringService->getShiftSupervisor($shiftType, $shiftDate);
                }

                if ($rosteringSupervisor) {
                    $localSupervisor = $this->userResolver->ensureLocalUser((int) $rosteringSupervisor->user_id);
                    if ($localSupervisor && $localSupervisor->is_active) {
                        $data['supervisor_id'] = $localSupervisor->id;
                        if (!array_key_exists('has_supervisor', $data)) {
                            $data['has_supervisor'] = true;
                        }
                    }
                } else {
                    if (!array_key_exists('has_supervisor', $data)) {
                        $data['has_supervisor'] = false;
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::info(
                'WorkOrderService: rostering auto-resolve skipped (rostering unavailable)',
                ['error' => $e->getMessage()]
            );
        }

        return $data;
    }

    /**
     * Select the technician snapshot for a new work order using shift round-robin.
     */
    private function selectTechnicianForShift(array $data): ?LocalUser
    {
        $technicianIds = collect($data['personnel'] ?? [])
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        // Pick from technician/supervisor roles in division (supervisor still does
        // technical work). If none of the listed users have an explicit "Teknisi"
        // role yet (lazy-created from rostering with grade-based role), fall back
        // to any active user in the personnel list.
        $division = $data['division'] ?? null;
        $teknisiRole = $division === 'CNSD' ? 'Teknisi CNSD' : ($division === 'TFP' ? 'Teknisi TFP' : null);

        $query = LocalUser::whereIn('id', $technicianIds)
            ->where('is_active', true)
            ->orderBy('id');

        $technicians = $teknisiRole
            ? (clone $query)->where('role', $teknisiRole)->get()
            : collect();

        if ($technicians->isEmpty()) {
            // Fallback: any active user listed as personnel
            $technicians = $query->get();
        }

        if ($technicians->isEmpty()) {
            return null;
        }

        $countQuery = WorkOrder::query()
            ->where('division', $data['division'])
            ->where('shift_date', $data['shift_date'])
            ->where('shift_type', $data['shift_type']);

        if (!empty($data['shift_id'])) {
            $countQuery->where('shift_id', $data['shift_id']);
        }

        $recordCount = $countQuery->count();
        $selectedIndex = $recordCount % $technicians->count();

        return $technicians[$selectedIndex];
    }

    /**
     * Authorize a signer for a given role.
     *
     * Authorization is name-based, not just role-based: the authenticated user's
     * `name` must match the cached signer name on the Work Order (mt_name /
     * supervisor_name / technician_name) using a tolerant comparison
     * (trim + collapse spaces + case-insensitive).
     *
     * Falls back to role-only check ONLY if the cached signer name is missing
     * (e.g. legacy WO without snapshot) — in that case the user must at least
     * hold the matching role.
     *
     * @throws RuntimeException when the user is not allowed to sign for this role
     */
    private function assertSignerCanSignRole(WorkOrder $workOrder, string $role, LocalUser $signer): void
    {
        // Use centralized role-based delegation authorization
        $slotType = \App\Services\SignatureAuthorizationService::slotType($role);

        // Resolve target ID and name for the slot
        $targetId = match ($role) {
            'mt'         => $workOrder->manager_id,
            'supervisor' => $workOrder->supervisor_id,
            'technician' => $workOrder->assigned_technician_id,
            default      => null,
        };
        $targetName = match ($role) {
            'mt'         => $workOrder->mt_name ?: $workOrder->manager_name_snapshot,
            'supervisor' => $workOrder->supervisor_name ?: $workOrder->supervisor_name_snapshot,
            'technician' => $workOrder->technician_name,
            default      => null,
        };

        // For technician in shift WO: if signer is in personnel list, allow
        if ($role === 'technician' && $slotType === 'technician') {
            $isInPersonnel = $workOrder->personnel()->where('user_id', $signer->id)->exists();
            if ($isInPersonnel) {
                return; // Personnel member can sign technician slot
            }
        }

        \App\Services\SignatureAuthorizationService::authorize(
            $signer,
            $slotType,
            $targetId ? (int) $targetId : null,
            $targetName,
        );
    }

    /**
     * Tolerant name comparison: trim, collapse interior whitespace, case-insensitive.
     */
    public static function namesMatch(?string $a, ?string $b): bool
    {
        $normalize = static function (?string $s): string {
            $s = (string) $s;
            $s = trim($s);
            $s = preg_replace('/\s+/u', ' ', $s) ?? '';
            return mb_strtolower($s, 'UTF-8');
        };
        $na = $normalize($a);
        $nb = $normalize($b);
        return $na !== '' && $na === $nb;
    }

    /**
     * Exclude supervisor and manager from the technicians list.
     *
     * Rule: if a person is already assigned as Supervisor or Manager Teknik on
     * a form, they must NOT appear again in the Pelaksana Teknisi list.
     *
     * Matching priority:
     *   1. user_id comparison (most reliable — same integer from rostering)
     *   2. Tolerant name match fallback (trim + collapse + case-insensitive)
     *
     * @param  array<int, array{local_id:int|null, name:string, user_id:int}>  $technicians
     * @param  int|null  $supervisorUserId   rostering user_id of the supervisor (null if none)
     * @param  string|null  $supervisorName  cached name of the supervisor (null if none)
     * @param  int|null  $managerUserId      rostering user_id of the manager (null if none)
     * @param  string|null  $managerName     cached name of the manager (null if none)
     * @return array  filtered technicians list
     */
    public static function excludeSignerRoles(
        array $technicians,
        ?int $supervisorUserId,
        ?string $supervisorName,
        ?int $managerUserId,
        ?string $managerName,
    ): array {
        return array_values(array_filter($technicians, static function (array $tech) use (
            $supervisorUserId, $supervisorName, $managerUserId, $managerName
        ) {
            $techUserId = (int) ($tech['user_id'] ?? 0);
            $techName   = $tech['name'] ?? '';

            // Exclude if matches supervisor
            if ($supervisorUserId && $techUserId === $supervisorUserId) {
                return false;
            }
            if ($supervisorName && self::namesMatch($techName, $supervisorName)) {
                return false;
            }

            // Exclude if matches manager
            if ($managerUserId && $techUserId === $managerUserId) {
                return false;
            }
            if ($managerName && self::namesMatch($techName, $managerName)) {
                return false;
            }

            return true;
        }));
    }

    /**
     * Replace personnel assignments for a work order.
     */
    private function syncPersonnel(WorkOrder $workOrder, array $personnel): void
    {
        // Delete existing personnel
        $workOrder->personnel()->delete();

        // Create new personnel assignments
        foreach ($personnel as $person) {
            WorkOrderPersonnel::create([
                'work_order_id' => $workOrder->id,
                'user_id' => $person['user_id'],
                'role_label' => $person['role_label'] ?? 'Teknisi',
            ]);
        }
    }

    /**
     * Replace output type records for a work order.
     */
    private function syncOutputs(WorkOrder $workOrder, array $outputTypes, ?string $outputOther = null): void
    {
        // Delete existing outputs
        $workOrder->outputs()->delete();

        // Create new output records
        foreach ($outputTypes as $type) {
            WorkOrderOutput::create([
                'work_order_id' => $workOrder->id,
                'output_type' => $type,
                'output_other' => ($type === 'other') ? $outputOther : null,
            ]);
        }
    }
}
