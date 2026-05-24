<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * RosteringAuthService
 *
 * Validates a Sanctum token issued by atoms-rostering by calling
 * GET {ROSTERING_API_URL}/api/auth/me with the token as a Bearer header.
 *
 * This is the Option 2 (API proxy) approach — no shared DB coupling,
 * no JWT secret. atoms-rostering is the single source of truth for auth.
 *
 * Result is NOT cached between requests — always revalidated per request.
 */
class RosteringAuthService
{
    private string $rosteringApiUrl;

    public function __construct()
    {
        $this->rosteringApiUrl = rtrim(env('ROSTERING_API_URL', 'http://localhost:8001'), '/');
    }

    /**
     * Validate a Sanctum token against atoms-rostering.
     *
     * @param  string  $token  The raw Bearer token from the incoming request.
     * @return array|null      Decoded user data on success, null on failure.
     *
     * Returns array shape:
     * [
     *   'id'        => int,
     *   'name'      => string,
     *   'email'     => string,
     *   'role'      => string,   // 'Admin' | 'Cns' | 'Support' | 'Manager Teknik' | 'General Manager'
     *   'grade'     => int|null,
     *   'is_active' => bool,
     *   'employee'  => [
     *     'id'              => int,
     *     'user_id'         => int,
     *     'employee_type'   => string,
     *     'group_number'    => int|null,
     *     'is_fixed_manager'=> bool,
     *   ] | null,
     * ]
     */
    public function validateToken(string $token): ?array
    {
        try {
            $response = Http::withToken($token)
                ->timeout(5)
                ->get("{$this->rosteringApiUrl}/api/auth/me");

            if ($response->successful()) {
                $body = $response->json();
                // atoms-rostering /auth/me returns { "user": {...} }
                return $body['user'] ?? null;
            }

            // 401 = token invalid/expired, 403 = inactive account
            Log::debug('RosteringAuthService: token validation failed', [
                'status' => $response->status(),
            ]);

            return null;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // atoms-rostering is unreachable — fail closed (deny access)
            Log::warning('RosteringAuthService: cannot reach atoms-rostering', [
                'url'   => $this->rosteringApiUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::error('RosteringAuthService: unexpected error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Upsert a LocalUser from rostering user data and return the Eloquent model.
     *
     * This ensures Auth::setUser() receives a proper Authenticatable instance.
     * Also keeps local_users table in sync with rostering on every authenticated request.
     *
     * @param  array  $rosteringUser  Decoded user data from rostering /api/auth/me
     * @return \App\Models\LocalUser
     */
    public function buildTransientUser(array $rosteringUser): \App\Models\LocalUser
    {
        $grade    = (int) ($rosteringUser['grade'] ?? 0);
        $empType  = $rosteringUser['employee']['employee_type'] ?? '';
        $role     = $this->mapRole($rosteringUser['role'] ?? '', $grade, $empType);
        $division = $this->mapDivision($rosteringUser['role'] ?? '', $rosteringUser['employee'] ?? null);

        /** @var \App\Models\LocalUser $user */
        $user = \App\Models\LocalUser::updateOrCreate(
            ['rostering_user_id' => $rosteringUser['id']],
            [
                'name'      => $rosteringUser['name'],
                'email'     => $rosteringUser['email'],
                'role'      => $role,
                'division'  => $division,
                'is_active' => $rosteringUser['is_active'] ?? true,
                'synced_at' => now(),
            ]
        );

        // Attach transient fields not stored in local_users (grade, employee)
        // so controllers can access them via $request->user()->grade etc.
        $user->grade    = $rosteringUser['grade'] ?? null;
        $user->employee = $rosteringUser['employee'] ?? null;

        return $user;
    }

    /**
     * Map atoms-rostering role strings to atoms-maintenance role strings.
     *
     * Rostering roles:  Admin | Cns | Support | Manager Teknik | General Manager
     * Maintenance roles: Admin | Teknisi CNSD | Supervisor CNSD | Teknisi TFP | Supervisor TFP | Manager Teknik | General Manager
     *
     * Supervisor distinction: grade >= 13 = supervisor.
     */
    private function mapRole(string $rosteringRole, int $grade = 0, string $employeeType = ''): string
    {
        $isSupervisorGrade = $grade >= 13;

        return match ($rosteringRole) {
            'Admin'           => 'Admin',
            'Manager Teknik'  => 'Manager Teknik',
            'General Manager' => 'General Manager',
            'Cns'             => $isSupervisorGrade ? 'Supervisor CNSD' : 'Teknisi CNSD',
            'Support'         => $isSupervisorGrade ? 'Supervisor TFP' : 'Teknisi TFP',
            default           => match ($employeeType) {
                'CNS'     => $isSupervisorGrade ? 'Supervisor CNSD' : 'Teknisi CNSD',
                'Support' => $isSupervisorGrade ? 'Supervisor TFP' : 'Teknisi TFP',
                default   => 'Teknisi CNSD',
            },
        };
    }

    /**
     * Map rostering role + employee data to a maintenance division string.
     */
    private function mapDivision(string $role, ?array $employee): string
    {
        return match ($role) {
            'Admin'           => 'Management',
            'Manager Teknik'  => 'Management',
            'General Manager' => 'Management',
            'Cns'             => 'CNSD',
            'Support'         => 'TFP',
            default           => 'CNSD',
        };
    }
}
