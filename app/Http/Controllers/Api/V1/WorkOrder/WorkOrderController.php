<?php

namespace App\Http\Controllers\Api\V1\WorkOrder;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrder\StoreWorkOrderRequest;
use App\Http\Requests\WorkOrder\UpdateWorkOrderRequest;
use App\Models\WorkOrder\WorkOrder;
use App\Services\NotificationService;
use App\Services\WorkOrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class WorkOrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected WorkOrderService $workOrderService,
        protected NotificationService $notificationService
    ) {}

    /**
     * Display a listing of work orders.
     * Supports filtering by: division, status, shift_date, shift_type, wo_type, search.
     * Supports pagination: page, per_page.
     * Supports sorting: sort_by, sort_dir.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $filters = $request->only([
            'division', 'status', 'shift_date', 'shift_type',
            'wo_type', 'search', 'sort_by', 'sort_dir',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $perPage = min($perPage, 100); // Cap at 100

        $workOrders = $this->workOrderService->listWorkOrders($filters, $user, $perPage);

        // Transform the paginated result to match frontend expectations
        $transformed = $workOrders->through(function ($wo) {
            return $this->transformWorkOrder($wo);
        });

        return $this->success($transformed, 'Work orders retrieved successfully');
    }

    /**
     * Display the specified work order.
     */
    public function show($id): JsonResponse
    {
        $workOrder = $this->workOrderService->getWorkOrder($id);

        if (!$workOrder) {
            return $this->error('Work order not found.', null, 404);
        }

        // Check authorization via policy
        $user = Auth::user();
        if (Gate::forUser($user)->denies('view', $workOrder)) {
            return $this->error('Unauthorized. You do not have access to this work order.', null, 403);
        }

        return $this->success(
            $this->transformWorkOrder($workOrder),
            'Work order retrieved successfully'
        );
    }

    /**
     * Store a newly created work order.
     */
    public function store(StoreWorkOrderRequest $request): JsonResponse
    {
        $user = Auth::user();

        $workOrder = $this->workOrderService->createWorkOrder(
            $request->validated(),
            $user
        );

        // Send notifications
        $this->notificationService->notifyWorkOrderCreated($workOrder);

        return $this->success(
            $this->transformWorkOrder($workOrder),
            'Work order created successfully',
            201
        );
    }

    /**
     * Update the specified work order.
     */
    public function update(UpdateWorkOrderRequest $request, $id): JsonResponse
    {
        $workOrder = WorkOrder::find($id);

        if (!$workOrder) {
            return $this->error('Work order not found.', null, 404);
        }

        // Check authorization via policy
        $user = Auth::user();
        if (Gate::forUser($user)->denies('update', $workOrder)) {
            return $this->error('Unauthorized. You cannot update this work order.', null, 403);
        }

        $oldStatus = $workOrder->status;

        $workOrder = $this->workOrderService->updateWorkOrder(
            $workOrder,
            $request->validated()
        );

        // Send notification if status changed
        $newStatus = $workOrder->status;
        if ($oldStatus !== $newStatus) {
            $this->notificationService->notifyStatusChanged($workOrder, $oldStatus, $newStatus, $user);
        }

        return $this->success(
            $this->transformWorkOrder($workOrder),
            'Work order updated successfully'
        );
    }

    /**
     * Remove the specified work order (soft-delete).
     */
    public function destroy($id): JsonResponse
    {
        $workOrder = WorkOrder::find($id);

        if (!$workOrder) {
            return $this->error('Work order not found.', null, 404);
        }

        // Check authorization via policy
        $user = Auth::user();
        if (Gate::forUser($user)->denies('delete', $workOrder)) {
            return $this->error('Unauthorized. You cannot delete this work order.', null, 403);
        }

        $this->workOrderService->deleteWorkOrder($workOrder);

        return $this->success(null, 'Work order deleted successfully');
    }

    /**
     * Transform a WorkOrder model to the response shape expected by the frontend.
     */
    private function transformWorkOrder(WorkOrder $wo): array
    {
        return [
            'id' => $wo->id,
            'wo_number' => $wo->wo_number,
            'wo_type' => $wo->wo_type,
            'division' => $wo->division,
            'shift_type' => $wo->shift_type,
            'shift_date' => $wo->shift_date?->format('Y-m-d'),
            'description' => $wo->description,
            'status' => $wo->status,
            'manager_id' => $wo->manager_id,
            'supervisor_id' => $wo->supervisor_id,
            'assigned_technician_id' => $wo->assigned_technician_id,
            'manager_name_snapshot' => $wo->manager_name_snapshot,
            'supervisor_name_snapshot' => $wo->supervisor_name_snapshot,
            'start_time' => $wo->start_time,
            'end_time' => $wo->end_time,
            'completion_status' => $wo->completion_status,
            'notes_kendala' => $wo->notes_kendala,
            'notes_usulan' => $wo->notes_usulan,
            'notes_pemberi_tugas' => $wo->notes_pemberi_tugas,
            'created_by' => $wo->created_by,
            'created_at' => $wo->created_at?->toISOString(),
            'updated_at' => $wo->updated_at?->toISOString(),
            'closed_at' => $wo->closed_at?->toISOString(),
            // Nested relations
            'manager' => $wo->manager ? [
                'id' => $wo->manager->id,
                'name' => $wo->manager->name,
            ] : null,
            'supervisor' => $wo->supervisor ? [
                'id' => $wo->supervisor->id,
                'name' => $wo->supervisor->name,
            ] : null,
            'personnel' => $wo->personnel->map(function ($p) {
                return [
                    'user_id' => $p->user_id,
                    'name' => $p->user?->name ?? '',
                    'role_label' => $p->role_label,
                    'signature_url' => null,
                ];
            })->values()->toArray(),
            'output_types' => $wo->outputs->pluck('output_type')->values()->toArray(),
            'output_other' => $wo->outputs
                ->where('output_type', 'other')
                ->first()?->output_other,
        ];
    }
}
