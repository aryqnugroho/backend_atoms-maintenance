<?php

namespace App\Services;

use App\Models\LocalUser;
use App\Models\WorkOrder\WorkOrder;
use App\Models\WorkOrder\WorkOrderOutput;
use App\Models\WorkOrder\WorkOrderPersonnel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
                $q->where('wo_number', 'ILIKE', "%{$search}%")
                  ->orWhere('description', 'ILIKE', "%{$search}%");
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
        ])->find($id);
    }

    /**
     * Create a new work order with personnel and outputs.
     */
    public function createWorkOrder(array $data, LocalUser $creator): WorkOrder
    {
        return DB::transaction(function () use ($data, $creator) {
            // Generate WO number
            $woNumber = $this->generateWoNumber($data['division']);

            // Snapshot manager and supervisor names
            $manager = LocalUser::find($data['manager_id']);
            $supervisor = LocalUser::find($data['supervisor_id']);

            $workOrder = WorkOrder::create([
                'wo_number' => $woNumber,
                'wo_type' => $data['wo_type'],
                'division' => $data['division'],
                'shift_type' => $data['shift_type'],
                'shift_date' => $data['shift_date'],
                'description' => $data['description'],
                'status' => $data['status'] ?? 'ongoing',
                'manager_id' => $data['manager_id'],
                'supervisor_id' => $data['supervisor_id'],
                'assigned_technician_id' => $data['assigned_technician_id'] ?? null,
                'manager_name_snapshot' => $manager ? $manager->name : '',
                'supervisor_name_snapshot' => $supervisor ? $supervisor->name : '',
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
                'creator:id,name',
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
                'wo_type', 'division', 'shift_type', 'shift_date', 'description',
                'status', 'manager_id', 'supervisor_id', 'assigned_technician_id',
                'start_time', 'end_time', 'completion_status',
                'notes_kendala', 'notes_usulan', 'notes_pemberi_tugas',
            ];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateFields[$field] = $data[$field];
                }
            }

            // Update name snapshots if manager/supervisor changed
            if (isset($data['manager_id'])) {
                $manager = LocalUser::find($data['manager_id']);
                $updateFields['manager_name_snapshot'] = $manager ? $manager->name : '';
            }

            if (isset($data['supervisor_id'])) {
                $supervisor = LocalUser::find($data['supervisor_id']);
                $updateFields['supervisor_name_snapshot'] = $supervisor ? $supervisor->name : '';
            }

            // Set closed_at when status becomes completed
            if (isset($data['status']) && $data['status'] === 'completed' && $oldStatus !== 'completed') {
                $updateFields['closed_at'] = now();
            }

            if (!empty($updateFields)) {
                $workOrder->update($updateFields);
            }

            // Sync personnel if provided
            if (isset($data['personnel'])) {
                $this->syncPersonnel($workOrder, $data['personnel']);
            }

            // Sync output types if provided
            if (isset($data['output_types'])) {
                $this->syncOutputs($workOrder, $data['output_types'], $data['output_other'] ?? null);
            }

            // Reload relationships
            $workOrder->load([
                'personnel.user:id,name,role',
                'outputs',
                'manager:id,name',
                'supervisor:id,name',
                'creator:id,name',
            ]);

            return $workOrder;
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
        $prefix = "WO-{$division}-{$dateStr}";

        // Count existing WOs for this division and date
        $count = WorkOrder::withTrashed()
            ->where('wo_number', 'LIKE', "{$prefix}%")
            ->count();

        $seq = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        return "{$prefix}-{$seq}";
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
