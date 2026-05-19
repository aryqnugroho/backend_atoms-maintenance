<?php

namespace Database\Seeders;

use App\Models\Logbook\CnsdEquipment;
use Illuminate\Database\Seeder;

/**
 * CnsdEquipmentSeeder — data awal peralatan CNSD berdasarkan form fisik
 * "LOG BOOK CNS & AUTOMATION" Perum LPPNPI Kantor Cabang Surabaya.
 *
 * Kategori dan urutan mengikuti form resmi:
 *   A. COMUNICATION
 *      1. Peralatan VHF Main
 *      2. Peralatan VHF Backup
 *      3. VOICE RECORDING
 *      4. REPRODUCER ATIS
 *      5. AMSC
 *      6. VCCS
 *   B. NAVIGATION (DVOR, DME, ILS)
 *   C. SURVEILLANCE (RADAR / MSSR, ADSB)
 *   D. AUTOMATION
 *      1. ATC SYSTEM (Server, Client)
 *      2. ASMGCS (Server, Display Position)
 *   E. CHECK TEMPERATURE RUANGAN (measurement, °C)
 *
 * Total: ~100+ item.
 */
class CnsdEquipmentSeeder extends Seeder
{
    public function run(): void
    {
        if (CnsdEquipment::count() > 0) {
            $this->command->info('CnsdEquipmentSeeder: already seeded, skipping.');
            return;
        }

        $data = [
            // ════════════════════════════════════════════════════════════
            // A. COMUNICATION
            // ════════════════════════════════════════════════════════════

            // ─── A.1 Peralatan VHF Main ─────────────────────────────────
            ['category' => 'A. COMUNICATION · VHF Main', 'name' => 'VHF Ground - Primary 118.9 MHz',         'order' => 101],
            ['category' => 'A. COMUNICATION · VHF Main', 'name' => 'VHF Ground - Secondary 119.15 MHz',      'order' => 102],
            ['category' => 'A. COMUNICATION · VHF Main', 'name' => 'VHF ADC - Primary 118.3 MHz',            'order' => 103],
            ['category' => 'A. COMUNICATION · VHF Main', 'name' => 'VHF ADC - Secondary 118.3 MHz',          'order' => 104],
            ['category' => 'A. COMUNICATION · VHF Main', 'name' => 'VHF APP Director - Primary 123.2 MHz',   'order' => 105],
            ['category' => 'A. COMUNICATION · VHF Main', 'name' => 'VHF APP Director - Secondary 124.5 MHz', 'order' => 106],
            ['category' => 'A. COMUNICATION · VHF Main', 'name' => 'VHF APP West - Primary 125.1 MHz',       'order' => 107],
            ['category' => 'A. COMUNICATION · VHF Main', 'name' => 'VHF APP West - Secondary 123.55 MHz',    'order' => 108],
            ['category' => 'A. COMUNICATION · VHF Main', 'name' => 'VHF APP East - Primary 124.0 MHz',       'order' => 109],
            ['category' => 'A. COMUNICATION · VHF Main', 'name' => 'VHF APP East - Secondary 122.85 MHz',    'order' => 110],
            ['category' => 'A. COMUNICATION · VHF Main', 'name' => 'VHF CDU - Primary 121.65 MHz',           'order' => 111],
            ['category' => 'A. COMUNICATION · VHF Main', 'name' => 'VHF CDU - Secondary 121.8 MHz',          'order' => 112],
            ['category' => 'A. COMUNICATION · VHF Main', 'name' => 'VHF - ER Makasar - Primary 123.9 MHz',   'order' => 113],
            ['category' => 'A. COMUNICATION · VHF Main', 'name' => 'VHF - ER Makasar - Secondary 125.9 MHz', 'order' => 114],
            ['category' => 'A. COMUNICATION · VHF Main', 'name' => 'VHF - ER UPKN 134.1 MHz',                'order' => 115],
            ['category' => 'A. COMUNICATION · VHF Main', 'name' => 'VHF - ER UBLI 125.7 MHz',                'order' => 116],
            ['category' => 'A. COMUNICATION · VHF Main', 'name' => 'VHF Atis 128.2 MHz',                     'order' => 117],
            ['category' => 'A. COMUNICATION · VHF Main', 'name' => 'VHF Emergency 121.5 MHz',                'order' => 118],

            // ─── A.2 Peralatan VHF Backup ───────────────────────────────
            ['category' => 'A. COMUNICATION · VHF Backup', 'name' => 'VHF Ground - Primary 118.9 MHz',         'order' => 201],
            ['category' => 'A. COMUNICATION · VHF Backup', 'name' => 'VHF Ground - Secondary 119.15 MHz',      'order' => 202],
            ['category' => 'A. COMUNICATION · VHF Backup', 'name' => 'VHF ADC - Primary 118.3 MHz',            'order' => 203],
            ['category' => 'A. COMUNICATION · VHF Backup', 'name' => 'VHF ADC - Secondary 118.1 MHz',          'order' => 204],
            ['category' => 'A. COMUNICATION · VHF Backup', 'name' => 'VHF APP Director - Primary 123.2 MHz',   'order' => 205],
            ['category' => 'A. COMUNICATION · VHF Backup', 'name' => 'VHF APP West - Primary 125.1 MHz',       'order' => 206],
            ['category' => 'A. COMUNICATION · VHF Backup', 'name' => 'VHF APP East - Primary 124.0 MHz',       'order' => 207],

            // ─── A.3 VOICE RECORDING ────────────────────────────────────
            ['category' => 'A. COMUNICATION · Voice Recording', 'name' => 'Server Recorder A',     'order' => 301],
            ['category' => 'A. COMUNICATION · Voice Recording', 'name' => 'Server Recorder B',     'order' => 302],
            ['category' => 'A. COMUNICATION · Voice Recording', 'name' => 'PC Recorder Playback',  'order' => 303],
            ['category' => 'A. COMUNICATION · Voice Recording', 'name' => 'NTP Server & GPS',      'order' => 304],

            // ─── A.4 REPRODUCER ATIS ────────────────────────────────────
            ['category' => 'A. COMUNICATION · Reproducer ATIS', 'name' => 'Server A',          'order' => 401],
            ['category' => 'A. COMUNICATION · Reproducer ATIS', 'name' => 'Server B',          'order' => 402],
            ['category' => 'A. COMUNICATION · Reproducer ATIS', 'name' => 'PC Client Operator','order' => 403],

            // ─── A.5 AMSC ───────────────────────────────────────────────
            ['category' => 'A. COMUNICATION · AMSC', 'name' => 'Server AMSC A',                       'order' => 501],
            ['category' => 'A. COMUNICATION · AMSC', 'name' => 'Server AMSC B',                       'order' => 502],
            ['category' => 'A. COMUNICATION · AMSC', 'name' => 'Control & Spv Console A',             'order' => 503],
            ['category' => 'A. COMUNICATION · AMSC', 'name' => 'Control & Spv Console B',             'order' => 504],
            ['category' => 'A. COMUNICATION · AMSC', 'name' => 'Komputer ADPS',                       'order' => 505],
            ['category' => 'A. COMUNICATION · AMSC', 'name' => 'AFTN Teleprinter - BO 1 WARRZPZE',    'order' => 506],
            ['category' => 'A. COMUNICATION · AMSC', 'name' => 'AFTN Teleprinter - BO 2 WARRYOYX',    'order' => 507],
            ['category' => 'A. COMUNICATION · AMSC', 'name' => 'AFTN Teleprinter - METEO 1 WARRYMYF', 'order' => 508],
            ['category' => 'A. COMUNICATION · AMSC', 'name' => 'AFTN Teleprinter - METEO 2 WARRYMYO', 'order' => 509],
            ['category' => 'A. COMUNICATION · AMSC', 'name' => 'AFTN Teleprinter - INFORMASI WARRYIYX','order' => 510],

            // ─── A.6 VCCS ───────────────────────────────────────────────
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'MCS & RCMS - GATE X2-01', 'order' => 601],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'MCS & RCMS - GATE X2-02', 'order' => 602],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'MCS & RCMS - GATE X2-03', 'order' => 603],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'MCS & RCMS - GATE X2-04', 'order' => 604],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'MCS & RCMS - GPIF 1-4',   'order' => 605],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'MCS & RCMS - ERIF 1-10',  'order' => 606],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'MCS & RCMS - BCA 1-5',    'order' => 607],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'MCS & RCMS - BCB 1-9',    'order' => 608],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'CWP 1',  'order' => 621],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'CWP 2',  'order' => 622],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'CWP 3',  'order' => 623],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'CWP 4',  'order' => 624],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'CWP 5',  'order' => 625],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'CWP 6',  'order' => 626],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'CWP 7',  'order' => 627],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'CWP 8',  'order' => 628],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'CWP 9',  'order' => 629],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'CWP 10', 'order' => 630],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'CWP 11', 'order' => 631],
            ['category' => 'A. COMUNICATION · VCCS', 'name' => 'CWP 12', 'order' => 632],

            // ════════════════════════════════════════════════════════════
            // B. NAVIGATION
            // ════════════════════════════════════════════════════════════
            ['category' => 'B. NAVIGATION', 'name' => 'DVOR',                  'order' => 1001],
            ['category' => 'B. NAVIGATION', 'name' => 'DME',                   'order' => 1002],
            ['category' => 'B. NAVIGATION', 'name' => 'ILS - Localizer',       'order' => 1003],
            ['category' => 'B. NAVIGATION', 'name' => 'ILS - Glide Path',      'order' => 1004],
            ['category' => 'B. NAVIGATION', 'name' => 'ILS - Middle Marker',   'order' => 1005],

            // ════════════════════════════════════════════════════════════
            // C. SURVEILLANCE
            // ════════════════════════════════════════════════════════════
            ['category' => 'C. SURVEILLANCE', 'name' => 'RADAR / MSSR - RMM 1 & 2',  'order' => 1101],
            ['category' => 'C. SURVEILLANCE', 'name' => 'RADAR / MSSR - LCMS 1 & 2', 'order' => 1102],
            ['category' => 'C. SURVEILLANCE', 'name' => 'RADAR / MSSR - SMP 1 & 2',  'order' => 1103],
            ['category' => 'C. SURVEILLANCE', 'name' => 'RADAR / MSSR - RDP 1 & 2',  'order' => 1104],
            ['category' => 'C. SURVEILLANCE', 'name' => 'ADSB',                       'order' => 1105],

            // ════════════════════════════════════════════════════════════
            // D. AUTOMATION
            // ════════════════════════════════════════════════════════════

            // ─── D.1 ATC SYSTEM ─────────────────────────────────────────
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'Server FDP 1 & 2',           'order' => 1201],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'Server SDP 1 & 2',           'order' => 1202],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'Server FDP RBP',             'order' => 1203],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'Recording & Playback System','order' => 1204],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'ASD-1 Director',             'order' => 1221],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'ASD-2 West Sector',          'order' => 1222],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'ASD-3 East Sector',          'order' => 1223],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'ASD-4 Supervisor FIR',       'order' => 1224],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'ASD-5 Tower',                'order' => 1225],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'ASD-6 Supervisor VFR',       'order' => 1226],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'ASD-7 MCC',                  'order' => 1227],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'FDD-1 Director',             'order' => 1228],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'FDD-2 West Sector',          'order' => 1229],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'FDD-3 East Sector',          'order' => 1230],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'ASD-4 FDO',                  'order' => 1231],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'FDD-5 Tower',                'order' => 1232],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'FDD-6 Briefing Office',      'order' => 1233],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'Data Spesialis',             'order' => 1234],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'SMC',                        'order' => 1235],
            ['category' => 'D. AUTOMATION · ATC System', 'name' => 'Playback System',            'order' => 1236],

            // ─── D.2 ASMGCS ─────────────────────────────────────────────
            ['category' => 'D. AUTOMATION · ASMGCS', 'name' => 'Server - CSP 01',           'order' => 1301],
            ['category' => 'D. AUTOMATION · ASMGCS', 'name' => 'Server - CSP 02',           'order' => 1302],
            ['category' => 'D. AUTOMATION · ASMGCS', 'name' => 'Server - COP 01',           'order' => 1303],
            ['category' => 'D. AUTOMATION · ASMGCS', 'name' => 'Server - COP 02',           'order' => 1304],
            ['category' => 'D. AUTOMATION · ASMGCS', 'name' => 'Server - WSP',              'order' => 1305],
            ['category' => 'D. AUTOMATION · ASMGCS', 'name' => 'Server - ANS',              'order' => 1306],
            ['category' => 'D. AUTOMATION · ASMGCS', 'name' => 'Display Position - DP Maintenance','order' => 1311],
            ['category' => 'D. AUTOMATION · ASMGCS', 'name' => 'Display Position - DP 01 Tower',  'order' => 1312],
            ['category' => 'D. AUTOMATION · ASMGCS', 'name' => 'Display Position - DP 02 Ground', 'order' => 1313],
            ['category' => 'D. AUTOMATION · ASMGCS', 'name' => 'Display Position - DP 03 Sup Tower','order' => 1314],
            ['category' => 'D. AUTOMATION · ASMGCS', 'name' => 'Display Position - DP 04 Director','order' => 1315],

            // ════════════════════════════════════════════════════════════
            // E. CHECK TEMPERATURE RUANGAN (measurement, °C)
            // ════════════════════════════════════════════════════════════
            ['category' => 'E. CHECK TEMPERATURE RUANGAN', 'name' => 'Equipment Room', 'order' => 1401, 'is_measurement' => true, 'unit' => '°C'],
            ['category' => 'E. CHECK TEMPERATURE RUANGAN', 'name' => 'App Room',       'order' => 1402, 'is_measurement' => true, 'unit' => '°C'],
            ['category' => 'E. CHECK TEMPERATURE RUANGAN', 'name' => 'RX Room',        'order' => 1403, 'is_measurement' => true, 'unit' => '°C'],
            ['category' => 'E. CHECK TEMPERATURE RUANGAN', 'name' => 'Tower Room',     'order' => 1404, 'is_measurement' => true, 'unit' => '°C'],
        ];

        $now = now();
        $rows = array_map(fn ($row) => array_merge(
            ['is_measurement' => false, 'unit' => null],
            $row,
            ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ), $data);

        CnsdEquipment::insert($rows);

        $this->command->info('CnsdEquipmentSeeder: inserted ' . count($rows) . ' equipment records.');
    }
}
