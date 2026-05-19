<?php

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Exceptions\SignerNotAuthorizedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\CreateReportingDamageReportRequest;
use App\Http\Requests\Reporting\SignReportingDamageReportRequest;
use App\Http\Requests\Reporting\UpdateReportingDamageReportRequest;
use App\Models\Reporting\ReportingDamageReport;
use App\Services\Reporting\ReportingDamageReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;

class ReportingDamageReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReportingDamageReportService $service,
    ) {}

    /**
     * GET /api/v1/reporting/damage-reports
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'date', 'year', 'damage_category', 'obstacle_code',
            'status', 'search', 'sort_by', 'sort_dir',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        $records = $this->service->listReports($filters, $perPage);
        $records->through(fn (ReportingDamageReport $r) => $this->summarizeRecord($r));

        return $this->success($records, 'Damage reports retrieved successfully');
    }

    /**
     * GET /api/v1/reporting/damage-reports/years
     */
    public function years(): JsonResponse
    {
        $years = ReportingDamageReport::selectRaw('EXTRACT(YEAR FROM report_date)::int AS y')
            ->whereNotNull('report_date')
            ->groupBy('y')
            ->orderByDesc('y')
            ->pluck('y')
            ->values();

        $currentYear = (int) now()->format('Y');
        if (!$years->contains($currentYear)) {
            $years = collect([$currentYear])->merge($years)->values();
        }

        return $this->success($years, 'Available years retrieved');
    }

    /**
     * POST /api/v1/reporting/damage-reports
     */
    public function store(CreateReportingDamageReportRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = Auth::user();

        if (!$user || (!$user->isAdmin() && !$user->isManager() && !$user->isSupervisor() && !$user->isTeknisi())) {
            return $this->error('Unauthorized.', null, 403);
        }

        try {
            $report = $this->service->createReport($data, $user);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        return $this->success($this->detailRecord($report), 'Damage report created successfully', 201);
    }

    /**
     * GET /api/v1/reporting/damage-reports/{id}
     */
    public function show(int $id): JsonResponse
    {
        $report = $this->service->findReport($id);
        if (!$report) {
            return $this->error('Laporan tidak ditemukan.', null, 404);
        }

        return $this->success($this->detailRecord($report), 'Damage report retrieved successfully');
    }

    /**
     * PUT /api/v1/reporting/damage-reports/{id}
     */
    public function update(UpdateReportingDamageReportRequest $request, int $id): JsonResponse
    {
        $report = ReportingDamageReport::find($id);
        if (!$report) {
            return $this->error('Laporan tidak ditemukan.', null, 404);
        }

        $user = Auth::user();
        if (!$user || (!$user->isAdmin() && !$user->isManager() && !$user->isSupervisor() && !$user->isTeknisi())) {
            return $this->error('Unauthorized.', null, 403);
        }

        $validated = $request->validated();

        try {
            $report = $this->service->updateReport($report, $validated, $user);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        return $this->success($this->detailRecord($report), 'Damage report updated successfully');
    }

    /**
     * POST /api/v1/reporting/damage-reports/{id}/sign
     */
    public function sign(SignReportingDamageReportRequest $request, int $id): JsonResponse
    {
        $report = ReportingDamageReport::find($id);
        if (!$report) {
            return $this->error('Laporan tidak ditemukan.', null, 404);
        }

        $user = Auth::user();
        if (!$user) {
            return $this->error('Unauthenticated.', null, 401);
        }

        $payload = $request->validated();

        try {
            $report = $this->service->signReport(
                $report,
                $payload['role'],
                $payload['signature'],
                $user,
                $payload['repairer_row_id'] ?? null,
            );
        } catch (SignerNotAuthorizedException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success([
            'signed_role' => $payload['role'],
            'record'      => $this->detailRecord($report),
        ], 'Signature saved successfully');
    }

    /**
     * DELETE /api/v1/reporting/damage-reports/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $report = ReportingDamageReport::find($id);
        if (!$report) {
            return $this->error('Laporan tidak ditemukan.', null, 404);
        }

        $user = Auth::user();
        if (!$user || (!$user->isAdmin() && !$user->isManager())) {
            return $this->error('Unauthorized.', null, 403);
        }

        $this->service->deleteReport($report);
        return $this->success(null, 'Damage report deleted successfully');
    }

    // ─── Transformers ─────────────────────────────────────────

    private function summarizeRecord(ReportingDamageReport $r): array
    {
        return [
            'id'                 => $r->id,
            'report_number'      => $r->report_number,
            'report_date'        => $r->report_date?->format('Y-m-d'),
            'day_name'           => $r->day_name,
            'location'           => $r->location,
            'facility'           => $r->facility,
            'equipment_name'     => $r->equipment_name,
            'equipment_module'   => $r->equipment_module,
            'damage_category'    => $r->damage_category,
            'obstacle_code'      => $r->obstacle_code,
            'status'             => $r->status,
            'manager_name'       => $r->manager_name,
            'repairers_count'    => $r->repairers_count ?? $r->repairers()->count(),
            'repairer_names'     => $r->repairers->pluck('person_name')->values()->toArray(),
            'created_at'         => $r->created_at?->toISOString(),
        ];
    }

    private function detailRecord(ReportingDamageReport $r): array
    {
        $r->loadMissing([
            'repairers',
            'manager:id,name,role',
            'creator:id,name',
        ]);

        return [
            'id'                   => $r->id,
            'report_number'        => $r->report_number,
            'report_date'          => $r->report_date?->format('Y-m-d'),
            'day_name'             => $r->day_name,
            'location'             => $r->location,
            'facility'             => $r->facility,
            'equipment_name'       => $r->equipment_name,
            'equipment_module'     => $r->equipment_module,
            'damage_category'      => $r->damage_category,
            'damage_description'   => $r->damage_description,
            'damage_cause'         => $r->damage_cause,
            'repair_action'        => $r->repair_action,
            'repair_by_type'       => $r->repair_by_type,
            'damage_started_at'    => $r->damage_started_at?->toISOString(),
            'repair_finished_at'   => $r->repair_finished_at?->toISOString(),
            'downtime_hours'       => $r->downtime_hours !== null ? (float) $r->downtime_hours : null,
            'obstacle_code'        => $r->obstacle_code,
            'obstacle_description' => $r->obstacle_description,
            'status'               => $r->status,
            'manager' => $r->manager_name ? [
                'id'        => $r->manager_id,
                'name'      => $r->manager_name,
                'role'      => $r->manager_role,
                'signature' => $r->manager_signature,
                'signed_by' => $r->manager_signed_by,
                'signed_at' => $r->manager_signed_at?->toISOString(),
            ] : null,
            'repairers' => $r->repairers->map(fn ($p) => [
                'id'              => $p->id,
                'person_id'       => $p->person_id,
                'person_name'     => $p->person_name,
                'person_role'     => $p->person_role,
                'person_division' => $p->person_division,
                'signature'       => $p->signature,
                'signed_by'       => $p->signed_by,
                'signed_at'       => $p->signed_at?->toISOString(),
                'sort_order'      => $p->sort_order,
            ])->values()->toArray(),
            'created_by' => $r->created_by_id ? ['id' => $r->created_by_id, 'name' => $r->created_by_name] : null,
            'created_at' => $r->created_at?->toISOString(),
            'updated_at' => $r->updated_at?->toISOString(),
        ];
    }
}
