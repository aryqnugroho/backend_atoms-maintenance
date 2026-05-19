<?php

namespace App\Http\Controllers\Api\V1\Logbook;

use App\Exceptions\SignerNotAuthorizedException;
use App\Http\Controllers\Controller;
use App\Models\Logbook\CnsdEquipment;
use App\Models\Logbook\LogbookCnsd;
use App\Services\Logbook\LogbookCnsdService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;

class LogbookCnsdController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LogbookCnsdService $service,
    ) {}

    /**
     * GET /api/v1/logbook/cnsd
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['year', 'month', 'signed']);
        $perPage = min((int) $request->input('per_page', 15), 100);

        $logbooks = $this->service->listLogbooks($filters, $perPage);

        // Batch-fetch Manager Teknik per shift for all dates on this page (single query)
        $dates = $logbooks->getCollection()
            ->map(fn (LogbookCnsd $l) => $l->date?->format('Y-m-d'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        $managersMap = $this->service->getManagersOnDutyForDates($dates);

        $logbooks->through(fn (LogbookCnsd $l) => $this->summarize($l, $managersMap));

        return $this->success($logbooks, 'Logbook CNSD list retrieved');
    }

    /**
     * GET /api/v1/logbook/cnsd/years
     */
    public function years(): JsonResponse
    {
        return $this->success($this->service->getAvailableYears(), 'Available years');
    }

    /**
     * GET /api/v1/logbook/cnsd/equipments
     */
    public function equipments(): JsonResponse
    {
        $equipments = CnsdEquipment::active()->ordered()
            ->get(['id', 'category', 'name', 'is_measurement', 'unit', 'order']);
        return $this->success($equipments, 'CNSD equipment list');
    }

    /**
     * POST /api/v1/logbook/cnsd
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

        return $this->success($this->detail($logbook), 'Logbook CNSD created', 201);
    }

    /**
     * GET /api/v1/logbook/cnsd/{id}
     */
    public function show(int $id): JsonResponse
    {
        $logbook = $this->service->findLogbook($id);
        if (!$logbook) {
            return $this->error('Logbook tidak ditemukan.', null, 404);
        }

        $detail = $this->detail($logbook);
        $detail['personnel_on_duty'] = $this->service->getPersonnelOnDuty(
            $logbook->date->format('Y-m-d')
        );

        return $this->success($detail, 'Logbook CNSD detail retrieved');
    }

    /**
     * POST /api/v1/logbook/cnsd/{id}/equipments
     */
    public function addEquipment(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', 'max:150'],
        ]);

        $logbook = LogbookCnsd::find($id);
        if (!$logbook) {
            return $this->error('Logbook tidak ditemukan.', null, 404);
        }

        $user = Auth::user();
        if (!$user || (!$user->isManager() && !$user->isSupervisor() && !$user->isAdmin())) {
            return $this->error('Hanya Manager Teknik atau Supervisor yang dapat mengelola peralatan.', null, 403);
        }

        if (!empty($logbook->manager_signature)) {
            return $this->error('Logbook yang sudah ditandatangani tidak dapat diubah.', null, 409);
        }

        try {
            $logbook = $this->service->addEquipmentToLogbook($logbook, $request->input('name'), $request->input('category'));
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        $detail = $this->detail($logbook);
        $detail['personnel_on_duty'] = $this->service->getPersonnelOnDuty($logbook->date->format('Y-m-d'));

        return $this->success($detail, 'Equipment added');
    }

    /**
     * PUT /api/v1/logbook/cnsd/{id}/equipments/{itemId}
     */
    public function editEquipment(Request $request, int $id, int $itemId): JsonResponse
    {
        $request->validate([
            'name'     => ['sometimes', 'required', 'string', 'max:200'],
            'category' => ['sometimes', 'required', 'string', 'max:150'],
        ]);

        $logbook = LogbookCnsd::find($id);
        if (!$logbook) {
            return $this->error('Logbook tidak ditemukan.', null, 404);
        }

        $user = Auth::user();
        if (!$user || (!$user->isManager() && !$user->isSupervisor() && !$user->isAdmin())) {
            return $this->error('Hanya Manager Teknik atau Supervisor yang dapat mengelola peralatan.', null, 403);
        }

        if (!empty($logbook->manager_signature)) {
            return $this->error('Logbook yang sudah ditandatangani tidak dapat diubah.', null, 409);
        }

        try {
            $logbook = $this->service->editEquipmentInLogbook($logbook, $itemId, $request->only(['name', 'category']));
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        $detail = $this->detail($logbook);
        $detail['personnel_on_duty'] = $this->service->getPersonnelOnDuty($logbook->date->format('Y-m-d'));

        return $this->success($detail, 'Equipment updated');
    }

    /**
     * DELETE /api/v1/logbook/cnsd/{id}/equipments/{itemId}
     */
    public function removeEquipment(int $id, int $itemId): JsonResponse
    {
        $logbook = LogbookCnsd::find($id);
        if (!$logbook) {
            return $this->error('Logbook tidak ditemukan.', null, 404);
        }

        $user = Auth::user();
        if (!$user || (!$user->isManager() && !$user->isSupervisor() && !$user->isAdmin())) {
            return $this->error('Hanya Manager Teknik atau Supervisor yang dapat mengelola peralatan.', null, 403);
        }

        if (!empty($logbook->manager_signature)) {
            return $this->error('Logbook yang sudah ditandatangani tidak dapat diubah.', null, 409);
        }

        try {
            $logbook = $this->service->removeEquipmentFromLogbook($logbook, $itemId);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        $detail = $this->detail($logbook);
        $detail['personnel_on_duty'] = $this->service->getPersonnelOnDuty($logbook->date->format('Y-m-d'));

        return $this->success($detail, 'Equipment removed');
    }

    /**
     * PUT /api/v1/logbook/cnsd/{id}/items
     * Update S/US status and/or measurement values for equipment items.
     */
    public function updateItems(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'items'              => ['required', 'array'],
            'items.*.id'         => ['required', 'integer'],
            'items.*.status_pagi'  => ['nullable', 'string', 'in:S,US'],
            'items.*.status_siang' => ['nullable', 'string', 'in:S,US'],
            'items.*.status_malam' => ['nullable', 'string', 'in:S,US'],
            'items.*.value_pagi'   => ['nullable', 'string', 'max:30'],
            'items.*.value_siang'  => ['nullable', 'string', 'max:30'],
            'items.*.value_malam'  => ['nullable', 'string', 'max:30'],
        ]);

        $logbook = LogbookCnsd::find($id);
        if (!$logbook) {
            return $this->error('Logbook tidak ditemukan.', null, 404);
        }

        if (!empty($logbook->manager_signature)) {
            return $this->error('Logbook yang sudah ditandatangani tidak dapat diubah.', null, 409);
        }

        try {
            $logbook = $this->service->updateItems($logbook, $request->input('items'));
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        $detail = $this->detail($logbook);
        $detail['personnel_on_duty'] = $this->service->getPersonnelOnDuty($logbook->date->format('Y-m-d'));

        return $this->success($detail, 'Items updated');
    }

    /**
     * POST /api/v1/logbook/cnsd/{id}/notes
     */
    public function addNote(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'shift'    => ['required', 'string', 'in:pagi,siang,malam'],
            'time'     => ['nullable', 'string', 'max:10'],
            'activity' => ['required', 'string', 'max:1000'],
        ]);

        $logbook = LogbookCnsd::find($id);
        if (!$logbook) {
            return $this->error('Logbook tidak ditemukan.', null, 404);
        }

        $user = Auth::user();
        if (!$user) {
            return $this->error('Unauthenticated.', null, 401);
        }

        $logbook = $this->service->addNote(
            $logbook,
            $request->input('shift'),
            $request->input('time'),
            $request->input('activity'),
            $user,
        );

        $detail = $this->detail($logbook);
        $detail['personnel_on_duty'] = $this->service->getPersonnelOnDuty($logbook->date->format('Y-m-d'));

        return $this->success($detail, 'Note added');
    }

    /**
     * DELETE /api/v1/logbook/cnsd/{id}/notes/{noteId}
     */
    public function deleteNote(int $id, int $noteId): JsonResponse
    {
        $logbook = LogbookCnsd::find($id);
        if (!$logbook) {
            return $this->error('Logbook tidak ditemukan.', null, 404);
        }

        if (!empty($logbook->manager_signature)) {
            return $this->error('Logbook yang sudah ditandatangani tidak dapat diubah.', null, 409);
        }

        $this->service->deleteNote($logbook, $noteId);

        $detail = $this->detail($logbook->fresh(['items.equipment', 'notes', 'manager:id,name', 'creator:id,name']));
        $detail['personnel_on_duty'] = $this->service->getPersonnelOnDuty($logbook->date->format('Y-m-d'));

        return $this->success($detail, 'Note deleted');
    }

    /**
     * POST /api/v1/logbook/cnsd/{id}/sign
     */
    public function sign(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,'],
        ]);

        $logbook = LogbookCnsd::find($id);
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
     * DELETE /api/v1/logbook/cnsd/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $logbook = LogbookCnsd::find($id);
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

    /**
     * @param  array<string, array{pagi: ?object, siang: ?object, malam: ?object}>  $managersMap
     */
    private function summarize(LogbookCnsd $l, array $managersMap = []): array
    {
        $dateKey         = $l->date?->format('Y-m-d');
        $managersByShift = $dateKey ? ($managersMap[$dateKey] ?? null) : null;

        $managersOnDuty = [];
        if ($managersByShift) {
            foreach (['pagi', 'siang', 'malam'] as $shift) {
                $mgr = $managersByShift[$shift] ?? null;
                if ($mgr) {
                    $managersOnDuty[] = [
                        'shift'   => $shift,
                        'name'    => $mgr->name,
                        'user_id' => $mgr->user_id,
                    ];
                }
            }
        }

        return [
            'id'                     => $l->id,
            'date'                   => $dateKey,
            'is_signed'              => !empty($l->manager_signature),
            'manager_signed_by_name' => $l->manager_signed_by_name,
            'manager_signed_at'      => $l->manager_signed_at?->toISOString(),
            'notes_count'            => $l->notes_count ?? 0,
            'created_by_name'        => $l->created_by_name,
            'created_at'             => $l->created_at?->toISOString(),
            'managers_on_duty'       => $managersOnDuty,
        ];
    }

    private function detail(LogbookCnsd $l): array
    {
        $l->loadMissing(['items.equipment', 'notes', 'manager:id,name', 'creator:id,name']);

        // Group items by category, preserving the seeded equipment order
        $itemsByCategory = $l->items
            ->sortBy(fn ($item) => $item->equipment?->order ?? 0)
            ->groupBy(fn ($item) => $item->equipment?->category ?? 'Lainnya');

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
                'equipment_id'    => $item->cnsd_equipment_id,
                'equipment_name'  => $item->equipment?->name,
                'equipment_order' => $item->equipment?->order,
                'is_measurement'  => (bool) ($item->equipment?->is_measurement ?? false),
                'unit'            => $item->equipment?->unit,
                'status_pagi'     => $item->status_pagi,
                'status_siang'    => $item->status_siang,
                'status_malam'    => $item->status_malam,
                'value_pagi'      => $item->value_pagi,
                'value_siang'     => $item->value_siang,
                'value_malam'     => $item->value_malam,
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
