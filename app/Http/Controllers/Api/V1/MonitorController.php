<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Logbook\LogbookCnsdNote;
use App\Models\Logbook\LogbookTfpNote;
use App\Models\Reporting\ReportingDamageReport;
use App\Models\WorkOrder\WorkOrder;
use App\Services\Dashboard\DashboardChecklistService;
use App\Services\Dashboard\DashboardMonthlySummaryService;
use App\Services\RosteringIntegrationService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Workshop TV monitor endpoints.
 *
 * Two access tiers:
 *   - PUBLIC (no auth): verify password, fetch snapshot.
 *     These power the kiosk-style /monitor screen. The password modal is
 *     purely a UI gate — the snapshot exposes only data already visible
 *     to authenticated users (no PII beyond names, no signatures).
 *   - PROTECTED (auth + role): rotate the monitor password.
 *     Only Manager Teknik / Supervisor CNSD / Supervisor TFP / Admin
 *     can rotate the kiosk gate password.
 */
class MonitorController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected RosteringIntegrationService $rosteringService,
        protected DashboardChecklistService $checklistService,
        protected DashboardMonthlySummaryService $monthlyService,
    ) {}

    // ─── PUBLIC: password verification ────────────────────────────────────

    /**
     * POST /api/v1/public/monitor/verify
     * Body: { password: string }
     *
     * Returns { ok: bool }. Throttled to mitigate brute force.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'max:255'],
        ]);

        $ok = AppSetting::verifyMonitorPassword((string) $request->input('password'));
        if (!$ok) {
            return $this->error('Password salah.', null, 401);
        }
        return $this->success(['ok' => true], 'Verified');
    }

    // ─── PUBLIC: aggregated snapshot ──────────────────────────────────────

    /**
     * GET /api/v1/public/monitor/snapshot
     *
     * Returns everything the kiosk page needs in one round trip so the
     * 60s refresh does not generate 5 concurrent requests.
     */
    public function snapshot(Request $request): JsonResponse
    {
        $date  = $this->resolveDate($request->input('date'));
        $shift = $this->resolveShift($request->input('shift_type'));

        $now = Carbon::parse($date);

        return $this->success([
            'server_time'    => now()->toIso8601String(),
            'date'           => $date,
            'shift'          => $this->shiftBlock($shift),
            'personnel'      => $this->personnelBlock($shift, $date),
            'checklist'      => $this->checklistBlock($shift, $date),
            'monthly'        => $this->monthlyService->summaryForMonth($now->year, $now->month),
            'work_orders'    => $this->workOrdersBlock($date),
            'damage_reports' => $this->damageReportsBlock(),
            'logbook'        => $this->logbookBlock($date),
        ], 'Monitor snapshot retrieved');
    }

    // ─── PROTECTED: rotate password ───────────────────────────────────────

    /**
     * PUT /api/v1/monitor/password
     * Body: { current_password: string, new_password: string }
     *
     * Auth required. Role middleware on the route enforces who can call.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'min:6', 'max:255'],
        ]);

        $user = Auth::user();
        if (!$user) {
            return $this->error('Tidak terautentikasi.', null, 401);
        }

        if (!AppSetting::verifyMonitorPassword((string) $request->input('current_password'))) {
            return $this->error('Password lama tidak cocok.', null, 422);
        }

        $newPassword = (string) $request->input('new_password');

        // Guardrail: prevent setting the same password to the same value.
        if (Hash::check($newPassword, (string) AppSetting::get(AppSetting::KEY_MONITOR_PASSWORD_HASH))) {
            return $this->error('Password baru tidak boleh sama dengan password lama.', null, 422);
        }

        AppSetting::setMonitorPassword($newPassword);

        return $this->success(['ok' => true], 'Password monitor berhasil diubah.');
    }

    // ─── Block builders ───────────────────────────────────────────────────

    /**
     * @return array{type:string,label:string,start_time:?string,end_time:?string}
     */
    private function shiftBlock(string $shift): array
    {
        $times = $this->rosteringService->getShiftTimes($shift);
        $labels = ['pagi' => 'Shift Pagi', 'siang' => 'Shift Siang', 'malam' => 'Shift Malam'];
        return [
            'type'       => $shift,
            'label'      => $labels[$shift] ?? ucfirst($shift),
            'start_time' => $times['start_time'] ?? null,
            'end_time'   => $times['end_time'] ?? null,
        ];
    }

    /**
     * Roster-backed personnel for the active shift, grouped by division.
     * Falls back gracefully to local_users when rostering is unreachable
     * (so the kiosk never goes blank during a roster outage).
     */
    private function personnelBlock(string $shift, string $date): array
    {
        $context = $this->rosteringService->getShiftContext($shift, $date);
        /** @var \Illuminate\Support\Collection<int, object> $personnel */
        $personnel = $context['personnel'];

        $cnsd = [];
        $tfp  = [];
        foreach ($personnel as $p) {
            $entry = [
                'name'           => (string) ($p->name ?? '—'),
                'role'           => (string) ($p->role ?? ''),
                'employee_type'  => (string) ($p->employee_type ?? ''),
            ];
            $type = (string) ($p->employee_type ?? '');
            if ($type === 'CNS') {
                $cnsd[] = $entry;
            } elseif ($type === 'Support') {
                $tfp[] = $entry;
            }
        }

        $managerObj = $context['manager'] ?? null;
        $supCnsdObj = $context['supervisor_cnsd'] ?? null;
        $supTfpObj  = $context['supervisor_tfp'] ?? null;

        return [
            'roster_available' => (bool) ($context['roster_available'] ?? false),
            'manager'          => $managerObj ? ['name' => (string) $managerObj->name] : null,
            'supervisor_cnsd'  => $supCnsdObj ? ['name' => (string) $supCnsdObj->name] : null,
            'supervisor_tfp'   => $supTfpObj  ? ['name' => (string) $supTfpObj->name]  : null,
            'cnsd'             => $cnsd,
            'tfp'              => $tfp,
        ];
    }

    /**
     * Shift checklist with rolled-up counts (done/total) per category, so the
     * kiosk doesn't need to recompute on the client. Items are sourced from
     * the editable dashboard_checklist_items table via DashboardChecklistService.
     */
    private function checklistBlock(string $shift, string $date): array
    {
        return $this->checklistService->buildForShift($date, $shift);
    }

    /**
     * Active work orders surfaced on the kiosk.
     *
     * Filter rule: show today's ongoing WOs PLUS *every* on_hold WO regardless
     * of date. On-hold WOs are the signature backlog — they accumulate across
     * shifts until MT/Supervisor closes them out, so a date filter would hide
     * the exact pile the workshop needs to clear.
     *
     * Sort: on_hold first (more urgent), then by creation desc.
     * Each row carries a per-role signature_status flag so the kiosk can
     * render "who's still owed a TTD" without a second round trip.
     */
    private function workOrdersBlock(string $date): array
    {
        $records = WorkOrder::query()
            ->where(function ($q) use ($date) {
                // Anything still on_hold, regardless of date — the signature
                // backlog that MT must clear.
                $q->where('status', 'on_hold')
                  // Or ongoing AND scheduled for today.
                  ->orWhere(function ($q2) use ($date) {
                      $q2->where('status', 'ongoing')
                         ->whereDate('shift_date', $date);
                  });
            })
            ->with(['manager:id,name', 'creator:id,name,role'])
            ->orderByRaw("CASE status WHEN 'on_hold' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->limit(8)
            ->get([
                'id', 'wo_number', 'wo_type', 'division', 'shift_type',
                'shift_date',
                'description', 'status', 'manager_id', 'manager_name_snapshot',
                'technician_name', 'has_supervisor', 'created_at', 'created_by',
                'mt_signature', 'supervisor_signature', 'technician_signature',
            ]);

        return $records->map(function (WorkOrder $wo) {
            $isGmDirective = $wo->wo_type === 'gm_directive'
                || (($wo->creator?->role ?? null) === 'General Manager');

            // Signature status per role (null = not required for this WO).
            // GM directives don't require any signatures.
            $sig = [
                'technician' => null,
                'supervisor' => null,
                'mt'         => null,
            ];
            if (!$isGmDirective) {
                $sig['technician'] = !empty($wo->technician_signature);
                $sig['mt']         = !empty($wo->mt_signature);
                // Supervisor only required when has_supervisor flag is true.
                $sig['supervisor'] = $wo->has_supervisor
                    ? !empty($wo->supervisor_signature)
                    : null;
            }

            return [
                'id'               => $wo->id,
                'wo_number'        => $wo->wo_number,
                'wo_type'          => $wo->wo_type,
                'division'         => $wo->division,
                'shift_type'       => $wo->shift_type,
                'shift_date'       => $wo->shift_date?->format('Y-m-d'),
                'description'      => $this->firstLine($wo->description),
                'status'           => $wo->status,
                'manager_name'     => $wo->manager?->name ?? $wo->manager_name_snapshot,
                'technician_name'  => $wo->technician_name,
                'is_gm_directive'  => $isGmDirective,
                'signature_status' => $sig,
            ];
        })->values()->toArray();
    }

    /**
     * Latest damage reports across both facilities. The kiosk shows the
     * most recent N regardless of completion status — staff want to see
     * "any new damage since last shift" at a glance.
     */
    private function damageReportsBlock(): array
    {
        $records = ReportingDamageReport::query()
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get([
                'id', 'report_number', 'report_date', 'equipment_name',
                'facility', 'location', 'damage_category', 'status',
            ]);

        return $records->map(fn (ReportingDamageReport $r) => [
            'id'              => $r->id,
            'report_number'   => $r->report_number,
            'report_date'     => $r->report_date,
            'equipment_name'  => $r->equipment_name,
            'facility'        => $r->facility,
            'location'        => $r->location,
            'damage_category' => $r->damage_category,
            'status'          => $r->status,
        ])->values()->toArray();
    }

    /**
     * Combined logbook timeline (CNSD + TFP) for today, with yesterday's
     * malam fallback when today is still empty. Mirrors DashboardController
     * logbookSummary behavior so the kiosk feels consistent with the
     * authenticated dashboard.
     */
    private function logbookBlock(string $date): array
    {
        $notes = $this->collectNotes($date);
        $isFallback = false;
        $sourceDate = $date;
        $fallbackShift = null;

        if (empty($notes)) {
            $yesterday = Carbon::parse($date)->subDay()->format('Y-m-d');
            $fallback = $this->collectNotes($yesterday, 'malam');
            if (!empty($fallback)) {
                $notes = $fallback;
                $isFallback = true;
                $sourceDate = $yesterday;
                $fallbackShift = 'malam';
            }
        }

        $shiftRank = ['pagi' => 0, 'siang' => 1, 'malam' => 2];
        usort($notes, function ($a, $b) use ($shiftRank) {
            $sa = $shiftRank[$a['shift']] ?? -1;
            $sb = $shiftRank[$b['shift']] ?? -1;
            if ($sa !== $sb) return $sb <=> $sa;
            return strcmp((string) $b['time'], (string) $a['time']);
        });

        $cnsdCount = count(array_filter($notes, fn ($n) => $n['division'] === 'CNSD'));
        $tfpCount  = count(array_filter($notes, fn ($n) => $n['division'] === 'TFP'));

        return [
            'date'           => $date,
            'source_date'    => $sourceDate,
            'is_fallback'    => $isFallback,
            'fallback_shift' => $fallbackShift,
            'total_count'    => count($notes),
            'cnsd_count'     => $cnsdCount,
            'tfp_count'      => $tfpCount,
            'notes'          => array_slice($notes, 0, 12),
        ];
    }

    /**
     * @return array<int, array{division:string,shift:string,time:?string,activity:string,is_auto:bool}>
     */
    private function collectNotes(string $date, ?string $onlyShift = null): array
    {
        $out = [];

        $cnsd = LogbookCnsdNote::query()
            ->whereHas('logbook', fn ($q) => $q->whereDate('date', $date))
            ->when($onlyShift, fn ($q) => $q->where('shift', $onlyShift))
            ->orderBy('shift')
            ->orderBy('time')
            ->get(['id', 'shift', 'time', 'activity']);

        foreach ($cnsd as $n) {
            $out[] = [
                'division' => 'CNSD',
                'shift'    => (string) $n->shift,
                'time'     => $n->time,
                'activity' => (string) $n->activity,
                'is_auto'  => str_starts_with((string) $n->activity, '[Auto]'),
            ];
        }

        $tfp = LogbookTfpNote::query()
            ->whereHas('logbook', fn ($q) => $q->whereDate('date', $date))
            ->when($onlyShift, fn ($q) => $q->where('shift', $onlyShift))
            ->orderBy('shift')
            ->orderBy('time')
            ->get(['id', 'shift', 'time', 'activity']);

        foreach ($tfp as $n) {
            $out[] = [
                'division' => 'TFP',
                'shift'    => (string) $n->shift,
                'time'     => $n->time,
                'activity' => (string) $n->activity,
                'is_auto'  => str_starts_with((string) $n->activity, '[Auto]'),
            ];
        }

        return $out;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function firstLine(?string $text): string
    {
        if (!$text) return '';
        $first = strtok($text, "\n");
        return $first === false ? '' : trim($first);
    }

    private function resolveDate(mixed $raw): string
    {
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }
        return Carbon::now()->format('Y-m-d');
    }

    private function resolveShift(mixed $raw): string
    {
        if (in_array($raw, ['pagi', 'siang', 'malam'], true)) {
            return (string) $raw;
        }
        $hour = (int) Carbon::now()->format('G');
        return match (true) {
            $hour >= 7  && $hour < 13 => 'pagi',
            $hour >= 13 && $hour < 19 => 'siang',
            default                   => 'malam',
        };
    }
}
