<?php

namespace App\Console\Commands;

use App\Models\LocalUser;
use App\Models\WorkOrder\WorkOrder;
use App\Models\WorkOrder\WorkOrderPersonnel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan local-users:cleanup
 *
 * Detects local_users rows whose `rostering_user_id` does NOT match the
 * authoritative rostering value. Typical cause: legacy MockUserSeeder.
 *
 * Strategy (safe by default):
 *   1. Find rows where local_users.name has a duplicate with a different
 *      rostering_user_id, AND only one of those rows actually exists in
 *      atoms-rostering with that ID.
 *   2. Determine which row is "authoritative" by cross-checking against the
 *      rostering DB.
 *   3. If the stale row has NO foreign-key references (Work Order, signature,
 *      personnel), delete it.
 *   4. If it does have references, leave it alone — manual data migration is
 *      required for that case (we never break FK relationships).
 *
 * Always read-only against db_rostering.
 *
 * Use --dry-run to preview; --force to actually delete.
 */
class CleanupLocalUsersCommand extends Command
{
    protected $signature = 'local-users:cleanup
                            {--dry-run : Preview the actions without writing}
                            {--force : Actually perform the cleanup}';

    protected $description = 'Detect and remove stale duplicate local_users rows whose rostering_user_id does not match live rostering data.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (!$dry && !$force) {
            $this->error('Pass --dry-run to preview or --force to apply changes.');
            return self::FAILURE;
        }

        $this->info('Scanning for duplicate local_users by name...');

        $dupeNames = LocalUser::query()
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        if ($dupeNames->isEmpty()) {
            $this->info('No duplicate names found. Nothing to clean up.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d duplicate name(s).', $dupeNames->count()));

        $deletedCount = 0;
        $keptCount = 0;
        $blockedCount = 0;
        $report = [];

        foreach ($dupeNames as $name) {
            $rows = LocalUser::where('name', $name)->orderBy('id')->get();
            $rosteringIds = $rows->pluck('rostering_user_id')->all();

            // Check which rostering_user_ids actually exist in rostering
            try {
                $authoritativeIds = DB::connection('rostering')
                    ->table('users')
                    ->whereIn('id', $rosteringIds)
                    ->whereNull('deleted_at')
                    ->where('name', $name)
                    ->pluck('id')
                    ->all();
            } catch (\Throwable $e) {
                $report[] = "[skip] {$name}: cannot query rostering — {$e->getMessage()}";
                continue;
            }

            foreach ($rows as $row) {
                if (in_array($row->rostering_user_id, $authoritativeIds, true)) {
                    $keptCount++;
                    continue;
                }

                // This row is suspect. Check for FK references before deleting.
                $woAsManager = WorkOrder::where('manager_id', $row->id)->withTrashed()->count();
                $woAsSupervisor = WorkOrder::where('supervisor_id', $row->id)->withTrashed()->count();
                $woAsTechnician = WorkOrder::where('assigned_technician_id', $row->id)->withTrashed()->count();
                $woAsCreator = WorkOrder::where('created_by', $row->id)->withTrashed()->count();
                $personnelRefs = WorkOrderPersonnel::where('user_id', $row->id)->count();
                $signatureRefs = WorkOrder::where('mt_signed_by', $row->id)
                    ->orWhere('supervisor_signed_by', $row->id)
                    ->orWhere('technician_signed_by', $row->id)
                    ->withTrashed()->count();

                $totalRefs = $woAsManager + $woAsSupervisor + $woAsTechnician
                    + $woAsCreator + $personnelRefs + $signatureRefs;

                if ($totalRefs > 0) {
                    $blockedCount++;
                    $report[] = sprintf(
                        '[blocked] local_id=%d (name="%s", rostering_user_id=%d) has %d FK references — keeping',
                        $row->id, $row->name, $row->rostering_user_id, $totalRefs
                    );
                    continue;
                }

                if ($dry) {
                    $deletedCount++;
                    $report[] = sprintf(
                        '[dry] would delete local_id=%d (name="%s", rostering_user_id=%d)',
                        $row->id, $row->name, $row->rostering_user_id
                    );
                } else {
                    $row->forceDelete();
                    $deletedCount++;
                    $report[] = sprintf(
                        '[deleted] local_id=%d (name="%s", rostering_user_id=%d)',
                        $row->id, $row->name, $row->rostering_user_id
                    );
                }
            }
        }

        $this->newLine();
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('  local-users:cleanup summary');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line(sprintf('  Kept (authoritative) : %d', $keptCount));
        $this->line(sprintf('  Deleted              : %d', $deletedCount));
        $this->line(sprintf('  Blocked (has FKs)    : %d', $blockedCount));
        if ($dry) {
            $this->warn('  DRY RUN — no changes written.');
        }
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if (!empty($report)) {
            $this->newLine();
            foreach ($report as $line) {
                $this->line($line);
            }
        }

        return self::SUCCESS;
    }
}
