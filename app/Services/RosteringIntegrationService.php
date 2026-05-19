<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RosteringIntegrationService
 *
 * Encapsulates all read-only queries to the atoms-rostering database.
 * Uses the 'rostering' DB connection defined in config/database.php.
 *
 * RULES:
 * - NEVER call DB::connection('rostering')->statement() for writes.
 * - Always filter whereNull('deleted_at') — all rostering tables use soft deletes.
 * - All public methods must have a graceful fallback when rostering DB is unavailable.
 */
class RosteringIntegrationService
{
    /**
     * Get the real shift end time for a given shift type.
     * Replaces the hardcoded fallback in WorkOrder::isShiftEnded().
     *
     * @param  string  $shiftType  'pagi' | 'siang' | 'malam'
     * @return array{start_time: string, end_time: string}|null  null if not found
     */
    public function getShiftTimes(string $shiftType): ?array
    {
        try {
            $shift = DB::connection('rostering')
                ->table('shifts')
                ->where('name', strtolower($shiftType))
                ->whereNull('deleted_at')
                ->select('name', 'start_time', 'end_time')
                ->first();

            if (!$shift || !$shift->end_time) {
                return null;
            }

            return [
                'start_time' => $shift->start_time,
                'end_time'   => $shift->end_time,
            ];
        } catch (\Exception $e) {
            Log::warning('RosteringIntegrationService::getShiftTimes failed', [
                'shift_type' => $shiftType,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Determine whether a shift has ended based on real rostering shift times.
     * Falls back to hardcoded times if rostering DB is unavailable.
     *
     * @param  string  $shiftType  'pagi' | 'siang' | 'malam'
     * @param  string  $shiftDate  'Y-m-d'
     */
    public function isShiftEnded(string $shiftType, string $shiftDate): bool
    {
        // Hardcoded fallback (same as current WorkOrder::isShiftEnded)
        $fallback = [
            'pagi'  => '13:00',
            'siang' => '19:00',
            'malam' => '07:00',
        ];

        $shiftTimes = $this->getShiftTimes($shiftType);
        $endTimeStr = $shiftTimes['end_time'] ?? ($fallback[strtolower($shiftType)] ?? '13:00');

        // Parse end time — strip seconds if present (e.g. "13:00:00" → "13:00")
        $endTimeStr = substr($endTimeStr, 0, 5);

        $endDate = Carbon::parse($shiftDate);

        // Malam shift ends the next calendar day
        if (strtolower($shiftType) === 'malam') {
            $endDate->addDay();
        }

        $shiftEnd = Carbon::parse($endDate->format('Y-m-d') . ' ' . $endTimeStr);

        return Carbon::now()->greaterThanOrEqualTo($shiftEnd);
    }

    /**
     * Get all personnel assigned to a specific shift on a given date.
     * Returns both CNS (technicians) and Support (TFP) employees.
     *
     * @param  string  $shiftType  'pagi' | 'siang' | 'malam'
     * @param  string  $date       'Y-m-d'
     * @return Collection<object{user_id, name, role, employee_type, group_number}>
     */
    public function getShiftPersonnel(string $shiftType, string $date): Collection
    {
        try {
            return DB::connection('rostering')
                ->table('shift_assignments as sa')
                ->join('roster_days as rd', 'rd.id', '=', 'sa.roster_day_id')
                ->join('roster_periods as rp', 'rp.id', '=', 'rd.roster_period_id')
                ->join('employees as e', 'e.id', '=', 'sa.employee_id')
                ->join('users as u', 'u.id', '=', 'e.user_id')
                ->join('shifts as s', 's.id', '=', 'sa.shift_id')
                ->where('rd.work_date', $date)
                ->where('rp.status', 'published')
                ->where('s.name', strtolower($shiftType))
                ->whereIn('e.employee_type', ['CNS', 'Support'])
                ->whereNull('sa.deleted_at')
                ->whereNull('rd.deleted_at')
                ->whereNull('e.deleted_at')
                ->whereNull('u.deleted_at')
                ->where('u.is_active', true)
                ->select(
                    'u.id as user_id',
                    'u.name',
                    'u.role',
                    'u.grade',
                    'e.employee_type',
                    'e.group_number'
                )
                ->orderBy('e.employee_type')
                ->orderBy('u.name')
                ->get();
        } catch (\Exception $e) {
            Log::warning('RosteringIntegrationService::getShiftPersonnel failed', [
                'shift_type' => $shiftType,
                'date'       => $date,
                'error'      => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Get the Manager Teknik assigned to a specific shift on a given date.
     *
     * The MT is stored in `shift_assignments` with `employees.employee_type = 'Manager Teknik'`.
     * The legacy `manager_duties` table is unused in current data, so this method
     * resolves MT from the same shift_assignments path as regular personnel.
     *
     * @param  string  $shiftType  'pagi' | 'siang' | 'malam'
     * @param  string  $date       'Y-m-d'
     * @return object{user_id, name, role, employee_type, group_number}|null
     */
    public function getShiftManager(string $shiftType, string $date): ?object
    {
        try {
            return DB::connection('rostering')
                ->table('shift_assignments as sa')
                ->join('roster_days as rd', 'rd.id', '=', 'sa.roster_day_id')
                ->join('roster_periods as rp', 'rp.id', '=', 'rd.roster_period_id')
                ->join('employees as e', 'e.id', '=', 'sa.employee_id')
                ->join('users as u', 'u.id', '=', 'e.user_id')
                ->join('shifts as s', 's.id', '=', 'sa.shift_id')
                ->where('rd.work_date', $date)
                ->where('rp.status', 'published')
                ->where('s.name', strtolower($shiftType))
                ->where('e.employee_type', 'Manager Teknik')
                ->whereNull('sa.deleted_at')
                ->whereNull('rd.deleted_at')
                ->whereNull('e.deleted_at')
                ->whereNull('u.deleted_at')
                ->where('u.is_active', true)
                ->select(
                    'u.id as user_id',
                    'u.name',
                    'u.role',
                    'e.employee_type',
                    'e.group_number'
                )
                ->orderBy('u.name')
                ->first();
        } catch (\Exception $e) {
            Log::warning('RosteringIntegrationService::getShiftManager failed', [
                'shift_type' => $shiftType,
                'date'       => $date,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Batch-fetch all Manager Teknik assigned across a list of dates and all 3 shifts
     * in a single query. Used by list views (e.g. Logbook TFP) to avoid N×3 queries.
     *
     * @param  array<int, string>  $dates  list of 'Y-m-d' strings
     * @return array<string, array{pagi: ?object, siang: ?object, malam: ?object}>
     *         keyed by date string. Missing dates / shifts return null.
     */
    public function getShiftManagersForDates(array $dates): array
    {
        // Initialize empty structure
        $result = [];
        foreach ($dates as $d) {
            $result[$d] = ['pagi' => null, 'siang' => null, 'malam' => null];
        }
        if (empty($dates)) {
            return $result;
        }

        try {
            $rows = DB::connection('rostering')
                ->table('shift_assignments as sa')
                ->join('roster_days as rd', 'rd.id', '=', 'sa.roster_day_id')
                ->join('roster_periods as rp', 'rp.id', '=', 'rd.roster_period_id')
                ->join('employees as e', 'e.id', '=', 'sa.employee_id')
                ->join('users as u', 'u.id', '=', 'e.user_id')
                ->join('shifts as s', 's.id', '=', 'sa.shift_id')
                ->whereIn('rd.work_date', $dates)
                ->where('rp.status', 'published')
                ->whereIn('s.name', ['pagi', 'siang', 'malam'])
                ->where('e.employee_type', 'Manager Teknik')
                ->whereNull('sa.deleted_at')
                ->whereNull('rd.deleted_at')
                ->whereNull('e.deleted_at')
                ->whereNull('u.deleted_at')
                ->where('u.is_active', true)
                ->select(
                    'rd.work_date',
                    's.name as shift',
                    'u.id as user_id',
                    'u.name'
                )
                ->orderBy('u.name')
                ->get();

            foreach ($rows as $row) {
                $dateKey = Carbon::parse($row->work_date)->format('Y-m-d');
                $shift   = strtolower((string) $row->shift);
                if (!isset($result[$dateKey]) || !in_array($shift, ['pagi', 'siang', 'malam'], true)) {
                    continue;
                }
                // Keep only the first manager per (date, shift) — matches single-result behavior of getShiftManager
                if ($result[$dateKey][$shift] === null) {
                    $result[$dateKey][$shift] = (object) [
                        'user_id' => (int) $row->user_id,
                        'name'    => $row->name,
                    ];
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::warning('RosteringIntegrationService::getShiftManagersForDates failed', [
                'date_count' => count($dates),
                'error'      => $e->getMessage(),
            ]);
            return $result;
        }
    }

    /**
     * Get the Supervisor for a shift, restricted to a single employee_type
     * (CNS for CNSD, Support for TFP).
     *
     * In atoms-rostering, a "Supervisor" is an employee on this shift whose
     * grade is at the supervisor threshold (>= 13). There is no separate
     * supervisor role.
     *
     * @param  string  $shiftType    'pagi' | 'siang' | 'malam'
     * @param  string  $date         'Y-m-d'
     * @param  string  $employeeType 'CNS' (CNSD) or 'Support' (TFP)
     * @return object{user_id, name, role, grade, employee_type}|null
     */
    public function getShiftSupervisorByDivision(string $shiftType, string $date, string $employeeType): ?object
    {
        try {
            return DB::connection('rostering')
                ->table('shift_assignments as sa')
                ->join('roster_days as rd', 'rd.id', '=', 'sa.roster_day_id')
                ->join('roster_periods as rp', 'rp.id', '=', 'rd.roster_period_id')
                ->join('employees as e', 'e.id', '=', 'sa.employee_id')
                ->join('users as u', 'u.id', '=', 'e.user_id')
                ->join('shifts as s', 's.id', '=', 'sa.shift_id')
                ->where('rd.work_date', $date)
                ->where('rp.status', 'published')
                ->where('s.name', strtolower($shiftType))
                ->where('e.employee_type', $employeeType)
                ->where('u.grade', '>=', 13) // grade 13 = SVP, grade 14 = SPV
                ->whereNull('sa.deleted_at')
                ->whereNull('rd.deleted_at')
                ->whereNull('e.deleted_at')
                ->whereNull('u.deleted_at')
                ->where('u.is_active', true)
                ->select(
                    'u.id as user_id',
                    'u.name',
                    'u.role',
                    'u.grade',
                    'e.employee_type'
                )
                ->orderByDesc('u.grade')
                ->first();
        } catch (\Exception $e) {
            Log::warning('RosteringIntegrationService::getShiftSupervisorByDivision failed', [
                'shift_type'    => $shiftType,
                'date'          => $date,
                'employee_type' => $employeeType,
                'error'         => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Backward-compatible: get any single supervisor on the shift, preferring CNS.
     * Used by Work Order creation when no division split is needed yet.
     */
    public function getShiftSupervisor(string $shiftType, string $date): ?object
    {
        return $this->getShiftSupervisorByDivision($shiftType, $date, 'CNS')
            ?? $this->getShiftSupervisorByDivision($shiftType, $date, 'Support');
    }

    /**
     * Get a complete snapshot of shift context for a given date and shift type.
     * Used by the frontend's "shift-today" endpoint and Work Order creation.
     *
     * @param  string  $shiftType  'pagi' | 'siang' | 'malam'
     * @param  string  $date       'Y-m-d'
     * @return array{
     *   date: string,
     *   shift_type: string,
     *   shift_times: array|null,
     *   manager: object|null,
     *   supervisor: object|null,
     *   supervisor_cnsd: object|null,
     *   supervisor_tfp: object|null,
     *   personnel: Collection,
     *   has_supervisor: bool,
     *   roster_available: bool
     * }
     */
    public function getShiftContext(string $shiftType, string $date): array
    {
        $shiftTimes        = $this->getShiftTimes($shiftType);
        $manager           = $this->getShiftManager($shiftType, $date);
        $supervisorCnsd    = $this->getShiftSupervisorByDivision($shiftType, $date, 'CNS');
        $supervisorTfp     = $this->getShiftSupervisorByDivision($shiftType, $date, 'Support');
        $personnel         = $this->getShiftPersonnel($shiftType, $date);

        // Backward-compatible primary supervisor (CNS preferred, fallback Support)
        $supervisor = $supervisorCnsd ?? $supervisorTfp;

        return [
            'date'              => $date,
            'shift_type'        => $shiftType,
            'shift_times'       => $shiftTimes,
            'manager'           => $manager,
            'supervisor'        => $supervisor,
            'supervisor_cnsd'   => $supervisorCnsd,
            'supervisor_tfp'    => $supervisorTfp,
            'personnel'         => $personnel,
            'has_supervisor'    => $supervisor !== null,
            'roster_available'  => $personnel->isNotEmpty() || $manager !== null,
        ];
    }

    /**
     * Get all active users from rostering (for local_users sync).
     * Returns the full list needed to populate/refresh the local_users cache.
     *
     * @return Collection<object{user_id, name, email, role, employee_type, group_number}>
     */
    public function getAllActiveUsers(): Collection
    {
        try {
            return DB::connection('rostering')
                ->table('employees as e')
                ->join('users as u', 'u.id', '=', 'e.user_id')
                ->where('e.is_active', true)
                ->where('u.is_active', true)
                ->whereNull('e.deleted_at')
                ->whereNull('u.deleted_at')
                ->select(
                    'u.id as rostering_user_id',
                    'u.name',
                    'u.email',
                    'u.role',
                    'u.grade',
                    'e.employee_type',
                    'e.group_number'
                )
                ->orderBy('e.employee_type')
                ->orderBy('u.name')
                ->get();
        } catch (\Exception $e) {
            Log::warning('RosteringIntegrationService::getAllActiveUsers failed', [
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }
}
