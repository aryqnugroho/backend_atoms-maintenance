<?php

namespace App\Http\Controllers\Api\V1\Tfp;

use App\Exceptions\SignerNotAuthorizedException;
use App\Exceptions\TfpTowerDuplicateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tfp\CreateTfpTowerRequest;
use App\Http\Requests\Tfp\SignTfpTowerRequest;
use App\Http\Requests\Tfp\UpdateTfpTowerRequest;
use App\Models\Tfp\TfpTowerRecord;
use App\Services\Tfp\TfpTowerService;
use App\Services\Tfp\TfpTowerTemplate;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;

class TfpTowerController extends Controller
{
    use ApiResponse;

    public function __construct(protected TfpTowerService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['form_type', 'date', 'year', 'shift_type', 'status', 'search', 'sort_by', 'sort_dir']);
        $perPage = min((int) $request->input('per_page', 15), 100);
        $records = $this->service->listRecords($filters, $perPage);
        $records->through(fn (TfpTowerRecord $r) => $this->summarizeRecord($r));
        return $this->success($records, 'TFP Tower records retrieved successfully');
    }

    public function template(): JsonResponse
    {
        return $this->success([
            'form_type'  => 'TOWER',
            'location'   => 'GEDUNG TOWER',
            'parameters' => TfpTowerTemplate::parameters(),
            'facilities' => TfpTowerTemplate::facilities(),
        ], 'TFP Tower template retrieved successfully');
    }

    public function store(CreateTfpTowerRequest $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || (!$user->isAdmin() && !$user->isManager() && !$user->isSupervisor() && !$user->isTeknisi())) {
            return $this->error('Unauthorized.', null, 403);
        }
        try {
            $record = $this->service->createRecord($request->validated(), $user);
        } catch (TfpTowerDuplicateException $e) {
            return $this->error($e->getMessage(), ['existing_record' => $this->detailRecord($e->existingRecord)], 409);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }
        return $this->success($this->detailRecord($record), 'TFP Tower record created successfully', 201);
    }

    public function show(int $id): JsonResponse
    {
        $record = $this->service->findRecord($id);
        if (!$record) return $this->error('Form tidak ditemukan.', null, 404);
        return $this->success($this->detailRecord($record), 'TFP Tower record retrieved successfully');
    }

    public function update(UpdateTfpTowerRequest $request, int $id): JsonResponse
    {
        $record = TfpTowerRecord::find($id);
        if (!$record) return $this->error('Form tidak ditemukan.', null, 404);
        $user = Auth::user();
        if (!$user || (!$user->isAdmin() && !$user->isManager() && !$user->isSupervisor() && !$user->isTeknisi())) {
            return $this->error('Unauthorized.', null, 403);
        }
        $validated = $request->validated();
        try {
            $record = $this->service->updateItems($record, $validated['items']);
            if (!empty($validated['facilities'])) {
                $record = $this->service->updateFacilities($record, $validated['facilities']);
            }
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 409);
        }
        return $this->success($this->detailRecord($record), 'TFP Tower record updated successfully');
    }

    public function sign(SignTfpTowerRequest $request, int $id): JsonResponse
    {
        $record = TfpTowerRecord::find($id);
        if (!$record) return $this->error('Form tidak ditemukan.', null, 404);
        $user = Auth::user();
        if (!$user) return $this->error('Unauthenticated.', null, 401);
        $payload = $request->validated();
        try {
            $record = $this->service->signRecord($record, $payload['role'], $payload['signature'], $user, $payload['technician_row_id'] ?? null);
        } catch (SignerNotAuthorizedException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 409);
        }
        return $this->success(['signed_role' => $payload['role'], 'record' => $this->detailRecord($record)], 'Signature saved successfully');
    }

    public function destroy(int $id): JsonResponse
    {
        $record = TfpTowerRecord::find($id);
        if (!$record) return $this->error('Form tidak ditemukan.', null, 404);
        $user = Auth::user();
        if (!$user || (!$user->isAdmin() && !$user->isManager())) return $this->error('Unauthorized.', null, 403);
        $this->service->deleteRecord($record);
        return $this->success(null, 'TFP Tower record deleted successfully');
    }

    public function years(): JsonResponse
    {
        $years = TfpTowerRecord::selectRaw('EXTRACT(YEAR FROM date)::int AS y')
            ->whereNotNull('date')->groupBy('y')->orderByDesc('y')->pluck('y')->values();
        $currentYear = (int) now()->format('Y');
        if (!$years->contains($currentYear)) $years = collect([$currentYear])->merge($years)->values();
        return $this->success($years, 'Available years retrieved');
    }

    private function summarizeRecord(TfpTowerRecord $r): array
    {
        return [
            'id' => $r->id, 'form_number' => $r->form_number, 'form_type' => $r->form_type,
            'date' => $r->date?->format('Y-m-d'), 'day_name' => $r->day_name, 'time_filled' => $r->time_filled,
            'shift_type' => $r->shift_type, 'location' => $r->location, 'status' => $r->status,
            'manager_name' => $r->manager_name, 'supervisor_name' => $r->supervisor_name,
            'technicians_count' => $r->technicians_count ?? $r->technicians()->count(),
            'technician_names'  => $r->technicians->pluck('technician_name')->values()->toArray(),
            'created_at' => $r->created_at?->toISOString(),
        ];
    }

    private function detailRecord(TfpTowerRecord $r): array
    {
        $r->loadMissing(['technicians', 'items', 'facilities', 'manager:id,name', 'supervisor:id,name', 'creator:id,name']);
        return [
            'id' => $r->id, 'form_number' => $r->form_number, 'form_type' => $r->form_type,
            'date' => $r->date?->format('Y-m-d'), 'day_name' => $r->day_name, 'time_filled' => $r->time_filled,
            'shift_type' => $r->shift_type, 'location' => $r->location, 'status' => $r->status,
            'manager' => $r->manager_name ? ['id' => $r->manager_id, 'name' => $r->manager_name, 'signature' => $r->manager_signature, 'signed_by' => $r->manager_signed_by, 'signed_at' => $r->manager_signed_at?->toISOString()] : null,
            'supervisor' => $r->supervisor_name ? ['id' => $r->supervisor_id, 'name' => $r->supervisor_name, 'signature' => $r->supervisor_signature, 'signed_by' => $r->supervisor_signed_by, 'signed_at' => $r->supervisor_signed_at?->toISOString()] : null,
            'technicians' => $r->technicians->map(fn ($t) => ['id' => $t->id, 'technician_id' => $t->technician_id, 'technician_name' => $t->technician_name, 'signature' => $t->technician_signature, 'signed_by' => $t->technician_signed_by, 'signed_at' => $t->technician_signed_at?->toISOString(), 'sort_order' => $t->sort_order])->values()->toArray(),
            'items' => $r->items->map(fn ($it) => ['id' => $it->id, 'parameter_number' => $it->parameter_number, 'parameter_name' => $it->parameter_name, 'unit' => $it->unit, 'panel_a10' => $it->panel_a10, 'panel_a11' => $it->panel_a11, 'panel_ats_a13_input' => $it->panel_ats_a13_input, 'panel_ats_a13_output' => $it->panel_ats_a13_output, 'panel_a14' => $it->panel_a14, 'panel_a16' => $it->panel_a16, 'panel_a17' => $it->panel_a17, 'panel_a18' => $it->panel_a18, 'panel_a19' => $it->panel_a19, 'panel_a20' => $it->panel_a20, 'panel_milat_ru1213' => $it->panel_milat_ru1213, 'is_disabled_map' => $it->is_disabled_map, 'sort_order' => $it->sort_order])->values()->toArray(),
            'facilities' => $r->facilities->map(fn ($f) => ['id' => $f->id, 'facility_name' => $f->facility_name, 'kondisi' => $f->kondisi, 'keterangan' => $f->keterangan, 'sort_order' => $f->sort_order])->values()->toArray(),
            'created_by' => $r->created_by_id ? ['id' => $r->created_by_id, 'name' => $r->created_by_name] : null,
            'created_at' => $r->created_at?->toISOString(), 'updated_at' => $r->updated_at?->toISOString(),
        ];
    }
}
