<?php

namespace App\Http\Controllers\Api\V1\Cnsd;

use App\Exceptions\CnsdVccsMeterDuplicateException;
use App\Exceptions\SignerNotAuthorizedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cnsd\CreateCnsdVccsMeterRequest;
use App\Http\Requests\Cnsd\SignCnsdVccsMeterRequest;
use App\Http\Requests\Cnsd\UpdateCnsdVccsMeterRequest;
use App\Models\Cnsd\CnsdVccsMeterRecord;
use App\Services\Cnsd\CnsdActivityLogger;
use App\Services\Cnsd\CnsdVccsMeterService;
use App\Services\Cnsd\CnsdVccsMeterTemplate;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;

class CnsdVccsMeterController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CnsdVccsMeterService $service,
        protected CnsdActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'form_type', 'date', 'year', 'shift_type', 'status', 'search', 'sort_by', 'sort_dir',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        $records = $this->service->listRecords($filters, $perPage);

        $records->through(fn (CnsdVccsMeterRecord $r) => $this->summarizeRecord($r));

        return $this->success($records, 'CNSD VCCS Meter records retrieved successfully');
    }

    public function template(): JsonResponse
    {
        return $this->success([
            'form_type'             => 'VCCS-METER',
            'facility'              => 'VCCS',
            'merk_default'          => 'LES',
            'type_default'          => null,
            'serial_number_default' => null,
            'sections'              => CnsdVccsMeterTemplate::sections(),
        ], 'VCCS Meter template retrieved successfully');
    }

    public function store(CreateCnsdVccsMeterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = Auth::user();

        if (!$user || (!$user->isAdmin() && !$user->isManager() && !$user->isSupervisor() && !$user->isTeknisi())) {
            return $this->error('Unauthorized.', null, 403);
        }

        try {
            $record = $this->service->createRecord($data, $user);
        } catch (CnsdVccsMeterDuplicateException $e) {
            return $this->error(
                $e->getMessage(),
                ['existing_record' => $this->detailRecord($e->existingRecord)],
                409,
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        try {
            $this->activityLogger->logMeterReadingCreated($record, 'VCCS', '/cnsd/vccs-meter', $user);
        } catch (\Throwable) { /* non-fatal */ }

        return $this->success($this->detailRecord($record), 'CNSD VCCS Meter record created successfully', 201);
    }

    public function show(int $id): JsonResponse
    {
        $record = $this->service->findRecord($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }

        return $this->success($this->detailRecord($record), 'CNSD VCCS Meter record retrieved successfully');
    }

    public function update(UpdateCnsdVccsMeterRequest $request, int $id): JsonResponse
    {
        $record = CnsdVccsMeterRecord::find($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }

        $user = Auth::user();
        if (!$user || (!$user->isAdmin() && !$user->isManager() && !$user->isSupervisor() && !$user->isTeknisi())) {
            return $this->error('Unauthorized.', null, 403);
        }

        try {
            $record = $this->service->updateRecord($record, $request->validated());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success($this->detailRecord($record), 'CNSD VCCS Meter record updated successfully');
    }

    public function sign(SignCnsdVccsMeterRequest $request, int $id): JsonResponse
    {
        $record = CnsdVccsMeterRecord::find($id);
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

    public function destroy(int $id): JsonResponse
    {
        $record = CnsdVccsMeterRecord::find($id);
        if (!$record) {
            return $this->error('Form tidak ditemukan.', null, 404);
        }

        $user = Auth::user();
        if (!$user || (!$user->isAdmin() && !$user->isManager() && !$user->isSupervisor())) {
            return $this->error('Unauthorized.', null, 403);
        }

        $this->service->deleteRecord($record);
        return $this->success(null, 'CNSD VCCS Meter record deleted successfully');
    }

    public function years(): JsonResponse
    {
        $years = CnsdVccsMeterRecord::selectRaw('EXTRACT(YEAR FROM date)::int AS y')
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

    private function summarizeRecord(CnsdVccsMeterRecord $r): array
    {
        return [
            'id'                => $r->id,
            'form_number'       => $r->form_number,
            'form_type'         => $r->form_type,
            'facility'          => $r->facility,
            'date'              => $r->date?->format('Y-m-d'),
            'shift_type'        => $r->shift_type,
            'day_name'          => $r->day_name,
            'time_filled'       => $r->time_filled,
            'location'          => $r->location,
            'merk'              => $r->merk,
            'type'              => $r->type,
            'serial_number'     => $r->serial_number,
            'status'            => $r->status,
            'manager_name'      => $r->manager_name,
            'supervisor_name'   => $r->supervisor_name,
            'technicians_count' => $r->technicians_count ?? $r->technicians()->count(),
            'technician_names'  => $r->technicians->pluck('technician_name')->values()->toArray(),
            'created_at'        => $r->created_at?->toISOString(),
        ];
    }

    private function detailRecord(CnsdVccsMeterRecord $r): array
    {
        $r->loadMissing(['technicians', 'items', 'manager:id,name', 'supervisor:id,name', 'creator:id,name']);

        return [
            'id'              => $r->id,
            'form_number'     => $r->form_number,
            'form_type'       => $r->form_type,
            'facility'        => $r->facility,
            'date'            => $r->date?->format('Y-m-d'),
            'shift_type'      => $r->shift_type,
            'day_name'        => $r->day_name,
            'time_filled'     => $r->time_filled,
            'location'        => $r->location,
            'merk'            => $r->merk,
            'type'            => $r->type,
            'serial_number'   => $r->serial_number,
            'status'          => $r->status,
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
                'id'           => $it->id,
                'section_code' => $it->section_code,
                'section_name' => $it->section_name,
                'group_number' => $it->group_number,
                'group_name'   => $it->group_name,
                'item_number'  => $it->item_number,
                'item_name'    => $it->item_name,
                'nominal'      => $it->nominal,
                'hasil_a'      => $it->hasil_a,
                'hasil_b'      => $it->hasil_b,
                'hasil'        => $it->hasil,
                'keterangan'   => $it->keterangan,
                'is_blocked'   => (bool) $it->is_blocked,
                'block_reason' => $it->block_reason,
                'sort_order'   => $it->sort_order,
            ])->values()->toArray(),
            'sections_meta' => CnsdVccsMeterTemplate::sectionMeta(),
            'created_by'    => $r->created_by_id ? ['id' => $r->created_by_id, 'name' => $r->created_by_name] : null,
            'created_at'    => $r->created_at?->toISOString(),
            'updated_at'    => $r->updated_at?->toISOString(),
        ];
    }
}
