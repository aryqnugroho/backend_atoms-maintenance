<?php

namespace App\Console\Commands;

use App\Models\LocalUser;
use App\Services\LocalUserResolver;
use App\Services\RosteringIntegrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan local-users:sync
 *
 * Bulk-pull active users from atoms-rostering (read-only) and upsert them
 * into local_users via LocalUserResolver. The rostering DB is the source of
 * truth — local_users is just a maintenance-side cache for foreign keys.
 *
 * Side-effects: writes only to maintenance DB. Never to db_rostering.
 *
 * Output: counts of created / updated / skipped rows, plus warnings for
 * suspicious entries (no rostering match, duplicate names with mismatched
 * rostering_user_id, etc.)
 */
class SyncLocalUsersCommand extends Command
{
    protected $signature = 'local-users:sync
                            {--prune-stale : Mark local_users that no longer exist in rostering as inactive (does not delete)}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Sync local_users from atoms-rostering (read-only). Lazy create + bulk update.';

    public function handle(RosteringIntegrationService $rostering, LocalUserResolver $resolver): int
    {
        $dry = (bool) $this->option('dry-run');

        $this->info('Pulling active users from atoms-rostering...');
        $users = $rostering->getAllActiveUsers();

        if ($users->isEmpty()) {
            $this->warn('No users returned from rostering. Aborting.');
            return self::FAILURE;
        }

        $this->info(sprintf('Got %d users from rostering.', $users->count()));

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $warnings = [];

        // Track rostering_user_ids we've seen for the prune step
        $seenRosteringIds = [];

        foreach ($users as $row) {
            $rid = (int) $row->rostering_user_id;
            $seenRosteringIds[] = $rid;

            $existing = LocalUser::where('rostering_user_id', $rid)->first();

            if ($dry) {
                if (!$existing) {
                    $created++;
                } else {
                    $updated++;
                }
                continue;
            }

            $local = $resolver->ensureLocalUser($rid);
            if (!$local) {
                $skipped++;
                $warnings[] = "rostering_user_id={$rid} could not be resolved";
                continue;
            }

            // ensureLocalUser created or updated — figure out which
            if (!$existing) {
                $created++;
            } else {
                // updateOrCreate touched the row — synced_at always changes
                $updated++;
            }
        }

        // Detect duplicates by name with different rostering_user_ids
        $dupes = LocalUser::query()
            ->select('name', DB::raw('COUNT(*) as c'))
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupes as $dupe) {
            $rows = LocalUser::where('name', $dupe->name)
                ->orderBy('id')
                ->get(['id', 'rostering_user_id', 'name', 'role']);
            $ids = $rows->map(fn ($r) => "local_id={$r->id}, rostering_user_id={$r->rostering_user_id}")->implode('; ');
            $warnings[] = "Duplicate name \"{$dupe->name}\" — {$ids}";
        }

        // Optional prune step: deactivate local_users whose rostering_user_id
        // is no longer in the active rostering set. Never deletes rows because
        // they may be foreign-keyed by Work Orders / signatures.
        $deactivated = 0;
        if ($this->option('prune-stale') && !$dry) {
            $deactivated = LocalUser::query()
                ->whereNotIn('rostering_user_id', $seenRosteringIds)
                ->where('is_active', true)
                ->update(['is_active' => false, 'synced_at' => now()]);
        }

        $this->newLine();
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('  local-users:sync summary');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line(sprintf('  Created     : %d', $created));
        $this->line(sprintf('  Updated     : %d', $updated));
        $this->line(sprintf('  Skipped     : %d', $skipped));
        if ($this->option('prune-stale')) {
            $this->line(sprintf('  Deactivated : %d (no longer in rostering)', $deactivated));
        }
        if ($dry) {
            $this->warn('  DRY RUN — no changes written.');
        }
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if (!empty($warnings)) {
            $this->newLine();
            $this->warn('Warnings:');
            foreach ($warnings as $w) {
                $this->line("  - {$w}");
            }
        }

        return self::SUCCESS;
    }
}
