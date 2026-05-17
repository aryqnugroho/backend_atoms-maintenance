<?php

namespace Database\Seeders;

use App\Models\LocalUser;
use App\Models\WorkOrder\WorkOrder;
use App\Models\WorkOrder\WorkOrderOutput;
use App\Models\WorkOrder\WorkOrderPersonnel;
use Illuminate\Database\Seeder;

/**
 * @deprecated 2026-05-16
 *
 * The Work Order database starts empty by design. Do NOT register this
 * seeder in DatabaseSeeder. It exists only as a historical reference for
 * the Work Order signature/status demo flow.
 *
 * Sample data should be created through the real UI flow so that
 * personnel mappings reflect live rostering data.
 */
class WorkOrderSeeder extends Seeder
{
    /**
     * Seed focused Work Order demo data for signature/status testing.
     */
    public function run(): void
    {
        $this->command?->warn('WorkOrderSeeder is deprecated. Work Orders should start empty.');

        $mt = $this->upsertUser(1, 'Dudik Fahrudin', 'dudik@airnav.co.id', 'Manager Teknik', 'Management');
        $supervisor = $this->upsertUser(2, 'Moch. Ichsan', 'ichsan@airnav.co.id', 'Supervisor CNSD', 'CNSD');
        $technician = $this->upsertUser(4, 'Khoirul M.A', 'khoirul@airnav.co.id', 'Teknisi CNSD', 'CNSD');

        $this->seedWorkOrder([
            'wo_number' => 'WO-CNSD-31-12-2026-001',
            'wo_type' => 'shift',
            'division' => 'CNSD',
            'shift_id' => 1,
            'shift_type' => 'pagi',
            'shift_date' => '2026-12-31',
            'description' => 'Pemeriksaan kesiapan VCCS dan Voice Recorder untuk data dummy signature ongoing.',
            'manager_id' => $mt->id,
            'supervisor_id' => $supervisor->id,
            'assigned_technician_id' => $technician->id,
            'has_supervisor' => true,
            'manager_name_snapshot' => $mt->name,
            'supervisor_name_snapshot' => $supervisor->name,
            'mt_name' => $mt->name,
            'supervisor_name' => $supervisor->name,
            'technician_name' => $technician->name,
            'created_by' => $supervisor->id,
            'personnel' => [
                ['user_id' => $technician->id, 'role_label' => 'Teknisi 1'],
            ],
            'output_types' => ['meter_reading', 'status_peralatan'],
        ]);

        $this->seedWorkOrder([
            'wo_number' => 'WO-CNSD-01-05-2026-001',
            'wo_type' => 'shift',
            'division' => 'CNSD',
            'shift_id' => 1,
            'shift_type' => 'pagi',
            'shift_date' => '2026-05-01',
            'description' => 'Perbaikan MSSR switching sebagai data dummy signature on hold.',
            'manager_id' => $mt->id,
            'supervisor_id' => $supervisor->id,
            'assigned_technician_id' => $technician->id,
            'has_supervisor' => true,
            'manager_name_snapshot' => $mt->name,
            'supervisor_name_snapshot' => $supervisor->name,
            'mt_name' => $mt->name,
            'supervisor_name' => $supervisor->name,
            'technician_name' => $technician->name,
            'start_time' => '07:30',
            'end_time' => '12:15',
            'completion_status' => 'belum_selesai_dilanjut',
            'notes_kendala' => 'Signature belum lengkap setelah shift selesai.',
            'created_by' => $supervisor->id,
            'personnel' => [
                ['user_id' => $technician->id, 'role_label' => 'Teknisi 1'],
            ],
            'output_types' => ['status_peralatan'],
        ]);
    }

    private function upsertUser(int $rosteringId, string $name, string $email, string $role, ?string $division): LocalUser
    {
        return LocalUser::updateOrCreate(
            ['rostering_user_id' => $rosteringId],
            [
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'division' => $division,
                'is_active' => true,
                'synced_at' => now(),
            ]
        );
    }

    private function seedWorkOrder(array $data): void
    {
        $personnel = $data['personnel'];
        $outputTypes = $data['output_types'];
        unset($data['personnel'], $data['output_types']);

        $workOrder = WorkOrder::updateOrCreate(
            ['wo_number' => $data['wo_number']],
            array_merge($data, [
                'status' => 'ongoing',
                'closed_at' => null,
            ])
        );

        $workOrder->status = $workOrder->recalculateStatus();
        $workOrder->save();

        WorkOrderPersonnel::where('work_order_id', $workOrder->id)->delete();
        foreach ($personnel as $person) {
            WorkOrderPersonnel::create([
                'work_order_id' => $workOrder->id,
                'user_id' => $person['user_id'],
                'role_label' => $person['role_label'],
            ]);
        }

        WorkOrderOutput::where('work_order_id', $workOrder->id)->delete();
        foreach ($outputTypes as $type) {
            WorkOrderOutput::create([
                'work_order_id' => $workOrder->id,
                'output_type' => $type,
                'output_other' => null,
            ]);
        }
    }
}
