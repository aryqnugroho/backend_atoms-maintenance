<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cnsd\CnsdAmscMeterRecord;
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
use App\Models\Tfp\TfpAobGroundRecord;
use App\Models\Tfp\TfpAobLt12Record;
use App\Models\WorkOrder\WorkOrder;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DashboardController — read-only helpers powering the dashboard widgets
 * (welcome modal + Pengingat Pengecekan Harian card).
 *
 * One canonical endpoint: `GET /api/v1/dashboard/shift-checklist?date=...&shift_type=...`
 * Returns has_record/record_id for every form that appears in the daily
 * reminder catalog (CNSD readiness + 12 CNSD meter readings + 2 TFP AOB
 * forms) plus a Work Order rollup per division. The frontend decides
 * which subset to render based on the current user's role.
 */
class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * Catalog of every checklist item the dashboard renders.
     *
     * Tuple structure:
     *   [key, division, category, shift|null, label, route, modelClass]
     *
     *   - `category` is either 'wajib' (every shift) or 'shift' (a specific shift)
     *   - `shift` is null for wajib items, 'pagi'|'siang'|'malam' for shift items
     *   - `modelClass` is the Eloquent model whose presence indicates "has_record"
     */
    private const CATALOG = [
        // ─── Wajib (every shift) ─────────────────────────────────────
        ['cnsd-readiness',  'CNSD', 'wajib', null, 'Kesiapan Peralatan CNSD',              '/cnsd/readiness',   CnsdReadinessRecord::class],
        ['tfp-aob-ground',  'TFP',  'wajib', null, 'Performance Check AOB Lantai Ground', '/tfp/aob-ground',   TfpAobGroundRecord::class],
        ['tfp-aob-lt12',    'TFP',  'wajib', null, 'Performance Check AOB Lantai 1 & 2',  '/tfp/aob-lt12',     TfpAobLt12Record::class],

        // ─── Pagi (CNSD meter readings) ──────────────────────────────
        ['cnsd-localizer',   'CNSD', 'shift', 'pagi', 'Localizer',   '/cnsd/localizer-meter',   CnsdLocalizerMeterRecord::class],
        ['cnsd-glidepath',   'CNSD', 'shift', 'pagi', 'Glide Path',  '/cnsd/glidepath-meter',   CnsdGlidepathMeterRecord::class],
        ['cnsd-tdme',        'CNSD', 'shift', 'pagi', 'T-DME',       '/cnsd/tdme-meter',        CnsdTdmeMeterRecord::class],
        ['cnsd-transmitter', 'CNSD', 'shift', 'pagi', 'Transmitter', '/cnsd/transmitter-meter', CnsdTransmitterMeterRecord::class],

        // ─── Siang (CNSD meter readings) ─────────────────────────────
        ['cnsd-dvor',  'CNSD', 'shift', 'siang', 'DVOR',  '/cnsd/dvor-meter',  CnsdDvorMeterRecord::class],
        ['cnsd-dme',   'CNSD', 'shift', 'siang', 'DME',   '/cnsd/dme-meter',   CnsdDmeMeterRecord::class],
        ['cnsd-radar', 'CNSD', 'shift', 'siang', 'Radar', '/cnsd/radar-meter', CnsdRadarMeterRecord::class],

        // ─── Malam (CNSD meter readings) ─────────────────────────────
        ['cnsd-recorder',   'CNSD', 'shift', 'malam', 'Recorder',   '/cnsd/recorder-meter',   CnsdRecorderMeterRecord::class],
        ['cnsd-atc-system', 'CNSD', 'shift', 'malam', 'ATC System', '/cnsd/atc-system-meter', CnsdAtcSystemMeterRecord::class],
        ['cnsd-amsc',       'CNSD', 'shift', 'malam', 'AMSC',       '/cnsd/amsc-meter',       CnsdAmscMeterRecord::class],
        ['cnsd-atis',       'CNSD', 'shift', 'malam', 'ATIS',       '/cnsd/atis-meter',       CnsdAtisMeterRecord::class],
        ['cnsd-receiver',   'CNSD', 'shift', 'malam', 'Receiver',   '/cnsd/receiver-meter',   CnsdReceiverMeterRecord::class],
    ];

    /**
     * GET /api/v1/dashboard/shift-checklist?date=YYYY-MM-DD&shift_type=pagi|siang|malam
     */
    public function shiftChecklist(Request $request): JsonResponse
    {
        $date  = $this->resolveDate($request->input('date'));
        $shift = $this->resolveShift($request->input('shift_type'));

        $items = [];
        foreach (self::CATALOG as $row) {
            [$key, $division, $category, $itemShift, $label, $route, $modelClass] = $row;

            // shift items only "exist" for their own shift
            $effectiveShift = $itemShift ?? $shift;

            $record = $this->findRecord($modelClass, $date, $effectiveShift);

            $items[] = [
                'key'        => $key,
                'division'   => $division,
                'category'   => $category,
                'shift'      => $itemShift,
                'label'      => $label,
                'route'      => $route,
                'has_record' => $record !== null,
                'record_id'  => $record?->id,
            ];
        }

        return $this->success([
            'date'         => $date,
            'shift_type'   => $shift,
            'items'        => $items,
            'work_orders'  => [
                'cnsd' => $this->summarizeWorkOrders('CNSD', $date, $shift),
                'tfp'  => $this->summarizeWorkOrders('TFP',  $date, $shift),
            ],
        ], 'Shift checklist retrieved');
    }

    private function findRecord(string $modelClass, string $date, string $shift): ?Model
    {
        /** @var class-string<Model> $modelClass */
        return $modelClass::query()
            ->whereDate('date', $date)
            ->where('shift_type', $shift)
            ->orderBy('id')
            ->first(['id']);
    }

    /**
     * For a division, return:
     *   - has_shift_wo: does a wo_type=shift WO exist for this date+shift+division?
     *   - shift_wo_id: id of the first shift WO, for deep-linking (null if none)
     *   - ongoing_count: how many WOs for this division+date+shift are still ongoing/on_hold
     */
    private function summarizeWorkOrders(string $division, string $date, string $shift): array
    {
        $base = WorkOrder::query()
            ->whereDate('shift_date', $date)
            ->where('shift_type', $shift)
            ->where('division', $division);

        $shiftWo = (clone $base)
            ->where('wo_type', 'shift')
            ->orderBy('id')
            ->first(['id']);

        $ongoingCount = (clone $base)
            ->whereIn('status', ['ongoing', 'on_hold'])
            ->count();

        return [
            'has_shift_wo'   => $shiftWo !== null,
            'shift_wo_id'    => $shiftWo?->id,
            'ongoing_count'  => $ongoingCount,
        ];
    }

    private function resolveDate(mixed $raw): string
    {
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }
        return Carbon::now()->format('Y-m-d');
    }

    private function resolveShift(mixed $raw): string
    {
        if (in_array($raw, ['pagi', 'siang', 'malam'], true)) {
            return (string) $raw;
        }

        // Fallback: derive from current local time (Asia/Jakarta) — pagi 07:00-13:00,
        // siang 13:00-19:00, malam 19:00-07:00.
        $hour = (int) Carbon::now()->format('G');
        return match (true) {
            $hour >= 7  && $hour < 13 => 'pagi',
            $hour >= 13 && $hour < 19 => 'siang',
            default                   => 'malam',
        };
    }
}
