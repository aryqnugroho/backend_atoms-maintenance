<?php

namespace App\Services;

use App\Models\LocalUser;
use App\Models\WorkOrder\WorkOrder;
use App\Models\WorkOrder\WorkOrderOutput;
use App\Models\WorkOrder\WorkOrderPersonnel;
use InvalidArgumentException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WorkOrderService
{
    /**
     * List work orders with filtering, sorting, and pagination.
     * Teknisi users only see their own assigned WOs.
     */
    public function listWorkOrders(array $filters, LocalUser $user, int $perPage = 15): LengthAwarePaginator
    {
        $query = WorkOrder::with([
            'personnel.user:id,name,role',
            'outputs',
            'manager:id,name',
            'supervisor:id,name',
            'creator:id,name',
        ]);

        // Teknisi can only see their own assigned WOs
        if ($user->isTeknisi()) {
            $query->visibleToTeknisi($user->id);
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

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $allowedSorts = ['created_at', 'shift_date', 'wo_number', 'status', 'division'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get a single work order with all relationships.
     */
    public function getWorkOrder(int $id): ?WorkOrder
    {
        return WorkOrder::with([
            'personnel.user:id,name,role',
            'outputs',
            'manager:id,name',
            'supervisor:id,name',
            'assignedTechnician:id,name',
            'creator:id,name',
            'mtSigner:id,name',
            'supervisorSigner:id,name',
            'technicianSigner:id,name',
        ])->find($id);
    }

    /**
     * Create a new work order with personnel and outputs.
     */
    public function createWorkOrder(array $data, LocalUser $creator): WorkOrder
    {
        return DB::transaction(function () use ($data, $creator) {
            // ── Auto-resolve shift personnel from rostering (if roster is published) ──
            // When manager_id or supervisor_id are not provided in the request,
            // attempt to resolve them from atoms-rostering's published roster.
            // This replaces the manual selection requirement when a roster exists.
            $data = $this->resolveShiftPersonnelFromRostering($data);

            // Generate WO number
            $woNumber = $this->generateWoNumber($data['division']);

            // Snapshot manager and supervisor names
            $manager = LocalUser::find($data['manager_id']);
            $supervisor = !empty($data['supervisor_id']) ? LocalUser::find($data['supervisor_id']) : null;
            $hasSupervisor = array_key_exists('has_supervisor', $data)
                ? (bool) $data['has_supervisor']
                : $supervisor !== null;
            $selectedTechnician = $this->selectTechnicianForShift($data);

            $workOrder = WorkOrder::create([
                'wo_number' => $woNumber,
                'wo_type' => $data['wo_type'],
                'division' => $data['division'],
                'shift_type' => $data['shift_type'],
                'shift_date' => $data['shift_date'],
                'shift_id' => $data['shift_id'] ?? null,
                'description' => $data['description'],
                'status' => 'ongoing',
                'manager_id' => $data['manager_id'],
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

            // Sync personnel
            if (!empty($data['personnel'])) {
                $this->syncPersonnel($workOrder, $data['personnel']);
            }

            // Sync output types
            if (!empty($data['output_types'])) {
                $this->syncOutputs($workOrder, $data['output_types'], $data['output_other'] ?? null);
            }

            // Reload relationships
            $workOrder->load([
                'personnel.user:id,name,role',
                'outputs',
                'manager:id,name',
                'supervisor:id,name',
                'assignedTechnician:id,name',
                'creator:id,name',
                'mtSigner:id,name',
                'supervisorSigner:id,name',
                'technicianSigner:id,name',
            ]);

            return $workOrder;
        });
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

            // Reload relationships
            $workOrder->load([
                'personnel.user:id,name,role',
                'outputs',
                'manager:id,name',
                'supervisor:id,name',
                'assignedTechnician:id,name',
                'creator:id,name',
                'mtSigner:id,name',
                'supervisorSigner:id,name',
                'technicianSigner:id,name',
            ]);

            return $workOrder;
        });
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
     * Generate a sequential work order number.
     * Format: WO-{DIV}-{DD}-{MM}-{YYYY}-{SEQ}
     */
    public function generateWoNumber(string $division): string
    {
        $today = now();
        $dateStr = $today->format('d-m-Y');
        $prefix = 'WO-' . $division . '-' . $dateStr;
        $likePrefix = $prefix . '%';

        // Count existing WOs for this division and date
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

            // ── Auto-resolve Manager Teknik ──────────────────────────────────────
            if (empty($data['manager_id'])) {
                $rosteringManager = $rosteringService->getShiftManager($shiftType, $shiftDate);
                if ($rosteringManager) {
                    $localManager = LocalUser::where('rostering_user_id', $rosteringManager->user_id)
                        ->where('is_active', true)
                        ->first();
                    if ($localManager) {
                        $data['manager_id'] = $localManager->id;
                    }
                }
            }

            // ── Auto-resolve Supervisor ──────────────────────────────────────────
            // Only resolve if has_supervisor is not explicitly set to false
            $supervisorExplicitlyDisabled = array_key_exists('has_supervisor', $data)
                && $data['has_supervisor'] === false;

            if (!$supervisorExplicitlyDisabled && empty($data['supervisor_id'])) {
                $rosteringSupervisor = $rosteringService->getShiftSupervisor($shiftType, $shiftDate);
                if ($rosteringSupervisor) {
                    $localSupervisor = LocalUser::where('rostering_user_id', $rosteringSupervisor->user_id)
                        ->where('is_active', true)
                        ->first();
                    if ($localSupervisor) {
                        $data['supervisor_id'] = $localSupervisor->id;
                        // Only set has_supervisor if not already explicitly provided
                        if (!array_key_exists('has_supervisor', $data)) {
                            $data['has_supervisor'] = true;
                        }
                    }
                } else {
                    // No supervisor-level CNS in this shift — mark has_supervisor false
                    if (!array_key_exists('has_supervisor', $data)) {
                        $data['has_supervisor'] = false;
                    }
                }
            }
        } catch (\Exception $e) {
            // Rostering unavailable — proceed with original data, no auto-resolve
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

        $technicians = LocalUser::whereIn('id', $technicianIds)
            ->whereIn('role', ['Teknisi CNSD', 'Teknisi TFP'])
            ->orderBy('id')
            ->get();

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

    private function assertSignerCanSignRole(WorkOrder $workOrder, string $role, LocalUser $signer): void
    {
        $allowed = match ($role) {
            'mt' => $signer->isManager(),
            'supervisor' => $signer->isSupervisor() && (
                !$workOrder->supervisor_id || $workOrder->supervisor_id === $signer->id
            ),
            'technician' => $signer->isTeknisi() && (
                !$workOrder->assigned_technician_id || $workOrder->assigned_technician_id === $signer->id
            ),
            default => false,
        };

        if (!$allowed) {
            throw new RuntimeException('Authenticated user is not allowed to sign for this role.');
        }
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
