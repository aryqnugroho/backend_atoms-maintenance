<?php

namespace App\Http\Controllers\Api\V1\Logbook;

use App\Exceptions\SignerNotAuthorizedException;
use App\Http\Controllers\Controller;
use App\Models\Logbook\LogbookTfp;
use App\Models\Logbook\TfpEquipment;
use App\Services\Logbook\LogbookTfpService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;

class LogbookTfpController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LogbookTfpService $service,
    ) {}

    /**
     * GET /api/v1/logbook/tfp
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['year', 'month', 'signed']);
        $perPage = min((int) $request->input('per_page', 15), 100);

        $logbooks = $this->service->listLogbooks($filters, $perPage);
        $logbooks->through(fn (LogbookTfp $l) => $this->summarize($l));

        return $this->success($logbooks, 'Logbook TFP list retrieved');
    }

    /**
     * GET /api/v1/logbook/tfp/years
     */
    public function years(): JsonResponse
    {
        return $this->success($this->service->getAvailableYears(), 'Available years');
    }

    /**
     * GET /api/v1/logbook/tfp/equipments
     * Returns master equipment list (for frontend reference).
     */
    public function equipments(): JsonResponse
    {
        $equipments = TfpEquipment::active()->ordered()->get(['id', 'category', 'name', 'order']);
        return $this->success($equipments, 'TFP equipment list');
    }

    /**
     * POST /api/v1/logbook/tfp
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date'],
        ]);

        $user = Auth::user();
        if (!$user) {
            return $this->error('Unauthenticated.', null, 401);
        }

        try {
            $logbook = $this->service->createLogbook($request->input('date'), $user);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        return $this->success($this->detail($logbook), 'Logbook TFP created', 201);
    }

    /**
     * GET /api/v1/logbook/tfp/{id}
     */
    public function show(int $id): JsonResponse
    {
        $logbook = $this->service->findLogbook($id);
        if (!$logbook) {
            return $this->error('Logbook tidak ditemukan.', null, 404);
        }

        $detail = $this->detail($logbook);

        // Attach personnel on duty from rostering
        $detail['personnel_on_duty'] = $this->service->getPersonnelOnDuty(
            $logbook->date->format('Y-m-d')
        );

        return $this->success($detail, 'Logbook TFP detail retrieved');
    }

    /**
     * POST /api/v1/logbook/tfp/{id}/sign
     */
    public function sign(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,'],
        ]);

        $logbook = LogbookTfp::find($id);
        if (!$logbook) {
            return $this->error('Logbook tidak ditemukan.', null, 404);
        }

        $user = Auth::user();
        if (!$user) {
            return $this->error('Unauthenticated.', null, 401);
        }

        try {
            $logbook = $this->service->signLogbook($logbook, $request->input('signature'), $user);
        } catch (SignerNotAuthorizedException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success($this->detail($logbook), 'Logbook signed successfully');
    }

    /**
     * DELETE /api/v1/logbook/tfp/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $logbook = LogbookTfp::find($id);
        if (!$logbook) {
            return $this->error('Logbook tidak ditemukan.', null, 404);
        }

        $user = Auth::user();
        if (!$user || (!$user->isAdmin() && !$user->isManager())) {
            return $this->error('Unauthorized.', null, 403);
        }

        try {
            $this->service->deleteLogbook($logbook);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success(null, 'Logbook deleted');
    }

    // ─── Transformers ─────────────────────────────────────────

    private function summarize(LogbookTfp $l): array
    {
        return [
            'id'                    => $l->id,
            'date'                  => $l->date?->format('Y-m-d'),
            'is_signed'             => !empty($l->manager_signature),
            'manager_signed_by_name'=> $l->manager_signed_by_name,
            'manager_signed_at'     => $l->manager_signed_at?->toISOString(),
            'notes_count'           => $l->notes_count ?? 0,
            'created_by_name'       => $l->created_by_name,
            'created_at'            => $l->created_at?->toISOString(),
        ];
    }

    private function detail(LogbookTfp $l): array
    {
        $l->loadMissing(['items.equipment', 'notes', 'manager:id,name', 'creator:id,name']);

        // Group items by category
        $itemsByCategory = $l->items->groupBy(fn ($item) => $item->equipment?->category ?? 'Lainnya');

        return [
            'id'                     => $l->id,
            'date'                   => $l->date?->format('Y-m-d'),
            'is_signed'              => !empty($l->manager_signature),
            'manager_signature'      => $l->manager_signature,
            'manager_signed_by_id'   => $l->manager_signed_by_id,
            'manager_signed_by_name' => $l->manager_signed_by_name,
            'manager_signed_by_role' => $l->manager_signed_by_role,
            'manager_signed_at'      => $l->manager_signed_at?->toISOString(),
            'created_by'             => $l->created_by_id ? ['id' => $l->created_by_id, 'name' => $l->created_by_name] : null,
            'created_at'             => $l->created_at?->toISOString(),
            'items_by_category'      => $itemsByCategory->map(fn ($items) => $items->map(fn ($item) => [
                'id'              => $item->id,
                'equipment_id'    => $item->tfp_equipment_id,
                'equipment_name'  => $item->equipment?->name,
                'equipment_order' => $item->equipment?->order,
                'status_pagi'     => $item->status_pagi,
                'status_siang'    => $item->status_siang,
                'status_malam'    => $item->status_malam,
            ])->values()->toArray())->toArray(),
            'notes'                  => $l->notes->map(fn ($n) => [
                'id'       => $n->id,
                'shift'    => $n->shift,
                'time'     => $n->time,
                'activity' => $n->activity,
            ])->values()->toArray(),
        ];
    }
}
