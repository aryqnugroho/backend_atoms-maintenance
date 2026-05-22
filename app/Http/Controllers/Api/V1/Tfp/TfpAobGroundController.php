<?php

namespace App\Http\Controllers\Api\V1\Tfp;

use App\Exceptions\SignerNotAuthorizedException;
use App\Exceptions\TfpAobGroundDuplicateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tfp\CreateTfpAobGroundRequest;
use App\Http\Requests\Tfp\SaveTfpAobGroundStructureRequest;
use App\Http\Requests\Tfp\SignTfpAobGroundRequest;
use App\Http\Requests\Tfp\UpdateTfpAobGroundRequest;
use App\Models\Tfp\TfpAobGroundRecord;
use App\Services\Tfp\TfpActivityLogger;
use App\Services\Tfp\TfpAobGroundService;
use App\Services\Tfp\TfpAobGroundTemplate;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;

class TfpAobGroundController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TfpAobGroundService $service,
        protected TfpActivityLogger $activityLogger,
    ) {}

    /**
     * GET /api/v1/tfp/aob-ground
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'form_type', 'date', 'year', 'shift_type', 'status', 'search', 'sort_by', 'sort_dir',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        $records = $this->service->listRecords($filters, $perPage);

        $records->through(fn (TfpAobGroundRecord $r) => $this->summarizeRecord($r));

        return $this->success($records, 'TFP AOB Ground records retrieved successfully');
    }

    /**
     * GET /api/v1/tfp/aob-ground/template
     */
    public function template(): JsonResponse
    {
        return $this->success([
            'form_type'   => 'AOB-GROUND',
            'location'    => 'AOB LANTAI GROUND',
            'parameters'  => TfpAobGroundTemplate::parameters(),
            'facilities'  => TfpAobGroundTemplate::facilities(),
        ], 'TFP AOB Ground template retrieved successfully');
    }

    /**
     * POST /api/v1/tfp/aob-ground
     */
    public function store(CreateTfpAobGroundRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = Auth::user();

        if (!$user || (!$user->isAdmin() && !$user->isManager() && !$user->isSupervisor() && !$user->isTeknisi())) {
            return $this->error('Unauthorized.', null, 403);
        }

        try {
            $record = $this->service->createRecord($data, $user);
        } catch (TfpAobGroundDuplicateException $e) {
            return $this->error(
                $e->getMessage(),
                ['existing_record' => $this->detailRecord($e->existingRecord)],
                409,
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        try {
            $this->activityLogger->appendLogbookNote(
                'Performance Check AOB Lantai Ground',
                $record->date->format('Y-m-d'),
                $record->shift_type,
                $user,
            );
        } catch (\Throwable) { /* non-fatal */ }

        return $this->success($this->detailRecord($record), 'TFP AOB Ground record created successfully', 201);
    }

    /**
     * GET /api/v1/tfp/aob-ground/{id}
     */
    public function show(int $id): JsonResponse
    {
        $record = $this->service->findRecord($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }

        return $this->success($this->detailRecord($record), 'TFP AOB Ground record retrieved successfully');
    }

    /**
     * PUT /api/v1/tfp/aob-ground/{id}
     */
    public function update(UpdateTfpAobGroundRequest $request, int $id): JsonResponse
    {
        $record = TfpAobGroundRecord::find($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }

        $user = Auth::user();
        if (!$user || (!$user->isAdmin() && !$user->isManager() && !$user->isSupervisor() && !$user->isTeknisi())) {
            return $this->error('Unauthorized.', null, 403);
        }

        $validated = $request->validated();
        $timeOverride = $validated['time_filled'] ?? null;

        try {
            $record = $this->service->updateItems($record, $validated['items'], $timeOverride);

            if (!empty($validated['facilities'])) {
                $record = $this->service->updateFacilities($record, $validated['facilities'], $timeOverride);
            }
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success($this->detailRecord($record), 'TFP AOB Ground record updated successfully');
    }

    /**
     * POST /api/v1/tfp/aob-ground/{id}/sign
     */
    public function sign(SignTfpAobGroundRequest $request, int $id): JsonResponse
    {
        $record = TfpAobGroundRecord::find($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }

        $user = Auth::user();
        if (!$user) {
            return $this->error('Unauthenticated.', null, 401);
        }

        $payload = $request->validated();

        try {
            $record = $this->service->signRecord(
                $record,
                $payload['role'],
                $payload['signature'],
                $user,
                $payload['technician_row_id'] ?? null,
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
            'record'      => $this->detailRecord($record),
        ], 'Signature saved successfully');
    }

    // ─── Structural edit (Manager / Supervisor / Admin only) ──────────────

    /**
     * PUT /api/v1/tfp/aob-ground/{id}/structure
     *
     * Batch save the Excel-like editor state: columns_config + per-item
     * disabled / merge maps. Replaces existing values atomically.
     */
    public function saveStructure(SaveTfpAobGroundStructureRequest $request, int $id): JsonResponse
    {
        $record = TfpAobGroundRecord::find($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }
        if (!$this->canEditStructure()) {
            return $this->error('Hanya Manager Teknik atau Supervisor TFP yang dapat mengubah struktur tabel.', null, 403);
        }

        $payload = $request->validated();

        try {
            $record = $this->service->saveStructure(
                $record,
                $payload['columns_config'],
                $payload['items'] ?? [],
            );
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success($this->detailRecord($record), 'Struktur tabel berhasil disimpan.');
    }

    /**
     * POST /api/v1/tfp/aob-ground/{id}/parameters
     */
    public function addParameter(Request $request, int $id): JsonResponse
    {
        $record = TfpAobGroundRecord::find($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }
        if (!$this->canEditStructure()) {
            return $this->error('Hanya Manager Teknik atau Supervisor TFP yang dapat menambah parameter.', null, 403);
        }

        $payload = $request->validate([
            'parameter_number' => ['nullable', 'string', 'max:10'],
            'parameter_name'   => ['required', 'string', 'max:200'],
            'unit'             => ['nullable', 'string', 'max:30'],
        ]);

        try {
            $record = $this->service->addParameter($record, $payload);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success($this->detailRecord($record), 'Parameter ditambahkan.');
    }

    /**
     * PUT /api/v1/tfp/aob-ground/{id}/parameters/{paramId}
     */
    public function updateParameter(Request $request, int $id, int $paramId): JsonResponse
    {
        $record = TfpAobGroundRecord::find($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }
        if (!$this->canEditStructure()) {
            return $this->error('Hanya Manager Teknik atau Supervisor TFP yang dapat mengubah struktur parameter.', null, 403);
        }

        $payload = $request->validate([
            'parameter_number' => ['sometimes', 'nullable', 'string', 'max:10'],
            'parameter_name'   => ['sometimes', 'string', 'max:200'],
            'unit'             => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        try {
            $record = $this->service->updateParameterStructure($record, $paramId, $payload);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 404);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success($this->detailRecord($record), 'Parameter diperbarui.');
    }

    /**
     * DELETE /api/v1/tfp/aob-ground/{id}/parameters/{paramId}
     */
    public function deleteParameter(int $id, int $paramId): JsonResponse
    {
        $record = TfpAobGroundRecord::find($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }
        if (!$this->canEditStructure()) {
            return $this->error('Hanya Manager Teknik atau Supervisor TFP yang dapat menghapus parameter.', null, 403);
        }

        try {
            $record = $this->service->deleteParameter($record, $paramId);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 404);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success($this->detailRecord($record), 'Parameter dihapus.');
    }

    /**
     * PUT /api/v1/tfp/aob-ground/{id}/parameters-reorder
     */
    public function reorderParameters(Request $request, int $id): JsonResponse
    {
        $record = TfpAobGroundRecord::find($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }
        if (!$this->canEditStructure()) {
            return $this->error('Hanya Manager Teknik atau Supervisor TFP yang dapat mengubah urutan.', null, 403);
        }

        $payload = $request->validate([
            'ordered_ids'   => ['required', 'array'],
            'ordered_ids.*' => ['integer'],
        ]);

        try {
            $record = $this->service->reorderParameters($record, $payload['ordered_ids']);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success($this->detailRecord($record), 'Urutan parameter diperbarui.');
    }

    /**
     * POST /api/v1/tfp/aob-ground/{id}/facilities
     */
    public function addFacility(Request $request, int $id): JsonResponse
    {
        $record = TfpAobGroundRecord::find($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }
        if (!$this->canEditStructure()) {
            return $this->error('Hanya Manager Teknik atau Supervisor TFP yang dapat menambah fasilitas.', null, 403);
        }

        $payload = $request->validate([
            'facility_name' => ['required', 'string', 'max:100'],
        ]);

        try {
            $record = $this->service->addFacility($record, $payload);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success($this->detailRecord($record), 'Fasilitas ditambahkan.');
    }

    /**
     * PUT /api/v1/tfp/aob-ground/{id}/facilities/{facilityId}
     */
    public function updateFacility(Request $request, int $id, int $facilityId): JsonResponse
    {
        $record = TfpAobGroundRecord::find($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }
        if (!$this->canEditStructure()) {
            return $this->error('Hanya Manager Teknik atau Supervisor TFP yang dapat mengubah struktur fasilitas.', null, 403);
        }

        $payload = $request->validate([
            'facility_name' => ['sometimes', 'string', 'max:100'],
        ]);

        try {
            $record = $this->service->updateFacilityStructure($record, $facilityId, $payload);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 404);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success($this->detailRecord($record), 'Fasilitas diperbarui.');
    }

    /**
     * DELETE /api/v1/tfp/aob-ground/{id}/facilities/{facilityId}
     */
    public function deleteFacility(int $id, int $facilityId): JsonResponse
    {
        $record = TfpAobGroundRecord::find($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }
        if (!$this->canEditStructure()) {
            return $this->error('Hanya Manager Teknik atau Supervisor TFP yang dapat menghapus fasilitas.', null, 403);
        }

        try {
            $record = $this->service->deleteFacility($record, $facilityId);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 404);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success($this->detailRecord($record), 'Fasilitas dihapus.');
    }

    /**
     * PUT /api/v1/tfp/aob-ground/{id}/facilities-reorder
     */
    public function reorderFacilities(Request $request, int $id): JsonResponse
    {
        $record = TfpAobGroundRecord::find($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }
        if (!$this->canEditStructure()) {
            return $this->error('Hanya Manager Teknik atau Supervisor TFP yang dapat mengubah urutan.', null, 403);
        }

        $payload = $request->validate([
            'ordered_ids'   => ['required', 'array'],
            'ordered_ids.*' => ['integer'],
        ]);

        try {
            $record = $this->service->reorderFacilities($record, $payload['ordered_ids']);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success($this->detailRecord($record), 'Urutan fasilitas diperbarui.');
    }

    /**
     * Role guard for structural edits. Manager Teknik / Supervisor TFP / Admin
     * only — Teknisi can fill values but not rename/add/delete/reorder rows.
     * Mirrors the role guard pattern used in CnsdReadinessController.
     */
    private function canEditStructure(): bool
    {
        $user = Auth::user();
        return $user && ($user->isAdmin() || $user->isManager() || $user->isSupervisor());
    }

    /**
     * DELETE /api/v1/tfp/aob-ground/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $record = TfpAobGroundRecord::find($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }

        $user = Auth::user();
        if (!$user || (!$user->isAdmin() && !$user->isManager())) {
            return $this->error('Unauthorized.', null, 403);
        }

        $this->service->deleteRecord($record);
        return $this->success(null, 'TFP AOB Ground record deleted successfully');
    }

    /**
     * GET /api/v1/tfp/aob-ground/years
     */
    public function years(): JsonResponse
    {
        $years = TfpAobGroundRecord::selectRaw('EXTRACT(YEAR FROM date)::int AS y')
            ->whereNotNull('date')
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

    // ─── Transformers ─────────────────────────────────────────

    private function summarizeRecord(TfpAobGroundRecord $r): array
    {
        return [
            'id'                => $r->id,
            'form_number'       => $r->form_number,
            'form_type'         => $r->form_type,
            'date'              => $r->date?->format('Y-m-d'),
            'day_name'          => $r->day_name,
            'time_filled'       => $r->time_filled,
            'shift_type'        => $r->shift_type,
            'location'          => $r->location,
            'status'            => $r->status,
            'manager_name'      => $r->manager_name,
            'supervisor_name'   => $r->supervisor_name,
            'technicians_count' => $r->technicians_count ?? $r->technicians()->count(),
            'technician_names'  => $r->technicians->pluck('technician_name')->values()->toArray(),
            'created_at'        => $r->created_at?->toISOString(),
        ];
    }

    private function detailRecord(TfpAobGroundRecord $r): array
    {
        $r->loadMissing([
            'technicians',
            'items',
            'facilities',
            'manager:id,name',
            'supervisor:id,name',
            'creator:id,name',
        ]);

        return [
            'id'             => $r->id,
            'form_number'    => $r->form_number,
            'form_type'      => $r->form_type,
            'date'           => $r->date?->format('Y-m-d'),
            'day_name'       => $r->day_name,
            'time_filled'    => $r->time_filled,
            'shift_type'     => $r->shift_type,
            'location'       => $r->location,
            'columns_config' => $r->columns_config ?: TfpAobGroundTemplate::defaultColumnsConfig(),
            'status'         => $r->status,
            'manager' => $r->manager_name ? [
                'id'        => $r->manager_id,
                'name'      => $r->manager_name,
                'signature' => $r->manager_signature,
                'signed_by' => $r->manager_signed_by,
                'signed_at' => $r->manager_signed_at?->toISOString(),
            ] : null,
            'supervisor' => $r->supervisor_name ? [
                'id'        => $r->supervisor_id,
                'name'      => $r->supervisor_name,
                'signature' => $r->supervisor_signature,
                'signed_by' => $r->supervisor_signed_by,
                'signed_at' => $r->supervisor_signed_at?->toISOString(),
            ] : null,
            'technicians' => $r->technicians->map(fn ($t) => [
                'id'              => $t->id,
                'technician_id'   => $t->technician_id,
                'technician_name' => $t->technician_name,
                'signature'       => $t->technician_signature,
                'signed_by'       => $t->technician_signed_by,
                'signed_at'       => $t->technician_signed_at?->toISOString(),
                'sort_order'      => $t->sort_order,
            ])->values()->toArray(),
            'items' => $r->items->map(fn ($it) => [
                'id'               => $it->id,
                'parameter_number' => $it->parameter_number,
                'parameter_name'   => $it->parameter_name,
                'unit'             => $it->unit,
                'values'           => is_array($it->values) ? $it->values : (object) [],
                'is_disabled_map'  => is_array($it->is_disabled_map) ? $it->is_disabled_map : (object) [],
                'merge_map'        => is_array($it->merge_map) ? $it->merge_map : (object) [],
                'sort_order'       => $it->sort_order,
            ])->values()->toArray(),
            'facilities' => $r->facilities->map(fn ($f) => [
                'id'            => $f->id,
                'facility_name' => $f->facility_name,
                'kondisi'       => $f->kondisi,
                'keterangan'    => $f->keterangan,
                'sort_order'    => $f->sort_order,
            ])->values()->toArray(),
            'created_by' => $r->created_by_id ? ['id' => $r->created_by_id, 'name' => $r->created_by_name] : null,
            'created_at' => $r->created_at?->toISOString(),
            'updated_at' => $r->updated_at?->toISOString(),
        ];
    }
}
