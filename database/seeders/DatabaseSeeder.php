<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * DatabaseSeeder — atoms-maintenance.
 *
 * Default behaviour: seed NOTHING. The maintenance database is provisioned
 * empty and populated lazily as users interact with the system:
 *
 * - `local_users` is filled by:
 *     1. SSO login (RosteringAuthService::buildTransientUser upserts on each login)
 *     2. Work Order create (LocalUserResolver lazy-creates referenced personnel)
 *     3. `php artisan local-users:sync` (bulk pull from atoms-rostering)
 *
 * - `work_orders` and related tables (work_order_personnel, work_order_outputs,
 *   notifications) MUST start empty. There is no dummy Work Order seed.
 *
 * The historical `MockUserSeeder` and `WorkOrderSeeder` files are retained as
 * read-only references but are NOT called from here. They are deprecated and
 * should not be reintroduced — running them produces stale/incorrect data
 * (rostering_user_id values that don't match live rostering).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Intentionally empty.
        //
        // If you genuinely need to seed sample data in a *throwaway local* env,
        // call individual seeders explicitly via:
        //   php artisan db:seed --class=Database\\Seeders\\MockUserSeeder
        // Be aware that MockUserSeeder writes incorrect rostering_user_ids; use
        // `php artisan local-users:sync` instead for accurate data.
    }
}
