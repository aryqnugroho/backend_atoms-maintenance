<?php

namespace Database\Seeders;

use App\Models\LocalUser;
use App\Models\WorkOrder\WorkOrder;
use App\Models\WorkOrder\WorkOrderOutput;
use App\Models\WorkOrder\WorkOrderPersonnel;
use Illuminate\Database\Seeder;

class WorkOrderSeeder extends Seeder
{
    /**
     * Seed sample work orders matching frontend mockData.ts.
     */
    public function run(): void
    {
        $workOrders = [
            [
                'wo_number' => 'WO-CNSD-12-04-2026-001',
                'wo_type' => 'shift',
                'division' => 'CNSD',
                'shift_type' => 'pagi',
                'shift_date' => '2026-04-12',
                'description' => 'Pemeriksaan VCCS Merk Frequentis dan Voice Recorder. Pastikan server A & B berfungsi normal.',
                'status' => 'ongoing',
                'manager_id' => $this->getUserId(1),
                'supervisor_id' => $this->getUserId(2),
                'manager_name_snapshot' => 'Dudik Fahrudin',
                'supervisor_name_snapshot' => 'Moch. Ichsan',
                'created_by' => $this->getUserId(2),
                'created_at' => '2026-04-12 07:15:00',
                'updated_at' => '2026-04-12 07:15:00',
                'personnel' => [
                    ['user_id' => $this->getUserId(4), 'role_label' => 'Teknisi 1'],
                    ['user_id' => $this->getUserId(6), 'role_label' => 'Teknisi 2'],
                ],
                'output_types' => ['meter_reading', 'status_peralatan'],
            ],
            [
                'wo_number' => 'WO-TFP-12-04-2026-001',
                'wo_type' => 'shift',
                'division' => 'TFP',
                'shift_type' => 'pagi',
                'shift_date' => '2026-04-12',
                'description' => 'Pengecekan UPS Tescom A dan B. Verifikasi tegangan battery dan arus battery.',
                'status' => 'ongoing',
                'start_time' => '07:30',
                'manager_id' => $this->getUserId(1),
                'supervisor_id' => $this->getUserId(3),
                'manager_name_snapshot' => 'Dudik Fahrudin',
                'supervisor_name_snapshot' => 'Fajar Kusuma W',
                'created_by' => $this->getUserId(3),
                'created_at' => '2026-04-12 07:20:00',
                'updated_at' => '2026-04-12 07:30:00',
                'personnel' => [
                    ['user_id' => $this->getUserId(5), 'role_label' => 'Teknisi 1'],
                ],
                'output_types' => ['meter_reading', 'logbook'],
            ],
            [
                'wo_number' => 'WO-CNSD-11-04-2026-002',
                'wo_type' => 'shift',
                'division' => 'CNSD',
                'shift_type' => 'malam',
                'shift_date' => '2026-04-11',
                'description' => 'Perbaikan MSSR Main-Standby switching. Koordinasi dengan tim radar.',
                'status' => 'on_hold',
                'start_time' => '19:45',
                'end_time' => '23:30',
                'completion_status' => 'belum_selesai_dilanjut',
                'notes_kendala' => 'Sparepart switching module belum tersedia. Sementara menggunakan manual switching.',
                'notes_usulan' => 'Segera pesan switching module dari gudang pusat.',
                'manager_id' => $this->getUserId(1),
                'supervisor_id' => $this->getUserId(2),
                'manager_name_snapshot' => 'Dudik Fahrudin',
                'supervisor_name_snapshot' => 'Moch. Ichsan',
                'created_by' => $this->getUserId(2),
                'created_at' => '2026-04-11 19:30:00',
                'updated_at' => '2026-04-11 23:35:00',
                'personnel' => [
                    ['user_id' => $this->getUserId(4), 'role_label' => 'Teknisi 1'],
                ],
                'output_types' => ['status_peralatan', 'logbook'],
            ],
            [
                'wo_number' => 'WO-CNSD-12-04-2026-P001',
                'wo_type' => 'personal',
                'division' => 'CNSD',
                'shift_type' => 'pagi',
                'shift_date' => '2026-04-12',
                'assigned_technician_id' => $this->getUserId(4),
                'description' => 'Kalibrasi CDU Secondary setelah drift frekuensi. Pastikan frekuensi kembali ke toleransi normal.',
                'status' => 'ongoing',
                'manager_id' => $this->getUserId(1),
                'supervisor_id' => $this->getUserId(2),
                'manager_name_snapshot' => 'Dudik Fahrudin',
                'supervisor_name_snapshot' => 'Moch. Ichsan',
                'created_by' => $this->getUserId(1),
                'created_at' => '2026-04-12 08:00:00',
                'updated_at' => '2026-04-12 08:00:00',
                'personnel' => [
                    ['user_id' => $this->getUserId(4), 'role_label' => 'Teknisi'],
                ],
                'output_types' => ['meter_reading', 'status_peralatan'],
            ],
            [
                'wo_number' => 'WO-TFP-10-04-2026-001',
                'wo_type' => 'shift',
                'division' => 'TFP',
                'shift_type' => 'siang',
                'shift_date' => '2026-04-10',
                'description' => 'Pemeliharaan rutin AC Split Wall 01-04 ruang equipment AOB Ground.',
                'status' => 'completed',
                'start_time' => '13:15',
                'end_time' => '16:00',
                'completion_status' => 'selesai',
                'notes_kendala' => 'AC 03 filter perlu diganti dalam 2 minggu ke depan.',
                'notes_pemberi_tugas' => 'Noted, siapkan SPK pembelian filter AC.',
                'manager_id' => $this->getUserId(1),
                'supervisor_id' => $this->getUserId(3),
                'manager_name_snapshot' => 'Dudik Fahrudin',
                'supervisor_name_snapshot' => 'Fajar Kusuma W',
                'created_by' => $this->getUserId(3),
                'closed_at' => '2026-04-10 16:30:00',
                'created_at' => '2026-04-10 13:00:00',
                'updated_at' => '2026-04-10 16:30:00',
                'personnel' => [
                    ['user_id' => $this->getUserId(5), 'role_label' => 'Teknisi 1'],
                ],
                'output_types' => ['logbook', 'other'],
                'output_other' => 'Laporan pemeliharaan AC',
            ],
        ];

        foreach ($workOrders as $woData) {
            $personnel = $woData['personnel'] ?? [];
            $outputTypes = $woData['output_types'] ?? [];
            $outputOther = $woData['output_other'] ?? null;

            unset($woData['personnel'], $woData['output_types'], $woData['output_other']);

            $wo = WorkOrder::create($woData);

            // Create personnel assignments
            foreach ($personnel as $person) {
                WorkOrderPersonnel::create([
                    'work_order_id' => $wo->id,
                    'user_id' => $person['user_id'],
                    'role_label' => $person['role_label'],
                ]);
            }

            // Create output type records
            foreach ($outputTypes as $type) {
                WorkOrderOutput::create([
                    'work_order_id' => $wo->id,
                    'output_type' => $type,
                    'output_other' => ($type === 'other') ? $outputOther : null,
                ]);
            }
        }
    }

    /**
     * Get local_user ID by rostering_user_id.
     */
    private function getUserId(int $rosteringId): int
    {
        return LocalUser::where('rostering_user_id', $rosteringId)->value('id') ?? $rosteringId;
    }
}
