<?php

namespace App\Services\Dashboard;

use App\Models\Cnsd\CnsdAmscMeterRecord;
use App\Models\Cnsd\CnsdAsmgcsMeterRecord;
use App\Models\Cnsd\CnsdAtcSystemMeterRecord;
use App\Models\Cnsd\CnsdAtisMeterRecord;
use App\Models\Cnsd\CnsdDmeMeterRecord;
use App\Models\Cnsd\CnsdDvorMeterRecord;
use App\Models\Cnsd\CnsdGlidepathMeterRecord;
use App\Models\Cnsd\CnsdLocalizerMeterRecord;
use App\Models\Cnsd\CnsdRadarMeterRecord;
use App\Models\Cnsd\CnsdReadinessRecord;
use App\Models\Cnsd\CnsdReceiverMeterRecord;
use App\Models\Cnsd\CnsdRecorderMeterRecord;
use App\Models\Cnsd\CnsdTdmeMeterRecord;
use App\Models\Cnsd\CnsdTransmitterMeterRecord;
use App\Models\Cnsd\CnsdVccsFreqMeterRecord;
use App\Models\Cnsd\CnsdVccsMeterRecord;
use App\Models\GroundCheck\GroundCheckAdcRecord;
use App\Models\GroundCheck\GroundCheckDvorRecord;
use App\Models\GroundCheck\GroundCheckGpRecord;
use App\Models\GroundCheck\GroundCheckLlzRecord;
use App\Models\GroundCheck\GroundCheckVhfRecord;
use App\Models\Grounding\GroundingReportRecord;
use App\Models\Tfp\TfpAobGroundRecord;
use App\Models\Tfp\TfpAobLt12Record;
use App\Models\Tfp\TfpDvorRecord;
use App\Models\Tfp\TfpGlidepathRecord;
use App\Models\Tfp\TfpLocalizerRecord;
use App\Models\Tfp\TfpRadarRecord;
use App\Models\Tfp\TfpTowerRecord;
use App\Models\Tfp\TfpTransmitterTxRecord;

/**
 * DashboardModuleRegistry — single source of truth for every module that can
 * appear on the dashboard's "Pengingat Pengecekan" card (and the kiosk
 * monitor variant). Used by:
 *   - the settings page to populate the "add reminder" dropdown
 *   - the snapshot/checklist endpoints to resolve a stored module_key into
 *     a frontend route + Eloquent model for the has_record lookup
 *
 * Adding a new module: append one row here. Migration is not required.
 * Removing a module: remove the row + clean up any stored items that
 * reference its key (or let the resolver silently skip unknown keys —
 * dashboard_checklist_items keeps the row, surface a warning in the UI).
 *
 * All listed models must expose `date` (date column) and `shift_type`
 * (pagi|siang|malam) so the per-shift has_record check works uniformly.
 */
class DashboardModuleRegistry
{
    /**
     * @return array<int, array{key:string,label:string,division:string,group:string,route:string,model:class-string}>
     */
    public static function modules(): array
    {
        return [
            // ─── CNSD Readiness ─────────────────────────────────────────
            ['key' => 'cnsd-readiness', 'label' => 'Kesiapan Peralatan CNSD (Form EQ-1)',
             'division' => 'CNSD', 'group' => 'CNSD Readiness',
             'route' => '/cnsd/readiness', 'model' => CnsdReadinessRecord::class],

            // ─── CNSD Meter Reading (16 modul) ──────────────────────────
            ['key' => 'cnsd-radar',         'label' => 'Meter Reading Radar',
             'division' => 'CNSD', 'group' => 'CNSD Meter Reading',
             'route' => '/cnsd/radar-meter',         'model' => CnsdRadarMeterRecord::class],
            ['key' => 'cnsd-recorder',      'label' => 'Meter Reading Recorder',
             'division' => 'CNSD', 'group' => 'CNSD Meter Reading',
             'route' => '/cnsd/recorder-meter',      'model' => CnsdRecorderMeterRecord::class],
            ['key' => 'cnsd-amsc',          'label' => 'Meter Reading AMSC',
             'division' => 'CNSD', 'group' => 'CNSD Meter Reading',
             'route' => '/cnsd/amsc-meter',          'model' => CnsdAmscMeterRecord::class],
            ['key' => 'cnsd-transmitter',   'label' => 'Meter Reading Transmitter',
             'division' => 'CNSD', 'group' => 'CNSD Meter Reading',
             'route' => '/cnsd/transmitter-meter',   'model' => CnsdTransmitterMeterRecord::class],
            ['key' => 'cnsd-receiver',      'label' => 'Meter Reading Receiver',
             'division' => 'CNSD', 'group' => 'CNSD Meter Reading',
             'route' => '/cnsd/receiver-meter',      'model' => CnsdReceiverMeterRecord::class],
            ['key' => 'cnsd-glidepath',     'label' => 'Meter Reading Glide Path',
             'division' => 'CNSD', 'group' => 'CNSD Meter Reading',
             'route' => '/cnsd/glidepath-meter',     'model' => CnsdGlidepathMeterRecord::class],
            ['key' => 'cnsd-localizer',     'label' => 'Meter Reading Localizer',
             'division' => 'CNSD', 'group' => 'CNSD Meter Reading',
             'route' => '/cnsd/localizer-meter',     'model' => CnsdLocalizerMeterRecord::class],
            ['key' => 'cnsd-tdme',          'label' => 'Meter Reading T-DME',
             'division' => 'CNSD', 'group' => 'CNSD Meter Reading',
             'route' => '/cnsd/tdme-meter',          'model' => CnsdTdmeMeterRecord::class],
            ['key' => 'cnsd-dvor',          'label' => 'Meter Reading DVOR',
             'division' => 'CNSD', 'group' => 'CNSD Meter Reading',
             'route' => '/cnsd/dvor-meter',          'model' => CnsdDvorMeterRecord::class],
            ['key' => 'cnsd-dme',           'label' => 'Meter Reading DME',
             'division' => 'CNSD', 'group' => 'CNSD Meter Reading',
             'route' => '/cnsd/dme-meter',           'model' => CnsdDmeMeterRecord::class],
            ['key' => 'cnsd-atc-system',    'label' => 'Meter Reading ATC System',
             'division' => 'CNSD', 'group' => 'CNSD Meter Reading',
             'route' => '/cnsd/atc-system-meter',    'model' => CnsdAtcSystemMeterRecord::class],
            ['key' => 'cnsd-atis',          'label' => 'Meter Reading ATIS',
             'division' => 'CNSD', 'group' => 'CNSD Meter Reading',
             'route' => '/cnsd/atis-meter',          'model' => CnsdAtisMeterRecord::class],
            ['key' => 'cnsd-vccs',          'label' => 'Meter Reading VCCS (LES)',
             'division' => 'CNSD', 'group' => 'CNSD Meter Reading',
             'route' => '/cnsd/vccs-meter',          'model' => CnsdVccsMeterRecord::class],
            ['key' => 'cnsd-vccs-freq',     'label' => 'Meter Reading VCCS (Frequentis)',
             'division' => 'CNSD', 'group' => 'CNSD Meter Reading',
             'route' => '/cnsd/vccs-freq-meter',     'model' => CnsdVccsFreqMeterRecord::class],
            ['key' => 'cnsd-asmgcs',        'label' => 'Meter Reading ASMGCS',
             'division' => 'CNSD', 'group' => 'CNSD Meter Reading',
             'route' => '/cnsd/asmgcs-meter',        'model' => CnsdAsmgcsMeterRecord::class],

            // ─── TFP Performance Check (8 modul) ────────────────────────
            ['key' => 'tfp-aob-ground',     'label' => 'Performance Check AOB Lantai Ground',
             'division' => 'TFP', 'group' => 'TFP Performance',
             'route' => '/tfp/aob-ground',    'model' => TfpAobGroundRecord::class],
            ['key' => 'tfp-aob-lt12',       'label' => 'Performance Check AOB Lantai 1 & 2',
             'division' => 'TFP', 'group' => 'TFP Performance',
             'route' => '/tfp/aob-lt12',      'model' => TfpAobLt12Record::class],
            ['key' => 'tfp-transmitter',    'label' => 'Performance Check Gedung Transmitter',
             'division' => 'TFP', 'group' => 'TFP Performance',
             'route' => '/tfp/transmitter-tx','model' => TfpTransmitterTxRecord::class],
            ['key' => 'tfp-tower',          'label' => 'Performance Check Gedung Tower',
             'division' => 'TFP', 'group' => 'TFP Performance',
             'route' => '/tfp/tower',         'model' => TfpTowerRecord::class],
            ['key' => 'tfp-radar',          'label' => 'Performance Check Gedung Radar',
             'division' => 'TFP', 'group' => 'TFP Performance',
             'route' => '/tfp/radar-tfp',     'model' => TfpRadarRecord::class],
            ['key' => 'tfp-dvor',           'label' => 'Performance Check Gedung DVOR',
             'division' => 'TFP', 'group' => 'TFP Performance',
             'route' => '/tfp/dvor',          'model' => TfpDvorRecord::class],
            ['key' => 'tfp-localizer',      'label' => 'Performance Check Gedung Localizer',
             'division' => 'TFP', 'group' => 'TFP Performance',
             'route' => '/tfp/localizer',     'model' => TfpLocalizerRecord::class],
            ['key' => 'tfp-glidepath',      'label' => 'Performance Check Gedung Glide Path',
             'division' => 'TFP', 'group' => 'TFP Performance',
             'route' => '/tfp/glidepath',     'model' => TfpGlidepathRecord::class],

            // ─── Ground Check (5 modul, CNSD) ───────────────────────────
            ['key' => 'gc-adc',  'label' => 'Ground Check ADC',
             'division' => 'CNSD', 'group' => 'Ground Check',
             'route' => '/ground-check/adc',  'model' => GroundCheckAdcRecord::class],
            ['key' => 'gc-vhf',  'label' => 'Ground Check VHF',
             'division' => 'CNSD', 'group' => 'Ground Check',
             'route' => '/ground-check/vhf',  'model' => GroundCheckVhfRecord::class],
            ['key' => 'gc-llz',  'label' => 'Ground Check Localizer',
             'division' => 'CNSD', 'group' => 'Ground Check',
             'route' => '/ground-check/llz',  'model' => GroundCheckLlzRecord::class],
            ['key' => 'gc-gp',   'label' => 'Ground Check Glide Path',
             'division' => 'CNSD', 'group' => 'Ground Check',
             'route' => '/ground-check/gp',   'model' => GroundCheckGpRecord::class],
            ['key' => 'gc-dvor', 'label' => 'Ground Check DVOR',
             'division' => 'CNSD', 'group' => 'Ground Check',
             'route' => '/ground-check/dvor', 'model' => GroundCheckDvorRecord::class],

            // ─── Grounding (1 modul, TFP) ───────────────────────────────
            ['key' => 'grounding', 'label' => 'Grounding Report',
             'division' => 'TFP', 'group' => 'Grounding',
             'route' => '/grounding/reports', 'model' => GroundingReportRecord::class],
        ];
    }

    /**
     * Look up a single module by its key. Returns null when the key has been
     * removed from the registry — callers should treat null as "stale row,
     * skip silently and surface a warning in the settings UI".
     */
    public static function find(string $key): ?array
    {
        foreach (self::modules() as $module) {
            if ($module['key'] === $key) return $module;
        }
        return null;
    }

    /** All valid keys, for validation. */
    public static function validKeys(): array
    {
        return array_map(static fn ($m) => $m['key'], self::modules());
    }
}
