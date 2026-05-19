<?php

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Http\Controllers\Controller;
use App\Services\Reporting\ReportingPersonSelectorService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ReportingPersonController — supplies personnel dropdown lists for the
 * Damage Report form.
 *
 * Endpoints:
 *   GET /api/v1/reporting/personnel?role=manager
 *   GET /api/v1/reporting/personnel?scope=repairer&search=...&division=...
 */
class ReportingPersonController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReportingPersonSelectorService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $role     = $request->input('role');     // 'manager' or null
        $scope    = $request->input('scope');    // 'manager' | 'repairer'
        $search   = $request->input('search');
        $division = $request->input('division'); // 'CNSD' | 'TFP' (only for repairer)

        // Default scope to 'manager' if role=manager, else 'repairer'
        if (!$scope) {
            $scope = $role === 'manager' ? 'manager' : 'repairer';
        }

        if ($scope === 'manager') {
            $list = $this->service->getManagers($search ?: null);
        } else {
            $list = $this->service->getRepairers($search ?: null, $division ?: null);
        }

        return $this->success($list, 'Personnel retrieved successfully');
    }
}
