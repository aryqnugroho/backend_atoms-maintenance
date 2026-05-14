<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LocalUser;
use App\Services\RosteringIntegrationService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonnelController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected RosteringIntegrationService $rosteringService
    ) {}

    /**
     * List all active personnel (for form dropdowns).
     * Returns basic user data needed for work order personnel assignment.
     * Source: local_users cache table.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LocalUser::where('is_active', true);

        // Optional filter by division
        if ($request->has('division')) {
            $query->where('division', $request->input('division'));
        }

        // Optional filter by role
        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        $personnel = $query->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'division']);

        return $this->success($personnel, 'Personnel retrieved successfully');
    }

    /**
     * GET /api/v1/personnel/shift-today
     *
     * Returns the real shift context for a given date and shift type,
     * sourced directly from atoms-rostering's database (read-only).
     *
     * Query params:
     *   - date       (optional) Y-m-d, defaults to today
     *   - shift_type (optional) pagi|siang|malam, defaults to current time-based shift
     *
     * Response includes: manager, supervisor, personnel list, has_supervisor flag,
     * shift times, and whether a published roster exists for that date.
     */
    public function shiftToday(Request $request): JsonResponse
    {
        $date = $request->input('date', Carbon::now()->toDateString());
        $shiftType = $request->input('shift_type');

        // Auto-detect shift from current time if not provided
        if (!$shiftType) {
            $hour = (int) Carbon::now()->format('H');
            if ($hour >= 7 && $hour < 13) {
                $shiftType = 'pagi';
            } elseif ($hour >= 13 && $hour < 19) {
                $shiftType = 'siang';
            } else {
                $shiftType = 'malam';
            }
        }

        $shiftType = strtolower($shiftType);

        if (!in_array($shiftType, ['pagi', 'siang', 'malam'])) {
            return $this->error('shift_type must be pagi, siang, or malam.', null, 422);
        }

        $context = $this->rosteringService->getShiftContext($shiftType, $date);

        return $this->success([
            'date'             => $context['date'],
            'shift_type'       => $context['shift_type'],
            'shift_times'      => $context['shift_times'],
            'has_supervisor'   => $context['has_supervisor'],
            'roster_available' => $context['roster_available'],
            'manager'          => $context['manager'],
            'supervisor'       => $context['supervisor'],
            'personnel'        => $context['personnel']->values(),
        ], 'Shift context retrieved successfully');
    }
}
