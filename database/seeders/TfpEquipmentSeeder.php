<?php

namespace Database\Seeders;

use App\Models\Logbook\TfpEquipment;
use Illuminate\Database\Seeder;

/**
 * TfpEquipmentSeeder — data awal peralatan TFP berdasarkan form fisik
 * "LOG BOOK FASILITAS PENUNJANG" Perum LPPNPI Kantor Cabang Surabaya.
 *
 * Kategori dan urutan mengikuti form resmi:
 *   A. POWER CNS & OTOMASI (15 item)
 *   B. PERALATAN
 *      1. UPS & GENSET (9 item)
 *      2. MEKANIK (6 item)
 *      3. ELEKTRONIKA & IT (5 item)
 *      4. PENERANGAN (32 item — termasuk halaman 2)
 */
class TfpEquipmentSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent — skip if already seeded
        if (TfpEquipment::count() > 0) {
            $this->command->info('TfpEquipmentSeeder: already seeded, skipping.');
            return;
        }

        $data = [
            // ─── A. POWER CNS & OTOMASI ────────────────────────────
            ['category' => 'POWER CNS & OTOMASI', 'name' => 'Power Tx',          'order' => 1],
            ['category' => 'POWER CNS & OTOMASI', 'name' => 'Power Rx',          'order' => 2],
            ['category' => 'POWER CNS & OTOMASI', 'name' => 'Power Recording',   'order' => 3],
            ['category' => 'POWER CNS & OTOMASI', 'name' => 'Power VCCS',        'order' => 4],
            ['category' => 'POWER CNS & OTOMASI', 'name' => 'Power AMSC',        'order' => 5],
            ['category' => 'POWER CNS & OTOMASI', 'name' => 'Power VSAT',        'order' => 6],
            ['category' => 'POWER CNS & OTOMASI', 'name' => 'Power DVOR',        'order' => 7],
            ['category' => 'POWER CNS & OTOMASI', 'name' => 'Power Localizer',   'order' => 8],
            ['category' => 'POWER CNS & OTOMASI', 'name' => 'Power GP',          'order' => 9],
            ['category' => 'POWER CNS & OTOMASI', 'name' => 'Power MM',          'order' => 10],
            ['category' => 'POWER CNS & OTOMASI', 'name' => 'Power ARO',         'order' => 11],
            ['category' => 'POWER CNS & OTOMASI', 'name' => 'Power MSSR',        'order' => 12],
            ['category' => 'POWER CNS & OTOMASI', 'name' => 'Power ASMGCS',      'order' => 13],
            ['category' => 'POWER CNS & OTOMASI', 'name' => 'Power ATC System',  'order' => 14],
            ['category' => 'POWER CNS & OTOMASI', 'name' => 'Power PIA',         'order' => 15],

            // ─── B.1 UPS & GENSET ──────────────────────────────────
            ['category' => 'UPS & GENSET', 'name' => 'UPS U1 (TOPAZ) : Gedung TX',        'order' => 1],
            ['category' => 'UPS & GENSET', 'name' => 'UPS U3 (PILLER) : Gedung Radar',    'order' => 2],
            ['category' => 'UPS & GENSET', 'name' => 'UPS U6 (DALE) : Equipment Room',    'order' => 3],
            ['category' => 'UPS & GENSET', 'name' => 'UPS U7 (DALE) : Equipment Room',    'order' => 4],
            ['category' => 'UPS & GENSET', 'name' => 'UPS U8 (GAMA) : Equipment Room',    'order' => 5],
            ['category' => 'UPS & GENSET', 'name' => 'UPS U9 (PROTECTA) : Equipment Room','order' => 6],
            ['category' => 'UPS & GENSET', 'name' => 'UPS U2 (PILLER) : Gedung TX',       'order' => 7],
            ['category' => 'UPS & GENSET', 'name' => 'GENSET : Gedung Radar',              'order' => 8],
            ['category' => 'UPS & GENSET', 'name' => 'GENSET : Gedung DVOR',               'order' => 9],

            // ─── B.2 MEKANIK ───────────────────────────────────────
            ['category' => 'MEKANIK', 'name' => 'AC Split Duct',    'order' => 1],
            ['category' => 'MEKANIK', 'name' => 'AC Standing Floor','order' => 2],
            ['category' => 'MEKANIK', 'name' => 'AC Package',       'order' => 3],
            ['category' => 'MEKANIK', 'name' => 'AC Split Wall',     'order' => 4],
            ['category' => 'MEKANIK', 'name' => 'Exhaust Fan',       'order' => 5],
            ['category' => 'MEKANIK', 'name' => 'Lift Tower',        'order' => 6],

            // ─── B.3 ELEKTRONIKA & IT ──────────────────────────────
            ['category' => 'ELEKTRONIKA & IT', 'name' => 'Telepon',              'order' => 1],
            ['category' => 'ELEKTRONIKA & IT', 'name' => 'Door lock',            'order' => 2],
            ['category' => 'ELEKTRONIKA & IT', 'name' => 'Jaringan dan Internet','order' => 3],
            ['category' => 'ELEKTRONIKA & IT', 'name' => 'CCTV',                 'order' => 4],
            ['category' => 'ELEKTRONIKA & IT', 'name' => 'Radio Trunking',       'order' => 5],

            // ─── B.4 PENERANGAN ────────────────────────────────────
            ['category' => 'PENERANGAN', 'name' => 'Ruang Kontrol ATC Tower',    'order' => 1],
            ['category' => 'PENERANGAN', 'name' => 'Ruang Kontrol APP',          'order' => 2],
            ['category' => 'PENERANGAN', 'name' => 'Ruang Equipment AOB',        'order' => 3],
            ['category' => 'PENERANGAN', 'name' => 'Ruang Manager Teknik',       'order' => 4],
            ['category' => 'PENERANGAN', 'name' => 'Ruang Managerial Teknik',    'order' => 5],
            ['category' => 'PENERANGAN', 'name' => 'Ruang Standby Teknisi',      'order' => 6],
            ['category' => 'PENERANGAN', 'name' => 'Ruang Istirahat APP',        'order' => 7],
            ['category' => 'PENERANGAN', 'name' => 'Ruang Istirahat Tower',      'order' => 8],
            ['category' => 'PENERANGAN', 'name' => 'Ruang PIA',                  'order' => 9],
            ['category' => 'PENERANGAN', 'name' => 'Ruang ARO',                  'order' => 10],
            ['category' => 'PENERANGAN', 'name' => 'Ruang AMSC',                 'order' => 11],
            ['category' => 'PENERANGAN', 'name' => 'Ruang CBT',                  'order' => 12],
            ['category' => 'PENERANGAN', 'name' => 'Ruang K2S',                  'order' => 13],
            ['category' => 'PENERANGAN', 'name' => 'Ruang Administrasi & Keuangan','order' => 14],
            ['category' => 'PENERANGAN', 'name' => 'Ruang Managerial Operasi',   'order' => 15],
            ['category' => 'PENERANGAN', 'name' => 'Ruang GM & Sek. GM',         'order' => 16],
            ['category' => 'PENERANGAN', 'name' => 'Ruang Rapat Managerial',     'order' => 17],
            ['category' => 'PENERANGAN', 'name' => 'Ruang Rapat Operasi',        'order' => 18],
            ['category' => 'PENERANGAN', 'name' => 'Ruang Rapat Teknik',         'order' => 19],
            ['category' => 'PENERANGAN', 'name' => 'Koridor Lt.G,1,2',           'order' => 20],
            ['category' => 'PENERANGAN', 'name' => 'Toilet Lt.G,1,2',            'order' => 21],
            ['category' => 'PENERANGAN', 'name' => 'Obstacle Light',             'order' => 22],
            ['category' => 'PENERANGAN', 'name' => 'Rotating Beacon',            'order' => 23],
            ['category' => 'PENERANGAN', 'name' => 'Gedung Radar',               'order' => 24],
            ['category' => 'PENERANGAN', 'name' => 'Gedung TX',                  'order' => 25],
            ['category' => 'PENERANGAN', 'name' => 'Ruang RX',                   'order' => 26],
            ['category' => 'PENERANGAN', 'name' => 'Shelter DVOR',               'order' => 27],
            ['category' => 'PENERANGAN', 'name' => 'Shelter GP',                 'order' => 28],
            ['category' => 'PENERANGAN', 'name' => 'Shelter MM',                 'order' => 29],
            ['category' => 'PENERANGAN', 'name' => 'Shelter Localizer',          'order' => 30],
            ['category' => 'PENERANGAN', 'name' => 'Lampu Sorot Papan Nama',     'order' => 31],
            ['category' => 'PENERANGAN', 'name' => 'Lampu PJU Gedung Radar',     'order' => 32],
        ];

        $now = now();
        $rows = array_map(fn ($row) => array_merge($row, [
            'is_active'  => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]), $data);

        TfpEquipment::insert($rows);

        $this->command->info('TfpEquipmentSeeder: inserted ' . count($rows) . ' equipment records.');
    }
}
