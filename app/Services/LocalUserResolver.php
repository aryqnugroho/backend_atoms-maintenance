<?php

namespace App\Services;

use App\Models\LocalUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * LocalUserResolver
 *
 * Maps a rostering_user_id (the source-of-truth ID owned by atoms-rostering)
 * to the corresponding local_users.id. Lazily creates a local_users row from
 * rostering data when the user has never been seen before in maintenance.
 *
 * Why we need this:
 *   The atoms-maintenance frontend identifies people by their rostering_user_id
 *   (that is what /api/v1/personnel/shift-today returns). FormRequest validation
 *   `exists:local_users,id` was failing because users who never logged in via SSO
 *   are absent from local_users — but they still need to be assignable as Work
 *   Order personnel, manager, supervisor, etc.
 *
 * Read-only towards atoms-rostering: this service NEVER writes to db_rostering.
 * It only reads, then writes to local_users (which is owned by maintenance).
 */
class LocalUserResolver
{
    /**
     * Resolve a rostering_user_id to a local_users.id, creating the row if missing.
     *
     * @param  int  $rosteringUserId
     * @return int|null  local_users.id, or null if the rostering user cannot be found
     */
    public function resolveLocalId(int $rosteringUserId): ?int
    {
        $local = $this->ensureLocalUser($rosteringUserId);
        return $local?->id;
    }

    /**
     * Resolve and return the LocalUser model for the given rostering_user_id.
     * If the user already exists locally, re-sync role/division from rostering
     * in case their grade or employee_type changed.
     */
    public function ensureLocalUser(int $rosteringUserId): ?LocalUser
    {
        // Pull fresh data from rostering DB — read-only join
        try {
            $row = DB::connection('rostering')
                ->table('users as u')
                ->leftJoin('employees as e', 'e.user_id', '=', 'u.id')
                ->where('u.id', $rosteringUserId)
                ->whereNull('u.deleted_at')
                ->select(
                    'u.id', 'u.name', 'u.email', 'u.role', 'u.grade',
                    'e.employee_type', 'e.group_number'
                )
                ->first();
        } catch (\Throwable $e) {
            Log::warning('LocalUserResolver: rostering query failed', [
                'rostering_user_id' => $rosteringUserId,
                'error' => $e->getMessage(),
            ]);
            // If rostering is unreachable, return existing local row if any
            return LocalUser::where('rostering_user_id', $rosteringUserId)->first();
        }

        if (!$row) {
            return null;
        }

        $role = $this->mapRole($row->role ?? '', (int) ($row->grade ?? 0), $row->employee_type ?? '');
        $division = $this->mapDivision($row->employee_type ?? '');

        /** @var LocalUser $user */
        $user = LocalUser::updateOrCreate(
            ['rostering_user_id' => $row->id],
            [
                'name' => $row->name,
                'email' => $row->email,
                'role' => $role,
                'division' => $division,
                'is_active' => true,
                'synced_at' => now(),
            ]
        );

        return $user;
    }

    /**
     * Resolve an array of rostering user_ids in one pass.
     *
     * @param  array<int>  $rosteringUserIds
     * @return array<int,int>  map of rostering_user_id => local_users.id (only resolved ones)
     */
    public function resolveLocalIds(array $rosteringUserIds): array
    {
        $map = [];
        foreach (array_unique(array_filter($rosteringUserIds, 'is_int')) as $rid) {
            $localId = $this->resolveLocalId($rid);
            if ($localId !== null) {
                $map[$rid] = $localId;
            }
        }
        return $map;
    }

    private function mapRole(string $rosteringRole, int $grade, string $employeeType): string
    {
        $isSupervisorGrade = $grade >= 13;

        return match ($rosteringRole) {
            'Admin' => 'Admin',
            'Manager Teknik', 'General Manager' => 'Manager Teknik',
            'Cns' => $isSupervisorGrade ? 'Supervisor CNSD' : 'Teknisi CNSD',
            'Support' => $isSupervisorGrade ? 'Supervisor TFP' : 'Teknisi TFP',
            default => match ($employeeType) {
                'CNS' => 'Teknisi CNSD',
                'Support' => 'Teknisi TFP',
                'Manager Teknik' => 'Manager Teknik',
                default => 'Teknisi CNSD',
            },
        };
    }

    private function mapDivision(string $employeeType): string
    {
        return match ($employeeType) {
            'CNS' => 'CNSD',
            'Support' => 'TFP',
            'Manager Teknik', 'Administrator' => 'Management',
            default => 'Management',
        };
    }
}
