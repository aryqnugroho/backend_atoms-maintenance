<?php

namespace App\Http\Controllers\Api\V1\WorkOrder;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrder\WorkOrderCreateRequest;
use App\Http\Requests\WorkOrder\WorkOrderSignRequest;
use App\Http\Requests\WorkOrder\WorkOrderUpdateRequest;
use App\Models\WorkOrder\WorkOrder;
use App\Services\NotificationService;
use App\Services\WorkOrderService;
use App\Traits\ApiResponse;
use InvalidArgumentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

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
            'wo_type', 'search', 'sort_by', 'sort_dir', 'year',
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
            $this->transformWorkOrder($workOrder, true),
            'Work order retrieved successfully'
        );
    }

    /**
     * Store a newly created work order.
     */
    public function store(WorkOrderCreateRequest $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $workOrder = $this->workOrderService->createWorkOrder(
                $request->validated(),
                $user
            );
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), null, 409);
        }

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
    public function update(WorkOrderUpdateRequest $request, $id): JsonResponse
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

        $oldStatus              = $workOrder->status;
        $oldDescription         = $workOrder->description;
        $oldNotesPemberi        = $workOrder->notes_pemberi_tugas;

        $workOrder = $this->workOrderService->updateWorkOrder(
            $workOrder,
            $request->validated()
        );

        // Send notification if status changed
        $newStatus = $workOrder->status;
        if ($oldStatus !== $newStatus) {
            $this->notificationService->notifyStatusChanged($workOrder, $oldStatus, $newStatus, $user);
        }

        // Send notification if description / notes_pemberi_tugas changed
        $changedFields = [];
        if ($oldDescription !== $workOrder->description) {
            $changedFields[] = 'description';
        }
        if ($oldNotesPemberi !== $workOrder->notes_pemberi_tugas) {
            $changedFields[] = 'notes_pemberi_tugas';
        }
        if (!empty($changedFields)) {
            $this->notificationService->notifyWorkOrderEdited($workOrder, $changedFields, $user);
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
     * Save a role signature for the work order.
     */
    public function sign(WorkOrderSignRequest $request, $id): JsonResponse
    {
        $workOrder = WorkOrder::find($id);

        if (!$workOrder) {
            return $this->error('Work order not found.', null, 404);
        }

        $user = Auth::user();
        if (Gate::forUser($user)->denies('update', $workOrder)) {
            return $this->error('Unauthorized. You cannot sign this work order.', null, 403);
        }

        $validated = $request->validated();

        try {
            $workOrder = $this->workOrderService->signWorkOrder(
                $workOrder,
                $validated['role'],
                $validated['signature'],
                $user
            );
        } catch (\App\Exceptions\SignerNotAuthorizedException $exception) {
            // 403 — wrong user trying to sign on behalf of someone else
            return $this->error($exception->getMessage(), null, 403);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), null, 422);
        } catch (RuntimeException $exception) {
            // Generic conflict (already signed, completed WO, etc.)
            return $this->error($exception->getMessage(), null, 409);
        }

        return $this->success([
            'signed_role' => $validated['role'],
            'pending_roles' => $workOrder->getPendingSignatures(),
            'current_status' => $workOrder->status,
            'record' => $this->transformWorkOrder($workOrder, true),
        ], 'Signature saved successfully');
    }

    /**
     * Return distinct years available in shift_date, descending.
     * Used by the frontend year-filter dropdown.
     */
    public function years(): JsonResponse
    {
        $years = WorkOrder::selectRaw('EXTRACT(YEAR FROM shift_date)::int AS y')
            ->whereNotNull('shift_date')
            ->groupBy('y')
            ->orderByDesc('y')
            ->pluck('y')
            ->values();

        // Always include current year even if no WOs exist yet
        $currentYear = (int) now()->format('Y');
        if (!$years->contains($currentYear)) {
            $years = collect([$currentYear])->merge($years)->values();
        }

        return $this->success($years, 'Available years retrieved');
    }

    /**
     * Return the full data structure needed by the print view.
     */
    public function print($id): JsonResponse
    {
        $workOrder = $this->workOrderService->getWorkOrder($id);

        if (!$workOrder) {
            return $this->error('Work order not found.', null, 404);
        }

        $user = Auth::user();
        if (Gate::forUser($user)->denies('view', $workOrder)) {
            return $this->error('Unauthorized. You do not have access to this work order.', null, 403);
        }

        return $this->success([
            'work_order' => $this->transformWorkOrder($workOrder, true),
            'required_signatures' => $workOrder->getRequiredSignatures(),
            'pending_signatures' => $workOrder->getPendingSignatures(),
        ], 'Work order print data retrieved successfully');
    }

    /**
     * Transform a WorkOrder model to the response shape expected by the frontend.
     */
    private function transformWorkOrder(WorkOrder $wo, bool $includeSignatures = false): array
    {
        $data = [
            'id' => $wo->id,
            'wo_number' => $wo->wo_number,
            'wo_type' => $wo->wo_type,
            'division' => $wo->division,
            'shift_id' => $wo->shift_id,
            'shift_type' => $wo->shift_type,
            'shift_date' => $wo->shift_date?->format('Y-m-d'),
            'description' => $wo->description,
            'status' => $wo->status,
            'manager_id' => $wo->manager_id,
            'supervisor_id' => $wo->supervisor_id,
            'assigned_technician_id' => $wo->assigned_technician_id,
            'has_supervisor' => $wo->has_supervisor,
            'manager_name_snapshot' => $wo->manager_name_snapshot,
            'supervisor_name_snapshot' => $wo->supervisor_name_snapshot,
            'mt_name' => $wo->mt_name,
            'supervisor_name' => $wo->supervisor_name,
            'technician_name' => $wo->technician_name,
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
            'required_signatures' => $wo->getRequiredSignatures(),
            'pending_signatures' => $wo->getPendingSignatures(),
            // Nested relations
            'manager' => $wo->manager ? [
                'id' => $wo->manager->id,
                'name' => $wo->manager->name,
            ] : null,
            'supervisor' => $wo->supervisor ? [
                'id' => $wo->supervisor->id,
                'name' => $wo->supervisor->name,
            ] : null,
            'creator' => $wo->creator ? [
                'id' => $wo->creator->id,
                'name' => $wo->creator->name,
                'role' => $wo->creator->role,
            ] : null,
            'personnel' => $wo->personnel->map(function ($p) {
                return [
                    'user_id' => $p->user_id,
                    'name' => $p->user?->name ?? '',
                    'role_label' => $p->role_label,
                ];
            })->values()->toArray(),
            'output_types' => $wo->outputs->pluck('output_type')->values()->toArray(),
            'output_other' => $wo->outputs
                ->where('output_type', 'other')
                ->first()?->output_other,
        ];

        if ($includeSignatures) {
            $data['signatures'] = [
                'mt' => [
                    'name' => $wo->mt_name,
                    'signature' => $wo->mt_signature,
                    'signed_by' => $wo->mt_signed_by,
                    'signed_at' => $wo->mt_signed_at?->toISOString(),
                ],
                'supervisor' => [
                    'name' => $wo->supervisor_name,
                    'signature' => $wo->supervisor_signature,
                    'signed_by' => $wo->supervisor_signed_by,
                    'signed_at' => $wo->supervisor_signed_at?->toISOString(),
                ],
                'technician' => [
                    'name' => $wo->technician_name,
                    'signature' => $wo->technician_signature,
                    'signed_by' => $wo->technician_signed_by,
                    'signed_at' => $wo->technician_signed_at?->toISOString(),
                ],
            ];
        }

        return $data;
    }
}
