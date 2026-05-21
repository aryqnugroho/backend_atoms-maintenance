<?php

namespace App\Console\Commands;

use App\Models\LocalUser;
use App\Models\WorkOrder\WorkOrder;
use App\Services\NotificationService;
use App\Services\RosteringIntegrationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * php artisan work-orders:notify-shift-ending
 *
 * Designed to be scheduled every minute. For each active shift whose end time
 * is between 9 and 11 minutes from "now", finds any Work Order that:
 *   - belongs to that shift (shift_date + shift_type)
 *   - is not fully signed (mt / supervisor / technician)
 *
 * and notifies each role-holder whose own signature is still pending.
 *
 * Idempotency: NotificationService::notifyShiftEndingUnsigned skips users
 * who already received a reminder for the same WO + role in the last 30
 * minutes, so duplicate cron ticks inside the [9, 11] minute window are
 * harmless.
 */
class NotifyShiftEndingReminderCommand extends Command
{
    protected $signature = 'work-orders:notify-shift-ending
                            {--shift= : Override shift (pagi|siang|malam) for manual testing}
                            {--date=  : Override shift_date (Y-m-d) for manual testing}';

    protected $description = 'Send a 10-minute-before-shift-end reminder to role-holders whose signature on a Work Order is still pending.';

    public function handle(RosteringIntegrationService $rostering, NotificationService $notifier): int
    {
        // Determine which (shift_type, shift_date) tuples are "10 minutes from
        // ending right now". In normal cron mode we evaluate all three shifts;
        // overrides exist only for manual testing.
        $now = Carbon::now();

        $candidates = $this->resolveTargetShifts($rostering, $now);

        if (empty($candidates)) {
            $this->info('No shifts ending in the [9, 11] minute window. Nothing to do.');
            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;

        foreach ($candidates as $target) {
            $shiftType = $target['shift_type'];
            $shiftDate = $target['shift_date'];

            $workOrders = WorkOrder::where('shift_type', $shiftType)
                ->where('shift_date', $shiftDate)
                ->where('status', '!=', 'completed')
                ->whereNotNull('shift_date')
                ->get();

            if ($workOrders->isEmpty()) {
                continue;
            }

            $this->line(sprintf('Shift %s @ %s — %d candidate WO(s)', $shiftType, $shiftDate, $workOrders->count()));

            foreach ($workOrders as $wo) {
                foreach ($this->pendingRoleHolders($wo) as [$role, $user]) {
                    $didSend = $notifier->notifyShiftEndingUnsigned($wo, $user, $role);
                    if ($didSend) {
                        $sent++;
                    } else {
                        $skipped++;
                    }
                }
            }
        }

        $this->info("Done. sent={$sent}, skipped(duplicates)={$skipped}");
        return self::SUCCESS;
    }

    /**
     * Identify shift/date pairs whose end time is between 9 and 11 minutes
     * from now. Uses RosteringIntegrationService for real shift times, with
     * the same hardcoded fallback as isShiftEnded().
     *
     * @return array<int, array{shift_type: string, shift_date: string}>
     */
    private function resolveTargetShifts(RosteringIntegrationService $rostering, Carbon $now): array
    {
        // Manual override (for php artisan testing)
        $shiftOverride = $this->option('shift');
        $dateOverride  = $this->option('date');
        if ($shiftOverride && $dateOverride) {
            return [[
                'shift_type' => $shiftOverride,
                'shift_date' => $dateOverride,
            ]];
        }

        $fallback = [
            'pagi'  => '13:00',
            'siang' => '19:00',
            'malam' => '07:00',
        ];

        $result = [];

        foreach (['pagi', 'siang', 'malam'] as $shift) {
            $times = $rostering->getShiftTimes($shift);
            $endStr = substr($times['end_time'] ?? $fallback[$shift], 0, 5);

            // Malam ends the next calendar day. We evaluate two candidate
            // dates (today + yesterday) and pick whichever falls into the
            // [9, 11]-minute pre-end window.
            $datesToCheck = $shift === 'malam'
                ? [$now->copy()->subDay()->format('Y-m-d'), $now->format('Y-m-d')]
                : [$now->format('Y-m-d')];

            foreach ($datesToCheck as $shiftDate) {
                $endAt = $shift === 'malam'
                    ? Carbon::parse(Carbon::parse($shiftDate)->addDay()->format('Y-m-d') . ' ' . $endStr)
                    : Carbon::parse($shiftDate . ' ' . $endStr);

                $minutesUntilEnd = $now->diffInMinutes($endAt, false);
                if ($minutesUntilEnd >= 9 && $minutesUntilEnd <= 11) {
                    $result[] = [
                        'shift_type' => $shift,
                        'shift_date' => $shiftDate,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * For a WO, return the list of (role, LocalUser) pairs where the role
     * still needs to sign. Skips roles that are not required for this WO.
     *
     * @return array<int, array{0:string, 1: LocalUser}>
     */
    private function pendingRoleHolders(WorkOrder $wo): array
    {
        $pending = [];

        $required = $wo->getRequiredSignatures(); // ['mt','supervisor','technician'] subset

        if (in_array('mt', $required, true) && empty($wo->mt_signature) && $wo->manager_id) {
            $u = LocalUser::find($wo->manager_id);
            if ($u) { $pending[] = ['mt', $u]; }
        }

        if (in_array('supervisor', $required, true) && empty($wo->supervisor_signature) && $wo->supervisor_id) {
            $u = LocalUser::find($wo->supervisor_id);
            if ($u) { $pending[] = ['supervisor', $u]; }
        }

        if (in_array('technician', $required, true) && empty($wo->technician_signature)) {
            // Personal WO: assigned_technician_id holds the single technician.
            // Shift WO: notify each WorkOrderPersonnel user.
            if ($wo->assigned_technician_id) {
                $u = LocalUser::find($wo->assigned_technician_id);
                if ($u) { $pending[] = ['technician', $u]; }
            } else {
                $wo->loadMissing('personnel.user');
                foreach ($wo->personnel as $p) {
                    if ($p->user) { $pending[] = ['technician', $p->user]; }
                }
            }
        }

        return $pending;
    }
}
