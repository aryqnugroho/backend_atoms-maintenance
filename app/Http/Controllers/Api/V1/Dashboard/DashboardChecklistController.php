<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Dashboard\DashboardChecklistItem;
use App\Services\Dashboard\DashboardModuleRegistry;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * DashboardChecklistController — settings CRUD for the editable "Pengingat
 * Pengecekan Harian" catalog. Powers the /settings/checklist page used by
 * MT / Supervisor / Admin to add, remove, reorder, and activate/deactivate
 * the modules that appear on the dashboard + monitor.
 *
 * Read endpoints (list/modules) are open to any authenticated user so the
 * dashboard can surface a "kelola" link only to authorized roles. Write
 * endpoints are gated at the route layer via `role:Admin,Manager Teknik,
 * Supervisor CNSD,Supervisor TFP`.
 */
class DashboardChecklistController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/dashboard/checklist/modules
     *
     * Return the full registry of modules that can be picked when adding
     * a new checklist item. Grouped on the client via the `group` field.
     */
    public function modules(): JsonResponse
    {
        return $this->success(
            DashboardModuleRegistry::modules(),
            'Module registry retrieved'
        );
    }

    /**
     * GET /api/v1/dashboard/checklist/items
     *
     * Return all checklist items (active + inactive) joined with the
     * module info from the registry. Items whose module_key has been
     * removed from the registry are still returned, but with a
     * `module_missing: true` flag so the settings UI can warn.
     */
    public function index(): JsonResponse
    {
        $rows = DashboardChecklistItem::query()
            ->orderBy('category')
            ->orderBy('shift_type')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $data = $rows->map(function (DashboardChecklistItem $row) {
            $module = DashboardModuleRegistry::find($row->module_key);
            return [
                'id'             => $row->id,
                'module_key'     => $row->module_key,
                'category'       => $row->category,
                'shift_type'     => $row->shift_type,
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

        return $this->success($data, 'Checklist items retrieved');
    }

    /**
     * POST /api/v1/dashboard/checklist/items
     *
     * Add a new checklist item. Server-side enforces:
     *   - module_key exists in the registry
     *   - category=wajib → shift_type must be null (one row per module)
     *   - category=shift → shift_type required (one row per module+shift)
     *   - no duplicate (module_key, category, shift_type) combination
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);
        $user = Auth::user();

        // Duplicate guard at app layer for friendly error; DB partial unique
        // index is the safety net for races.
        $exists = DashboardChecklistItem::query()
            ->where('module_key', $data['module_key'])
            ->where('category', $data['category'])
            ->when(
                $data['category'] === 'shift',
                fn ($q) => $q->where('shift_type', $data['shift_type']),
                fn ($q) => $q->whereNull('shift_type'),
            )
            ->exists();
        if ($exists) {
            return $this->error(
                'Modul ini sudah ada di kategori/shift tersebut.',
                null,
                409,
            );
        }

        $item = DashboardChecklistItem::create([
            'module_key'    => $data['module_key'],
            'category'      => $data['category'],
            'shift_type'    => $data['category'] === 'wajib' ? null : $data['shift_type'],
            'sort_order'    => $data['sort_order'] ?? $this->nextSortOrder($data['category'], $data['shift_type'] ?? null),
            'is_active'     => $data['is_active'] ?? true,
            'created_by_id' => $user?->id,
            'updated_by_id' => $user?->id,
        ]);

        return $this->success($this->present($item), 'Checklist item created', 201);
    }

    /**
     * PUT /api/v1/dashboard/checklist/items/{id}
     *
     * Update sort_order / is_active. Module key + category + shift_type are
     * immutable post-create (delete + re-add if you need to "move" a module).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $item = DashboardChecklistItem::find($id);
        if (!$item) {
            return $this->error('Item tidak ditemukan.', null, 404);
        }

        $data = $request->validate([
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('sort_order', $data)) $item->sort_order = $data['sort_order'];
        if (array_key_exists('is_active',  $data)) $item->is_active  = $data['is_active'];
        $item->updated_by_id = Auth::id();
        $item->save();

        return $this->success($this->present($item->fresh()), 'Checklist item updated');
    }

    /**
     * DELETE /api/v1/dashboard/checklist/items/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $item = DashboardChecklistItem::find($id);
        if (!$item) {
            return $this->error('Item tidak ditemukan.', null, 404);
        }
        $item->delete();
        return $this->success(null, 'Checklist item deleted');
    }

    /**
     * POST /api/v1/dashboard/checklist/items/reorder
     * Body: { items: [{ id: 1, sort_order: 0 }, ...] }
     *
     * Bulk reorder within a single (category, shift_type) group. Frontend
     * sends the new ordering after a drag-drop / move-up/down operation.
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
                DashboardChecklistItem::where('id', $row['id'])
                    ->update([
                        'sort_order'    => $row['sort_order'],
                        'updated_by_id' => $userId,
                        'updated_at'    => now(),
                    ]);
            }
        });

        return $this->success(['count' => count($data['items'])], 'Items reordered');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'module_key' => ['required', 'string', Rule::in(DashboardModuleRegistry::validKeys())],
            'category'   => ['required', Rule::in(DashboardChecklistItem::CATEGORIES)],
            'shift_type' => [
                'nullable',
                Rule::in(DashboardChecklistItem::SHIFT_TYPES),
                Rule::requiredIf(fn () => $request->input('category') === 'shift'),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);
    }

    private function nextSortOrder(string $category, ?string $shiftType): int
    {
        $max = DashboardChecklistItem::query()
            ->where('category', $category)
            ->when(
                $category === 'shift',
                fn ($q) => $q->where('shift_type', $shiftType),
                fn ($q) => $q->whereNull('shift_type'),
            )
            ->max('sort_order');
        return ((int) $max) + 1;
    }

    private function present(DashboardChecklistItem $item): array
    {
        $module = DashboardModuleRegistry::find($item->module_key);
        return [
            'id'             => $item->id,
            'module_key'     => $item->module_key,
            'category'       => $item->category,
            'shift_type'     => $item->shift_type,
            'sort_order'     => $item->sort_order,
            'is_active'      => (bool) $item->is_active,
            'module_missing' => $module === null,
            'label'          => $module['label']    ?? $item->module_key,
            'division'       => $module['division'] ?? null,
            'group'          => $module['group']    ?? null,
            'route'          => $module['route']    ?? null,
            'updated_at'     => $item->updated_at?->toISOString(),
        ];
    }
}
