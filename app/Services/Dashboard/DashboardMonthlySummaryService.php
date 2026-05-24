<?php

namespace App\Services\Dashboard;

use App\Models\Dashboard\DashboardMonthlyTarget;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * DashboardMonthlySummaryService — for each active monthly target, counts
 * how many *completed* forms exist in the requested calendar month and
 * returns the list of dates so the dashboard / monitor can render both the
 * progress indicator and a deep-link list ("sudah dilakukan tanggal 5, 18").
 *
 * "Completed" means status = 'completed' (all required signatures present).
 * Forms still in `ongoing` or `on_hold` do NOT count toward the target —
 * compliance requires a fully signed record.
 *
 * Stale targets (module_key removed from the registry) are silently skipped.
 */
class DashboardMonthlySummaryService
{
    /**
     * Build the monthly summary for $year-$month (1-indexed month).
     *
     * @return array{
     *   year: int,
     *   month: int,
     *   month_label: string,
     *   items: array<int, array<string, mixed>>,
     *   targets_total: int,
     *   targets_met: int,
     * }
     */
    public function summaryForMonth(int $year, int $month): array
    {
        $targets = DashboardMonthlyTarget::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $items = [];
        $targetsMet = 0;

        foreach ($targets as $target) {
            $module = DashboardModuleRegistry::find($target->module_key);
            if (!$module) {
                continue;
            }

            $records = $this->fetchCompletedRecords($module['model'], $year, $month);
            $currentCount = $records->count();
            $met = $currentCount >= (int) $target->min_count;
            if ($met) {
                $targetsMet++;
            }

            $items[] = [
                'id'            => $target->id,
                'module_key'    => $target->module_key,
                'label'         => $module['label'],
                'division'      => $module['division'],
                'group'         => $module['group'],
                'route'         => $module['route'],
                'min_count'     => (int) $target->min_count,
                'current_count' => $currentCount,
                'met'           => $met,
                'sort_order'    => $target->sort_order,
                'records'       => $records->map(fn ($r) => [
                    'id'   => $r->id,
                    'date' => $this->dateString($r->date),
                ])->values()->toArray(),
            ];
        }

        $monthLabel = Carbon::createFromDate($year, $month, 1)
            ->locale('id')
            ->translatedFormat('F Y');

        return [
            'year'          => $year,
            'month'         => $month,
            'month_label'   => $monthLabel,
            'items'         => $items,
            'targets_total' => count($items),
            'targets_met'   => $targetsMet,
        ];
    }

    /**
     * Pull completed records for one model within the given month.
     * Returns id + date only — minimal columns to keep the response small
     * even when a target has many records.
     *
     * @param  class-string<Model>  $modelClass
     */
    private function fetchCompletedRecords(string $modelClass, int $year, int $month)
    {
        return $modelClass::query()
            ->where('status', 'completed')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date')
            ->orderBy('id')
            ->get(['id', 'date']);
    }

    private function dateString(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }
        return substr((string) $date, 0, 10);
    }
}
