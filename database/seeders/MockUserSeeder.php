<?php

namespace Database\Seeders;

use App\Models\LocalUser;
use Illuminate\Database\Seeder;

class MockUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
