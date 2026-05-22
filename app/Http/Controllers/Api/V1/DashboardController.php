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
use App\Models\Logbook\LogbookCnsdNote;
use App\Models\Logbook\LogbookTfpNote;
use App\Models\Tfp\TfpAobGroundRecord;
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

    /**
     * GET /api/v1/dashboard/logbook-summary?date=YYYY-MM-DD&limit=8
     *
     * Returns a unified, chronologically ordered timeline of the most recent
     * notes from BOTH the CNSD and TFP logbooks for the requested date
     * (default: today). If today has no notes at all, falls back to the
     * previous day's malam shift so the dashboard always has something to
     * surface ("what did the last shift do?").
     *
     * Auto-notes generated by form-create flows (activity starting with
     * "[Auto]") are tagged with `is_auto: true` so the UI can render them
     * distinctly.
     */
    public function logbookSummary(Request $request): JsonResponse
    {
        $date  = $this->resolveDate($request->input('date'));
        $limit = max(1, min((int) $request->input('limit', 8), 20));

        $notes = $this->collectNotes($date);
        $isFallback = false;
        $sourceDate = $date;
        $fallbackShift = null;

        if (empty($notes)) {
            // Nothing today yet — surface yesterday's malam shift so the user
            // still sees "what just happened on the last active shift".
            $yesterday = Carbon::parse($date)->subDay()->format('Y-m-d');
            $fallback = $this->collectNotes($yesterday, 'malam');
            if (!empty($fallback)) {
                $notes = $fallback;
                $isFallback = true;
                $sourceDate = $yesterday;
                $fallbackShift = 'malam';
            }
        }

        // Sort: newest first, by shift order (malam > siang > pagi) then time desc.
        $shiftRank = ['pagi' => 0, 'siang' => 1, 'malam' => 2];
        usort($notes, function ($a, $b) use ($shiftRank) {
            $sa = $shiftRank[$a['shift']] ?? -1;
            $sb = $shiftRank[$b['shift']] ?? -1;
            if ($sa !== $sb) return $sb <=> $sa;
            return strcmp((string) $b['time'], (string) $a['time']);
        });

        // Counts (computed BEFORE slicing so the "selengkapnya" hint is accurate)
        $cnsdCount = count(array_filter($notes, fn ($n) => $n['division'] === 'CNSD'));
        $tfpCount  = count(array_filter($notes, fn ($n) => $n['division'] === 'TFP'));

        return $this->success([
            'date'           => $date,
            'source_date'    => $sourceDate,
            'is_fallback'    => $isFallback,
            'fallback_shift' => $fallbackShift,
            'total_count'    => count($notes),
            'cnsd_count'     => $cnsdCount,
            'tfp_count'      => $tfpCount,
            'notes'          => array_slice($notes, 0, $limit),
        ], 'Logbook summary retrieved');
    }

    /**
     * Pull notes for one date from BOTH division logbooks. If $onlyShift is
     * given, restrict to that shift (used by the yesterday-fallback path).
     *
     * @return array<int, array{division:string,shift:string,time:?string,activity:string,is_auto:bool,logbook_id:int,note_id:int}>
     */
    private function collectNotes(string $date, ?string $onlyShift = null): array
    {
        $out = [];

        $cnsd = LogbookCnsdNote::query()
            ->whereHas('logbook', fn ($q) => $q->whereDate('date', $date))
            ->when($onlyShift, fn ($q) => $q->where('shift', $onlyShift))
            ->with(['logbook:id,date'])
            ->orderBy('shift')
            ->orderBy('time')
            ->get(['id', 'logbook_cnsd_id', 'shift', 'time', 'activity']);

        foreach ($cnsd as $n) {
            $out[] = [
                'division'   => 'CNSD',
                'shift'      => (string) $n->shift,
                'time'       => $n->time,
                'activity'   => (string) $n->activity,
                'is_auto'    => str_starts_with((string) $n->activity, '[Auto]'),
                'logbook_id' => (int) $n->logbook_cnsd_id,
                'note_id'    => (int) $n->id,
            ];
        }

        $tfp = LogbookTfpNote::query()
            ->whereHas('logbook', fn ($q) => $q->whereDate('date', $date))
            ->when($onlyShift, fn ($q) => $q->where('shift', $onlyShift))
            ->with(['logbook:id,date'])
            ->orderBy('shift')
            ->orderBy('time')
            ->get(['id', 'logbook_tfp_id', 'shift', 'time', 'activity']);

        foreach ($tfp as $n) {
            $out[] = [
                'division'   => 'TFP',
                'shift'      => (string) $n->shift,
                'time'       => $n->time,
                'activity'   => (string) $n->activity,
                'is_auto'    => str_starts_with((string) $n->activity, '[Auto]'),
                'logbook_id' => (int) $n->logbook_tfp_id,
                'note_id'    => (int) $n->id,
            ];
        }

        return $out;
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
