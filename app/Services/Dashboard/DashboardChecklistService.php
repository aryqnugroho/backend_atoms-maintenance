<?php

namespace App\Services\Dashboard;

use App\Models\Dashboard\DashboardChecklistItem;
use Illuminate\Database\Eloquent\Model;

/**
 * DashboardChecklistService — single entry point both DashboardController
 * and MonitorController call when they need the "Pengingat Pengecekan
 * Harian" rows. Reads from DB (dashboard_checklist_items) and joins each
 * row against DashboardModuleRegistry to resolve label/route/model.
 *
 * Rows whose module_key no longer exists in the registry (a module was
 * deleted from the codebase but the row still exists in the DB) are
 * silently skipped so the dashboard never crashes — the settings UI is
 * expected to surface "unknown module" warnings separately.
 */
class DashboardChecklistService
{
    /**
     * Build the per-shift checklist rendered on the dashboard + monitor.
     *
     * Each returned row is a flat array suitable for direct JSON serialization
     * and carries everything the frontend needs:
     *   - key, label, division, group, route (from registry)
     *   - category, shift, sort_order (from DB)
     *   - has_record, record_id (computed via findRecord for the given date+shift)
     *
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   wajib_total: int,
     *   wajib_done: int,
     *   shift_total: int,
     *   shift_done: int,
     * }
     */
    public function buildForShift(string $date, string $shift): array
    {
        $rows = DashboardChecklistItem::query()
            ->forShift($shift)
            ->get();

        $items = [];
        $wajibTotal = 0;
        $wajibDone  = 0;
        $shiftTotal = 0;
        $shiftDone  = 0;

        foreach ($rows as $row) {
            $module = DashboardModuleRegistry::find($row->module_key);
            if (!$module) {
                // Stale row — skip silently. Settings page surfaces the warning.
                continue;
            }

            // Wajib items always probe the current shift; shift items probe
            // their pinned shift_type (which equals $shift by definition for
            // forShift()-scoped rows).
            $effectiveShift = $row->category === 'wajib' ? $shift : (string) $row->shift_type;
            $record = $this->findRecord($module['model'], $date, $effectiveShift);
            $has = $record !== null;

            $items[] = [
                'id'         => $row->id,
                'key'        => $row->module_key,
                'label'      => $module['label'],
                'division'   => $module['division'],
                'group'      => $module['group'],
                'route'      => $module['route'],
                'category'   => $row->category,
                'shift'      => $row->shift_type,
                'sort_order' => $row->sort_order,
                'has_record' => $has,
                'record_id'  => $record?->id,
            ];

            if ($row->category === 'wajib') {
                $wajibTotal++;
                if ($has) $wajibDone++;
            } else {
                $shiftTotal++;
                if ($has) $shiftDone++;
            }
        }

        return [
            'items'       => $items,
            'wajib_total' => $wajibTotal,
            'wajib_done'  => $wajibDone,
            'shift_total' => $shiftTotal,
            'shift_done'  => $shiftDone,
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function findRecord(string $modelClass, string $date, string $shift): ?Model
    {
        return $modelClass::query()
            ->whereDate('date', $date)
            ->where('shift_type', $shift)
            ->orderBy('id')
            ->first(['id']);
    }
}
