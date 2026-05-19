<?php

namespace App\Services\Reporting;

use App\Models\LocalUser;
use Illuminate\Support\Collection;

/**
 * ReportingPersonSelectorService — supplies personnel lists for the
 * Reporting/Damage Report form dropdowns.
 *
 * Two scopes:
 *   - manager:  hanya role = "Manager Teknik".
 *   - repairer: gabungan Teknisi CNSD, Teknisi TFP, Supervisor CNSD,
 *               Supervisor TFP, dan Admin (Admin tetap diizinkan menjadi
 *               pelaksana karena merupakan internal user).
 *
 * Source: local_users cache (no roster join, no shift filter).
 */
class ReportingPersonSelectorService
{
    public const REPAIRER_ROLES = [
        'Teknisi CNSD',
        'Teknisi TFP',
        'Supervisor CNSD',
        'Supervisor TFP',
    ];

    public function getManagers(?string $search = null, int $limit = 50): Collection
    {
        $q = LocalUser::query()
            ->where('is_active', true)
            ->where('role', 'Manager Teknik');

        if (!empty($search)) {
            $needle = '%' . $search . '%';
            $q->where('name', 'ILIKE', $needle);
        }

        return $q->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'role', 'division']);
    }

    public function getRepairers(?string $search = null, ?string $division = null, int $limit = 100): Collection
    {
        $q = LocalUser::query()
            ->where('is_active', true)
            ->whereIn('role', self::REPAIRER_ROLES);

        if (!empty($division)) {
            $q->where('division', $division);
        }

        if (!empty($search)) {
            $needle = '%' . $search . '%';
            $q->where('name', 'ILIKE', $needle);
        }

        return $q->orderBy('division')
            ->orderBy('role')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'role', 'division']);
    }
}
