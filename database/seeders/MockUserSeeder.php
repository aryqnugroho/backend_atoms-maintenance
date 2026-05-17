<?php

namespace Database\Seeders;

use App\Models\LocalUser;
use Illuminate\Database\Seeder;

/**
 * @deprecated 2026-05-16
 *
 * This seeder produces STALE local_users rows whose `rostering_user_id` does
 * NOT match the live atoms-rostering data (mis. local Moch. Ichsan claims
 * rostering_user_id=2 but the real ID is 9). Running it will create incorrect
 * mappings.
 *
 * The seeder is retained only as a historical reference. Use these instead:
 *
 *   - SSO login (auto-upsert via RosteringAuthService::buildTransientUser)
 *   - Work Order create (LocalUserResolver lazy-creates referenced users)
 *   - `php artisan local-users:sync` (bulk pull from rostering)
 *
 * NOT registered in DatabaseSeeder. If invoked manually, prints a warning.
 */
class MockUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn('MockUserSeeder is deprecated. Use `php artisan local-users:sync` instead.');

        $mockUsers = [
            [
                'rostering_user_id' => 1,
                'name' => 'Dudik Fahrudin',
                'email' => 'dudik@airnav.co.id',
                'role' => 'Manager Teknik',
                'division' => 'Management',
            ],
            [
                'rostering_user_id' => 2,
                'name' => 'Moch. Ichsan',
                'email' => 'ichsan@airnav.co.id',
                'role' => 'Supervisor CNSD',
                'division' => 'CNSD',
            ],
            [
                'rostering_user_id' => 3,
                'name' => 'Fajar Kusuma W',
                'email' => 'fajar@airnav.co.id',
                'role' => 'Supervisor TFP',
                'division' => 'TFP',
            ],
            [
                'rostering_user_id' => 4,
                'name' => 'Khoirul M.A',
                'email' => 'khoirul@airnav.co.id',
                'role' => 'Teknisi CNSD',
                'division' => 'CNSD',
            ],
            [
                'rostering_user_id' => 5,
                'name' => 'Iqbal Mustika',
                'email' => 'iqbal@airnav.co.id',
                'role' => 'Teknisi TFP',
                'division' => 'TFP',
            ],
            [
                'rostering_user_id' => 6,
                'name' => 'Argo Pragolo',
                'email' => 'argo@airnav.co.id',
                'role' => 'Teknisi CNSD',
                'division' => 'CNSD',
            ],
            [
                'rostering_user_id' => 7,
                'name' => 'Admin System',
                'email' => 'admin@airnav.co.id',
                'role' => 'Admin',
                'division' => null,
            ],
        ];

        foreach ($mockUsers as $user) {
            LocalUser::updateOrCreate(
                ['rostering_user_id' => $user['rostering_user_id']],
                $user
            );
        }
    }
}
