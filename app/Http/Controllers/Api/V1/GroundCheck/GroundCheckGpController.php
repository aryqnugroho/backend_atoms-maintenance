<?php

namespace App\Http\Controllers\Api\V1\GroundCheck;

use App\Http\Controllers\Controller;
use App\Http\Requests\GroundCheck\CreateGroundCheckGpRequest;
use App\Http\Requests\GroundCheck\SignGroundCheckGpRequest;
use App\Http\Requests\GroundCheck\UpdateGroundCheckGpRequest;
use App\Models\GroundCheck\GroundCheckGpPhoto;
use App\Models\GroundCheck\GroundCheckGpRecord;
use App\Services\GroundCheck\GroundCheckGpNavTemplate;
use App\Services\GroundCheck\GroundCheckGpService;
use App\Services\GroundCheck\GroundCheckGpTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroundCheckGpController extends Controller
{
    public function __construct(
        protected GroundCheckGpService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'date', 'year', 'shift_type', 'status', 'sort_by', 'sort_dir']);
        $perPage = min((int) ($request->per_page ?? 15), 100);

        $records = $this->service->listRecords($filters, $perPage);

        $items = $records->getCollection()->map(function (GroundCheckGpRecord $r) {
            return [
                'id'                  => $r->id,
                'form_number'         => $r->form_number,
                'date'                => $r->date->format('Y-m-d'),
                'day_name'            => $r->day_name,
                'time_filled'         => $r->time_filled,
                'shift_type'          => $r->shift_type,
                'status'              => $r->status,
                'report_month'        => $r->report_month,
                'airport'             => $r->airport,
                'equipment_name'      => $r->equipment_name,
                'manager_name'        => $r->manager_name,
                'manager_signature'   => $r->manager_signature ? true : false,
                'supervisor_name'     => $r->supervisor_name,
                'supervisor_signature' => $r->supervisor_signature ? true : false,
                'technicians_count'   => $r->technicians_count,
                'technician_names'    => $r->technicians->pluck('technician_name')->toArray(),
                'created_at'          => $r->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'current_page' => $records->currentPage(),
                'last_page'    => $records->lastPage(),
                'per_page'     => $records->perPage(),
                'total'        => $records->total(),
            ],
        ]);
    }

    public function years(): JsonResponse
    {
        $years = GroundCheckGpRecord::query()
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM date)::int AS year')
            ->orderByDesc('year')
            ->pluck('year')
            ->toArray();

        $currentYear = (int) now()->format('Y');
        if (!in_array($currentYear, $years, true)) {
            array_unshift($years, $currentYear);
        }

        return response()->json([
            'success' => true,
            'data'    => $years,
        ]);
    }

    public function template(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'items'     => GroundCheckGpTemplate::items(),
                'nav_items' => GroundCheckGpNavTemplate::items(),
            ],
        ]);
    }

    public function store(CreateGroundCheckGpRequest $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $record = $this->service->createRecord($request->validated(), $user);

            return response()->json([
                'success' => true,
                'message' => 'Ground Check Glide Path berhasil dibuat.',
                'data'    => $this->formatDetail($record),
            ], 201);
        } catch (\RuntimeException $e) {
            $code = $e->getCode();
            if ($code === 409) {
                $existing = $this->service->findExistingRecord(
                    'GC-GP',
                    $request->date,
                    $request->shift_type
                );
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'existing_record' => $existing ? ['id' => $existing->id, 'form_number' => $existing->form_number] : null,
                ], 409);
            }
            if ($code === 422) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            throw $e;
        }
    }

    public function show(int $id): JsonResponse
    {
        $record = $this->service->findRecord($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Record tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatDetail($record),
        ]);
    }

    public function update(UpdateGroundCheckGpRequest $request, int $id): JsonResponse
    {
        $record = GroundCheckGpRecord::find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Record tidak ditemukan.',
            ], 404);
        }

        try {
            $updated = $this->service->updateRecord($record, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil disimpan.',
                'data'    => $this->formatDetail($updated),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function sign(SignGroundCheckGpRequest $request, int $id): JsonResponse
    {
        $record = GroundCheckGpRecord::with('technicians')->find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Record tidak ditemukan.',
            ], 404);
        }

        $user = Auth::user();

        try {
            $signed = $this->service->signRecord(
                $record,
                $request->role,
                $request->signature,
                $user,
                $request->technician_row_id,
            );

            return response()->json([
                'success' => true,
                'message' => 'Tanda tangan berhasil disimpan.',
                'data'    => $this->formatDetail($signed),
            ]);
        } catch (\App\Exceptions\SignerNotAuthorizedException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $record = GroundCheckGpRecord::find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Record tidak ditemukan.',
            ], 404);
        }

        $this->service->deleteRecord($record);

        return response()->json([
            'success' => true,
            'message' => 'Record berhasil dihapus.',
        ]);
    }

    // ─── Helpers ───────────────────────────────────────────────

    private function formatDetail(GroundCheckGpRecord $record): array
    {
        return [
            'id'                   => $record->id,
            'form_number'          => $record->form_number,
            'form_type'            => $record->form_type,
            'report_month'         => $record->report_month,
            'airport'              => $record->airport,
            'equipment_name'       => $record->equipment_name,
            'equipment_location'   => $record->equipment_location,
            'equipment_function'   => $record->equipment_function,
            'technical_data'       => $record->technical_data,
            'last_calibration'     => $record->last_calibration,
            'nav_organization'     => $record->nav_organization,
            'nav_analyzer_title'   => $record->nav_analyzer_title,
            'date'                 => $record->date->format('Y-m-d'),
            'day_name'             => $record->day_name,
            'time_filled'          => $record->time_filled,
            'shift_type'           => $record->shift_type,
            'status'               => $record->status,
            'manager_id'           => $record->manager_id,
            'manager_name'         => $record->manager_name,
            'manager_signature'    => $record->manager_signature,
            'manager_signed_at'    => $record->manager_signed_at?->toISOString(),
            'supervisor_id'        => $record->supervisor_id,
            'supervisor_name'      => $record->supervisor_name,
            'supervisor_signature' => $record->supervisor_signature,
            'supervisor_signed_at' => $record->supervisor_signed_at?->toISOString(),
            'created_by_id'        => $record->created_by_id,
            'created_by_name'      => $record->created_by_name,
            'technicians'          => $record->technicians->map(fn ($t) => [
                'id'                   => $t->id,
                'technician_id'        => $t->technician_id,
                'technician_name'      => $t->technician_name,
                'technician_signature' => $t->technician_signature,
                'technician_signed_at' => $t->technician_signed_at?->toISOString(),
                'sort_order'           => $t->sort_order,
            ])->toArray(),
            'items'                => $record->items->map(fn ($i) => [
                'id'                    => $i->id,
                'section_name'          => $i->section_name,
                'subsection_name'       => $i->subsection_name,
                'item_code'             => $i->item_code,
                'parameter_name'        => $i->parameter_name,
                'input_type'            => $i->input_type ?? 'text',
                'calibration_result'    => $i->calibration_result,
                'tolerance'             => $i->tolerance,
                'tx1_hasil_pd'          => $i->tx1_hasil_pd,
                'tx1_in_tolerance'      => $i->tx1_in_tolerance,
                'tx1_out_of_tolerance'  => $i->tx1_out_of_tolerance,
                'tx2_hasil_pd'          => $i->tx2_hasil_pd,
                'tx2_in_tolerance'      => $i->tx2_in_tolerance,
                'tx2_out_of_tolerance'  => $i->tx2_out_of_tolerance,
                'keterangan'            => $i->keterangan,
                'is_header'             => $i->is_header,
                'is_subheader'          => $i->is_subheader,
                'is_disabled'           => $i->is_disabled,
                'is_check_only'         => $i->is_check_only,
                'sort_order'            => $i->sort_order,
            ])->toArray(),
            'nav_items'            => $record->navItems->map(fn ($n) => [
                'id'                  => $n->id,
                'section_code'        => $n->section_code,
                'section_label'       => $n->section_label,
                'section_keterangan'  => $n->section_keterangan,
                'item_code'           => $n->item_code,
                'parameter_name'      => $n->parameter_name,
                'tx1_value'           => $n->tx1_value,
                'tx2_value'           => $n->tx2_value,
                'keterangan'          => $n->keterangan,
                'is_section_header'   => $n->is_section_header,
                'sort_order'          => $n->sort_order,
            ])->toArray(),
            'photos'               => $record->photos->map(fn (GroundCheckGpPhoto $p) => [
                'id'              => $p->id,
                'url'             => $p->url,
                'caption'         => $p->caption,
                'original_name'   => $p->original_name,
                'mime_type'       => $p->mime_type,
                'size_bytes'      => $p->size_bytes,
                'uploaded_by_id'  => $p->uploaded_by_id,
                'uploaded_by_name' => $p->uploaded_by_name,
                'sort_order'      => $p->sort_order,
                'uploaded_at'     => $p->created_at?->toISOString(),
            ])->values()->toArray(),
            'created_at'           => $record->created_at?->toISOString(),
            'updated_at'           => $record->updated_at?->toISOString(),
        ];
    }

    // ─── Photos ────────────────────────────────────────────────

    public function uploadPhoto(Request $request, int $id): JsonResponse
    {
        $record = GroundCheckGpRecord::with('photos')->find($id);
        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Record tidak ditemukan.'], 404);
        }

        if ($record->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Form sudah selesai ditandatangani, foto tidak dapat ditambahkan.',
            ], 409);
        }

        $validated = $request->validate([
            'photo'   => ['required', 'file', 'image', 'max:8192'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $user = Auth::user();

        try {
            $this->service->addPhoto($record, $validated['photo'], $validated['caption'] ?? null, $user);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $record->load(['photos', 'items', 'navItems', 'technicians', 'manager', 'supervisor']);
        return response()->json([
            'success' => true,
            'message' => 'Foto berhasil diunggah.',
            'data'    => $this->formatDetail($record),
        ], 201);
    }

    public function updatePhoto(Request $request, int $id, int $photoId): JsonResponse
    {
        $record = GroundCheckGpRecord::find($id);
        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Record tidak ditemukan.'], 404);
        }
        $photo = GroundCheckGpPhoto::where('ground_check_gp_record_id', $id)->find($photoId);
        if (!$photo) {
            return response()->json(['success' => false, 'message' => 'Foto tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $this->service->updatePhotoCaption($photo, $validated['caption'] ?? null);

        $record->load(['photos', 'items', 'navItems', 'technicians', 'manager', 'supervisor']);
        return response()->json([
            'success' => true,
            'message' => 'Caption foto diperbarui.',
            'data'    => $this->formatDetail($record),
        ]);
    }

    public function deletePhoto(int $id, int $photoId): JsonResponse
    {
        $record = GroundCheckGpRecord::find($id);
        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Record tidak ditemukan.'], 404);
        }
        if ($record->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Form sudah selesai ditandatangani, foto tidak dapat dihapus.',
            ], 409);
        }
        $photo = GroundCheckGpPhoto::where('ground_check_gp_record_id', $id)->find($photoId);
        if (!$photo) {
            return response()->json(['success' => false, 'message' => 'Foto tidak ditemukan.'], 404);
        }

        $this->service->deletePhoto($photo);

        $record->load(['photos', 'items', 'navItems', 'technicians', 'manager', 'supervisor']);
        return response()->json([
            'success' => true,
            'message' => 'Foto dihapus.',
            'data'    => $this->formatDetail($record),
        ]);
    }
}
