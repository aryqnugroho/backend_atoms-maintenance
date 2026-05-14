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
     * Returns the first MT found for that shift (there should be exactly one).
     *
     * @param  string  $shiftType  'pagi' | 'siang' | 'malam'
     * @param  string  $date       'Y-m-d'
     * @return object{user_id, name, role, employee_type}|null
     */
    public function getShiftManager(string $shiftType, string $date): ?object
    {
        try {
            return DB::connection('rostering')
                ->table('manager_duties as md')
                ->join('roster_days as rd', 'rd.id', '=', 'md.roster_day_id')
                ->join('roster_periods as rp', 'rp.id', '=', 'rd.roster_period_id')
                ->join('employees as e', 'e.id', '=', 'md.employee_id')
                ->join('users as u', 'u.id', '=', 'e.user_id')
                ->join('shifts as s', 's.id', '=', 'md.shift_id')
                ->where('rd.work_date', $date)
                ->where('rp.status', 'published')
                ->where('md.duty_type', 'Manager Teknik')
                ->where('s.name', strtolower($shiftType))
                ->whereNull('md.deleted_at')
                ->whereNull('rd.deleted_at')
                ->whereNull('e.deleted_at')
                ->whereNull('u.deleted_at')
                ->where('u.is_active', true)
                ->select(
                    'u.id as user_id',
                    'u.name',
                    'u.role',
                    'e.employee_type'
                )
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
     * Get the Supervisor (CNS employee with grade >= 13) for a shift.
     * Returns null if no supervisor-level CNS is assigned (has_supervisor = false).
     *
     * In atoms-rostering, "Supervisor" = CNS employee with grade 13 (SVP CNS)
     * or grade 14 (SPV CNS). There is no separate supervisor role.
     *
     * @param  string  $shiftType  'pagi' | 'siang' | 'malam'
     * @param  string  $date       'Y-m-d'
     * @return object{user_id, name, role, grade}|null
     */
    public function getShiftSupervisor(string $shiftType, string $date): ?object
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
                ->where('e.employee_type', 'CNS')
                ->where('u.grade', '>=', 13) // grade 13 = SVP CNS, grade 14 = SPV CNS
                ->whereNull('sa.deleted_at')
                ->whereNull('rd.deleted_at')
                ->whereNull('e.deleted_at')
                ->whereNull('u.deleted_at')
                ->where('u.is_active', true)
                ->select(
                    'u.id as user_id',
                    'u.name',
                    'u.role',
                    'u.grade'
                )
                ->orderByDesc('u.grade') // highest grade first (SVP before SPV)
                ->first();
        } catch (\Exception $e) {
            Log::warning('RosteringIntegrationService::getShiftSupervisor failed', [
                'shift_type' => $shiftType,
                'date'       => $date,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }
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
     *   personnel: Collection,
     *   has_supervisor: bool,
     *   roster_available: bool
     * }
     */
    public function getShiftContext(string $shiftType, string $date): array
    {
        $shiftTimes  = $this->getShiftTimes($shiftType);
        $manager     = $this->getShiftManager($shiftType, $date);
        $supervisor  = $this->getShiftSupervisor($shiftType, $date);
        $personnel   = $this->getShiftPersonnel($shiftType, $date);

        return [
            'date'             => $date,
            'shift_type'       => $shiftType,
            'shift_times'      => $shiftTimes,
            'manager'          => $manager,
            'supervisor'       => $supervisor,
            'personnel'        => $personnel,
            'has_supervisor'   => $supervisor !== null,
            'roster_available' => $personnel->isNotEmpty() || $manager !== null,
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
