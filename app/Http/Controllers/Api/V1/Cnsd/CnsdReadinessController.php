<?php

namespace App\Http\Controllers\Api\V1\Cnsd;

use App\Exceptions\CnsdReadinessDuplicateException;
use App\Exceptions\SignerNotAuthorizedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cnsd\CreateCnsdReadinessRequest;
use App\Http\Requests\Cnsd\SignCnsdReadinessRequest;
use App\Http\Requests\Cnsd\UpdateCnsdReadinessRequest;
use App\Models\Cnsd\CnsdReadinessRecord;
use App\Services\Cnsd\CnsdEq1Template;
use App\Services\Cnsd\CnsdReadinessService;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;

class CnsdReadinessController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CnsdReadinessService $service,
        protected NotificationService $notificationService,
    ) {}

    /**
     * GET /api/v1/cnsd/readiness
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'form_type', 'date', 'year', 'shift_type', 'status', 'search', 'sort_by', 'sort_dir',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        $records = $this->service->listRecords($filters, $perPage);

        $records->through(fn (CnsdReadinessRecord $r) => $this->summarizeRecord($r));

        return $this->success($records, 'CNSD readiness records retrieved successfully');
    }

    /**
     * GET /api/v1/cnsd/readiness/template
     *
     * Returns the EQ-1 section + item template. Used by the frontend to render
     * the form skeleton before any record exists, and by the create modal to
     * preview what items will be generated.
     */
    public function template(): JsonResponse
    {
        return $this->success([
            'form_type' => 'EQ-1',
            'sections'  => CnsdEq1Template::sections(),
        ], 'EQ-1 template retrieved successfully');
    }

    /**
     * POST /api/v1/cnsd/readiness
     */
    public function store(CreateCnsdReadinessRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = Auth::user();

        if (!$user || (!$user->isAdmin() && !$user->isManager() && !$user->isSupervisor() && !$user->isTeknisi())) {
            return $this->error('Unauthorized.', null, 403);
        }

        try {
            $record = $this->service->createRecord($data, $user);
        } catch (CnsdReadinessDuplicateException $e) {
            return $this->error(
                $e->getMessage(),
                ['existing_record' => $this->detailRecord($e->existingRecord)],
                409,
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        // Fire notification for create (graceful — don't let notification failure
        // break the HTTP response).
        try {
            $this->notificationService->notifyReadinessCreated($record);
        } catch (\Throwable) {
            // Notification failure is non-fatal; log would surface in storage/logs
        }

        return $this->success($this->detailRecord($record), 'CNSD readiness record created successfully', 201);
    }

    /**
     * GET /api/v1/cnsd/readiness/{id}
     */
    public function show(int $id): JsonResponse
    {
        $record = $this->service->findRecord($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }

        return $this->success($this->detailRecord($record), 'CNSD readiness record retrieved successfully');
    }

    /**
     * PUT /api/v1/cnsd/readiness/{id}
     */
    public function update(UpdateCnsdReadinessRequest $request, int $id): JsonResponse
    {
        $record = CnsdReadinessRecord::find($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }

        $user = Auth::user();
        if (!$user || (!$user->isAdmin() && !$user->isManager() && !$user->isSupervisor() && !$user->isTeknisi())) {
            return $this->error('Unauthorized.', null, 403);
        }

        try {
            $record = $this->service->updateItems($record, $request->validated()['items']);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success($this->detailRecord($record), 'CNSD readiness record updated successfully');
    }

    /**
     * POST /api/v1/cnsd/readiness/{id}/sign
     */
    public function sign(SignCnsdReadinessRequest $request, int $id): JsonResponse
    {
        $record = CnsdReadinessRecord::find($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }

        $user = Auth::user();
        if (!$user) {
            return $this->error('Unauthenticated.', null, 401);
        }

        $payload = $request->validated();
        $oldStatus = $record->status;

        try {
            $record = $this->service->signRecord(
                $record,
                $payload['role'],
                $payload['signature'],
                $user,
                $payload['technician_row_id'] ?? null,
            );
        } catch (SignerNotAuthorizedException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        // Fire completion notification if record just transitioned to completed.
        // Graceful — notification failure must not break the sign response.
        if ($oldStatus !== 'completed' && $record->status === 'completed') {
            try {
                $this->notificationService->notifyReadinessCompleted($record, $user);
            } catch (\Throwable) {
                // Non-fatal
            }
        }

        return $this->success([
            'signed_role' => $payload['role'],
            'record'      => $this->detailRecord($record),
        ], 'Signature saved successfully');
    }

    /**
     * DELETE /api/v1/cnsd/readiness/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $record = CnsdReadinessRecord::find($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }

        $user = Auth::user();
        if (!$user || (!$user->isAdmin() && !$user->isManager())) {
            return $this->error('Unauthorized.', null, 403);
        }

        $this->service->deleteRecord($record);
        return $this->success(null, 'CNSD readiness record deleted successfully');
    }

    /**
     * GET /api/v1/cnsd/readiness/years
     */
    public function years(): JsonResponse
    {
        $years = CnsdReadinessRecord::selectRaw('EXTRACT(YEAR FROM date)::int AS y')
            ->whereNotNull('date')
            ->groupBy('y')
            ->orderByDesc('y')
            ->pluck('y')
            ->values();

        $currentYear = (int) now()->format('Y');
        if (!$years->contains($currentYear)) {
            $years = collect([$currentYear])->merge($years)->values();
        }

        return $this->success($years, 'Available years retrieved');
    }

    // ─── Transformers ─────────────────────────────────────────

    /**
     * Summary shape for list pages.
     */
    private function summarizeRecord(CnsdReadinessRecord $r): array
    {
        return [
            'id'                => $r->id,
            'form_number'       => $r->form_number,
            'form_type'         => $r->form_type,
            'facility'          => $r->facility,
            'date'              => $r->date?->format('Y-m-d'),
            'shift_type'        => $r->shift_type,
            'location'          => $r->location,
            'room'              => $r->room,
            'status'            => $r->status,
            'manager_name'      => $r->manager_name,
            'supervisor_name'   => $r->supervisor_name,
            'technicians_count' => $r->technicians_count ?? $r->technicians()->count(),
            'technician_names'  => $r->technicians->pluck('technician_name')->values()->toArray(),
            'created_at'        => $r->created_at?->toISOString(),
        ];
    }

    /**
     * Full detail shape (used by show/store/update/sign responses).
     */
    private function detailRecord(CnsdReadinessRecord $r): array
    {
        // Make sure technicians + items are eager-loaded
        $r->loadMissing(['technicians', 'items', 'manager:id,name', 'supervisor:id,name', 'creator:id,name']);

        return [
            'id'              => $r->id,
            'form_number'     => $r->form_number,
            'form_type'       => $r->form_type,
            'facility'        => $r->facility,
            'date'            => $r->date?->format('Y-m-d'),
            'shift_type'      => $r->shift_type,
            'location'        => $r->location,
            'room'            => $r->room,
            'status'          => $r->status,
            'manager' => $r->manager_name ? [
                'id'                => $r->manager_id,
                'name'              => $r->manager_name,
                'signature'         => $r->manager_signature,
                'signed_by'         => $r->manager_signed_by,
                'signed_at'         => $r->manager_signed_at?->toISOString(),
            ] : null,
            'supervisor' => $r->supervisor_name ? [
                'id'                => $r->supervisor_id,
                'name'              => $r->supervisor_name,
                'signature'         => $r->supervisor_signature,
                'signed_by'         => $r->supervisor_signed_by,
                'signed_at'         => $r->supervisor_signed_at?->toISOString(),
            ] : null,
            'technicians' => $r->technicians->map(fn ($t) => [
                'id'                  => $t->id,
                'technician_id'       => $t->technician_id,
                'technician_name'     => $t->technician_name,
                'signature'           => $t->technician_signature,
                'signed_by'           => $t->technician_signed_by,
                'signed_at'           => $t->technician_signed_at?->toISOString(),
                'sort_order'          => $t->sort_order,
            ])->values()->toArray(),
            'items' => $r->items->map(fn ($it) => [
                'id'                     => $it->id,
                'section_name'           => $it->section_name,
                'item_number'            => $it->item_number,
                'equipment_name'         => $it->equipment_name,
                'sub_equipment_name'     => $it->sub_equipment_name,
                'status_peralatan'       => $it->status_peralatan,
                'kondisi_operasional_1'  => $it->kondisi_operasional_1,
                'kondisi_operasional_2'  => $it->kondisi_operasional_2,
                'keterangan'             => $it->keterangan,
                'sort_order'             => $it->sort_order,
            ])->values()->toArray(),
            'sections_meta'  => CnsdEq1Template::sectionMeta(),
            'created_by'     => $r->created_by_id ? ['id' => $r->created_by_id, 'name' => $r->created_by_name] : null,
            'created_at'     => $r->created_at?->toISOString(),
            'updated_at'     => $r->updated_at?->toISOString(),
        ];
    }
}
