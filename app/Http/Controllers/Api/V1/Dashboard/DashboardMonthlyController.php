<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Dashboard\DashboardMonthlyTarget;
use App\Services\Dashboard\DashboardModuleRegistry;
use App\Services\Dashboard\DashboardMonthlySummaryService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * DashboardMonthlyController — settings CRUD for monthly checklist targets
 * + read-only summary endpoint used by the "Pengingat Pengecekan Bulanan"
 * card on dashboard + monitor.
 *
 * Read endpoints open to any authenticated user. Write endpoints gated to
 * MT / Supervisor / Admin at the route layer.
 */
class DashboardMonthlyController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DashboardMonthlySummaryService $summaryService,
    ) {}

    /**
     * GET /api/v1/dashboard/monthly/targets
     */
    public function index(): JsonResponse
    {
        $rows = DashboardMonthlyTarget::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $data = $rows->map(function (DashboardMonthlyTarget $row) {
            $module = DashboardModuleRegistry::find($row->module_key);
            return [
                'id'             => $row->id,
                'module_key'     => $row->module_key,
                'min_count'      => $row->min_count,
                'sort_order'     => $row->sort_order,
                'is_active'      => (bool) $row->is_active,
                'module_missing' => $module === null,
                'label'          => $module['label']    ?? $row->module_key,
                'division'       => $module['division'] ?? null,
                'group'          => $module['group']    ?? null,
                'route'          => $module['route']    ?? null,
                'created_at'     => $row->created_at?->toISOString(),
                'updated_at'     => $row->updated_at?->toISOString(),
            ];
        })->values()->toArray();

        return $this->success($data, 'Monthly targets retrieved');
    }

    /**
     * GET /api/v1/dashboard/monthly/summary?year=YYYY&month=MM
     *
     * Defaults to current calendar month when params omitted.
     */
    public function summary(Request $request): JsonResponse
    {
        $now = Carbon::now();
        $year  = (int) ($request->input('year')  ?? $now->year);
        $month = (int) ($request->input('month') ?? $now->month);

        // Defensive bounds — UI sends current month most of the time
        if ($year < 2020 || $year > 2099) $year = $now->year;
        if ($month < 1 || $month > 12)    $month = $now->month;

        $data = $this->summaryService->summaryForMonth($year, $month);

        return $this->success($data, 'Monthly summary retrieved');
    }

    /**
     * POST /api/v1/dashboard/monthly/targets
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'module_key' => ['required', 'string', Rule::in(DashboardModuleRegistry::validKeys())],
            'min_count'  => ['required', 'integer', 'min:1', 'max:99'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        if (DashboardMonthlyTarget::where('module_key', $data['module_key'])->exists()) {
            return $this->error('Modul ini sudah ada di daftar target bulanan.', null, 409);
        }

        $user = Auth::user();
        $target = DashboardMonthlyTarget::create([
            'module_key'    => $data['module_key'],
            'min_count'     => $data['min_count'],
            'sort_order'    => $data['sort_order'] ?? $this->nextSortOrder(),
            'is_active'     => $data['is_active'] ?? true,
            'created_by_id' => $user?->id,
            'updated_by_id' => $user?->id,
        ]);

        return $this->success($this->present($target), 'Monthly target created', 201);
    }

    /**
     * PUT /api/v1/dashboard/monthly/targets/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $target = DashboardMonthlyTarget::find($id);
        if (!$target) {
            return $this->error('Target tidak ditemukan.', null, 404);
        }

        $data = $request->validate([
            'min_count'  => ['sometimes', 'integer', 'min:1', 'max:99'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        foreach (['min_count', 'sort_order', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $target->{$field} = $data[$field];
            }
        }
        $target->updated_by_id = Auth::id();
        $target->save();

        return $this->success($this->present($target->fresh()), 'Monthly target updated');
    }

    /**
     * DELETE /api/v1/dashboard/monthly/targets/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $target = DashboardMonthlyTarget::find($id);
        if (!$target) {
            return $this->error('Target tidak ditemukan.', null, 404);
        }
        $target->delete();
        return $this->success(null, 'Monthly target deleted');
    }

    /**
     * POST /api/v1/dashboard/monthly/targets/reorder
     * Body: { items: [{ id, sort_order }, ...] }
     */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'              => ['required', 'array', 'min:1'],
            'items.*.id'         => ['required', 'integer'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        $userId = Auth::id();
        DB::transaction(function () use ($data, $userId) {
            foreach ($data['items'] as $row) {
                DashboardMonthlyTarget::where('id', $row['id'])
                    ->update([
                        'sort_order'    => $row['sort_order'],
                        'updated_by_id' => $userId,
                        'updated_at'    => now(),
                    ]);
            }
        });

        return $this->success(['count' => count($data['items'])], 'Targets reordered');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function nextSortOrder(): int
    {
        return ((int) DashboardMonthlyTarget::max('sort_order')) + 1;
    }

    private function present(DashboardMonthlyTarget $target): array
    {
        $module = DashboardModuleRegistry::find($target->module_key);
        return [
            'id'             => $target->id,
            'module_key'     => $target->module_key,
            'min_count'      => $target->min_count,
            'sort_order'     => $target->sort_order,
            'is_active'      => (bool) $target->is_active,
            'module_missing' => $module === null,
            'label'          => $module['label']    ?? $target->module_key,
            'division'       => $module['division'] ?? null,
            'group'          => $module['group']    ?? null,
            'route'          => $module['route']    ?? null,
            'updated_at'     => $target->updated_at?->toISOString(),
        ];
    }
}
