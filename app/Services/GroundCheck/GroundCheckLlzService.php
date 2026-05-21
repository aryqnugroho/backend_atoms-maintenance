<?php

namespace App\Services\GroundCheck;

use App\Exceptions\SignerNotAuthorizedException;
use App\Models\GroundCheck\GroundCheckLlzCurvePoint;
use App\Models\GroundCheck\GroundCheckLlzItem;
use App\Models\GroundCheck\GroundCheckLlzPhoto;
use App\Models\GroundCheck\GroundCheckLlzRecord;
use App\Models\GroundCheck\GroundCheckLlzTechnician;
use App\Models\LocalUser;
use App\Services\LocalUserResolver;
use App\Services\RosteringIntegrationService;
use App\Services\WorkOrderService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * GroundCheckLlzService — orchestrates the LLZ Ground Check form.
 *
 * Each record carries:
 *   - items           : Form 1 "Pengujian Berkala di Darat" (hierarchical, with disabled rows)
 *   - curvePoints     : Form 2 "Groundcheck Performance Curve" (17 fixed measurement points)
 *
 * The chart on Form 3 is rendered client-side from curvePoints; no DB storage needed.
 */
class GroundCheckLlzService
{
    public function __construct(
        protected LocalUserResolver $userResolver,
        protected RosteringIntegrationService $rosteringService,
    ) {}

    // ─── Read ──────────────────────────────────────────────────

    public function listRecords(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = GroundCheckLlzRecord::query()
            ->with([
                'technicians:id,ground_check_llz_record_id,technician_id,technician_name,technician_signature,sort_order',
                'manager:id,name',
                'supervisor:id,name',
            ])
            ->withCount('technicians');

        $query->byFormType('GC-LLZ');

        if (!empty($filters['date']))       { $query->byDate($filters['date']); }
        if (!empty($filters['year']))       { $query->whereYear('date', (int) $filters['year']); }
        if (!empty($filters['shift_type'])) { $query->byShift($filters['shift_type']); }
        if (!empty($filters['status']))     { $query->where('status', $filters['status']); }

        if (!empty($filters['search'])) {
            $needle = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($needle) {
                $q->where('form_number', 'ILIKE', $needle)
                    ->orWhere('manager_name', 'ILIKE', $needle)
                    ->orWhere('supervisor_name', 'ILIKE', $needle);
            });
        }

        $sortBy  = $filters['sort_by']  ?? 'date';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowed = ['date', 'created_at', 'shift_type', 'status'];
        if (!in_array($sortBy, $allowed, true)) {
            $sortBy = 'date';
        }

        return $query
            ->orderBy($sortBy, $sortDir)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findRecord(int $id): ?GroundCheckLlzRecord
    {
        return GroundCheckLlzRecord::query()
            ->with([
                'technicians',
                'items',
                'curvePoints',
                'manager:id,name',
                'supervisor:id,name',
                'creator:id,name',
            ])
            ->find($id);
    }

    public function findExistingRecord(string $formType, string $date, string $shiftType): ?GroundCheckLlzRecord
    {
        return GroundCheckLlzRecord::query()
            ->where('form_type', $formType)
            ->whereDate('date', $date)
            ->where('shift_type', $shiftType)
            ->first();
    }

    // ─── Create ────────────────────────────────────────────────

    public function createRecord(array $data, ?LocalUser $creator = null): GroundCheckLlzRecord
    {
        $formType  = 'GC-LLZ';
        $date      = $data['date'];
        $shiftType = $data['shift_type'];

        $existing = $this->findExistingRecord($formType, $date, $shiftType);
        if ($existing) {
            throw new RuntimeException(
                'Ground Check LLZ untuk tanggal ' . $date . ' shift ' . $shiftType . ' sudah ada.',
                409
            );
        }

        $rosterContext = $this->resolveRosterContext($shiftType, $date);

        if (empty($rosterContext['technicians'])) {
            throw new RuntimeException(
                'Tidak ada teknisi CNSD yang bertugas pada tanggal '
                . $date . ' shift ' . $shiftType
                . '. Pastikan roster sudah dipublish dan terdapat personel CNS untuk shift ini.',
                422
            );
        }

        return DB::transaction(function () use (
            $formType, $date, $shiftType, $creator, $rosterContext
        ) {
            $manager    = $rosterContext['manager'];
            $supervisor = $rosterContext['supervisor'];

            $carbonDate = Carbon::parse($date);
            $dayNames   = [
                'Sunday'    => 'Minggu',
                'Monday'    => 'Senin',
                'Tuesday'   => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday'  => 'Kamis',
                'Friday'    => 'Jumat',
                'Saturday'  => 'Sabtu',
            ];
            $dayName = $dayNames[$carbonDate->format('l')] ?? $carbonDate->format('l');

            $monthNames = [
                1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
                5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
                9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER',
            ];
            $reportMonth = ($monthNames[(int) $carbonDate->format('m')] ?? '') . ' ' . $carbonDate->format('Y');

            $record = GroundCheckLlzRecord::create([
                'form_number'         => $this->generateFormNumber($date),
                'form_type'           => $formType,
                'report_month'        => $reportMonth,
                'airport'             => 'JUANDA SURABAYA',
                'equipment_name'      => 'ILS LOCALIZER',
                'equipment_location'  => 'SHELTER LOCALIZER',
                // Pre-fill from paper-form reference (teknisi can override)
                'equipment_function'  => 'SURABAYA',
                'technical_data'      => 'ALAT BANTU',
                'identification'      => 'ISBY',
                'last_calibration'    => null,
                'curve_facility'      => 'Instrument Landing System',
                'curve_merk'          => 'Mopiens 520',
                'curve_ident_freq'    => 'ISBY - 110.10 MHz',
                'curve_jarak_ant'     => '300 M',
                'date'                => $date,
                'time_filled'         => now()->format('H:i'),
                'day_name'            => $dayName,
                'shift_type'          => $shiftType,
                'status'              => 'ongoing',
                'manager_id'          => $manager?->id,
                'manager_name'        => $manager?->name,
                'supervisor_id'       => $supervisor?->id,
                'supervisor_name'     => $supervisor?->name,
                'created_by_id'       => $creator?->id,
                'created_by_name'     => $creator?->name,
            ]);

            // Seed technicians
            $sort = 0;
            foreach ($rosterContext['technicians'] as $tech) {
                GroundCheckLlzTechnician::create([
                    'ground_check_llz_record_id' => $record->id,
                    'technician_id'              => $tech['local_id'],
                    'technician_name'            => $tech['name'],
                    'sort_order'                 => $sort++,
                ]);
            }

            // Seed Form 1 items
            $itemRows = GroundCheckLlzTemplate::buildItemRows($record->id);
            if (!empty($itemRows)) {
                GroundCheckLlzItem::insert($itemRows);
            }

            // Seed Form 2 curve points (17 fixed rows)
            $curveRows = GroundCheckLlzCurveTemplate::buildRows($record->id);
            if (!empty($curveRows)) {
                GroundCheckLlzCurvePoint::insert($curveRows);
            }

            $record->refresh();
            return $record->load([
                'technicians',
                'items',
                'curvePoints',
                'manager:id,name',
                'supervisor:id,name',
            ]);
        });
    }

    private function resolveRosterContext(string $shiftType, string $date): array
    {
        $manager     = null;
        $supervisor  = null;
        $technicians = [];

        try {
            $rosterManager = $this->rosteringService->getShiftManager($shiftType, $date);
            if ($rosterManager) {
                $manager = $this->userResolver->ensureLocalUser((int) $rosterManager->user_id);
            }

            $rosterSupervisor = $this->rosteringService->getShiftSupervisorByDivision($shiftType, $date, 'CNS');
            if ($rosterSupervisor) {
                $supervisor = $this->userResolver->ensureLocalUser((int) $rosterSupervisor->user_id);
            }

            $personnel = $this->rosteringService->getShiftPersonnel($shiftType, $date);
            $cnsOnly   = $personnel->filter(fn ($p) => $p->employee_type === 'CNS')->values();

            foreach ($cnsOnly as $person) {
                $local = $this->userResolver->ensureLocalUser((int) $person->user_id);
                $technicians[] = [
                    'local_id' => $local?->id,
                    'name'     => $person->name,
                    'user_id'  => (int) $person->user_id,
                ];
            }

            $technicians = WorkOrderService::excludeSignerRoles(
                $technicians,
                $rosterSupervisor ? (int) $rosterSupervisor->user_id : null,
                $supervisor?->name,
                $rosterManager ? (int) $rosterManager->user_id : null,
                $manager?->name,
            );
        } catch (\Throwable $e) {
            Log::warning('GroundCheckLlzService: roster lookup failed', [
                'shift_type' => $shiftType,
                'date'       => $date,
                'error'      => $e->getMessage(),
            ]);
        }

        return [
            'manager'     => $manager,
            'supervisor'  => $supervisor,
            'technicians' => $technicians,
        ];
    }

    public function generateFormNumber(string $date): string
    {
        $dateYymmdd = date('ymd', strtotime($date));
        $prefix = 'GC-LLZ-' . $dateYymmdd;

        $count = GroundCheckLlzRecord::withTrashed()
            ->where('form_number', 'LIKE', $prefix . '%')
            ->count();

        return $prefix . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    // ─── Update ────────────────────────────────────────────────

    public function updateRecord(GroundCheckLlzRecord $record, array $data): GroundCheckLlzRecord
    {
        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat diubah lagi.');
        }

        return DB::transaction(function () use ($record, $data) {
            // Metadata fields
            $metaFields = [
                'equipment_location',
                'equipment_function',
                'technical_data',
                'identification',
                'last_calibration',
                'curve_facility',
                'curve_merk',
                'curve_ident_freq',
                'curve_jarak_ant',
            ];
            foreach ($metaFields as $field) {
                if (array_key_exists($field, $data)) {
                    $record->{$field} = $data[$field];
                }
            }

            $timeProvided = array_key_exists('time_filled', $data);
            if ($timeProvided && is_string($data['time_filled']) && $data['time_filled'] !== '') {
                $record->time_filled = $data['time_filled'];
            }

            // Update Form 1 items
            if (!empty($data['items']) && is_array($data['items'])) {
                $existing = $record->items()->get()->keyBy('id');

                foreach ($data['items'] as $payload) {
                    if (empty($payload['id']) || !$existing->has($payload['id'])) {
                        continue;
                    }

                    /** @var GroundCheckLlzItem $item */
                    $item = $existing->get($payload['id']);

                    // Skip headers, subheaders, and disabled rows
                    if ($item->is_header || $item->is_subheader || $item->is_disabled) {
                        continue;
                    }

                    $allowed = [
                        'calibration_result',
                        'tolerance',
                        'tx1_hasil_pd',
                        'tx1_in_tolerance',
                        'tx1_out_of_tolerance',
                        'tx2_hasil_pd',
                        'tx2_in_tolerance',
                        'tx2_out_of_tolerance',
                        'keterangan',
                    ];

                    $item->fill(array_intersect_key($payload, array_flip($allowed)));
                    $item->save();
                }
            }

            // Update Form 2 curve points
            if (!empty($data['curve_points']) && is_array($data['curve_points'])) {
                $existing = $record->curvePoints()->get()->keyBy('id');

                foreach ($data['curve_points'] as $payload) {
                    if (empty($payload['id']) || !$existing->has($payload['id'])) {
                        continue;
                    }

                    /** @var GroundCheckLlzCurvePoint $point */
                    $point = $existing->get($payload['id']);

                    $allowed = [
                        'tx1_ddm_pct', 'tx1_ddm_ua', 'tx1_sum_pct',
                        'tx1_mod_90hz', 'tx1_mod_150hz', 'tx1_rf_level_db',
                        'tx2_ddm_pct', 'tx2_ddm_ua', 'tx2_sum_pct',
                        'tx2_mod_90hz', 'tx2_mod_150hz', 'tx2_rf_level_db',
                    ];

                    $point->fill(array_intersect_key($payload, array_flip($allowed)));
                    $point->save();
                }
            }

            if (!$timeProvided && empty($record->time_filled)) {
                $record->time_filled = now()->format('H:i');
            }
            $record->save();

            return $record->fresh([
                'technicians',
                'items',
                'curvePoints',
                'manager:id,name',
                'supervisor:id,name',
            ]);
        });
    }

    // ─── Sign ──────────────────────────────────────────────────

    public function signRecord(
        GroundCheckLlzRecord $record,
        string $role,
        string $base64Signature,
        LocalUser $signer,
        ?int $technicianRowId = null,
    ): GroundCheckLlzRecord {
        $role = strtolower(trim($role));

        if ($record->status === 'completed') {
            throw new RuntimeException('Form yang sudah completed tidak dapat ditandatangani lagi.');
        }

        return DB::transaction(function () use ($record, $role, $base64Signature, $signer, $technicianRowId) {
            if ($role === 'technician') {
                $this->signTechnicianRow($record, $base64Signature, $signer, $technicianRowId);
            } else {
                $this->signRecordRole($record, $role, $base64Signature, $signer);
            }

            $record->refresh();
            $newStatus = $record->isComplete()
                ? 'completed'
                : ($record->isShiftEnded() ? 'on_hold' : 'ongoing');

            if ($record->status !== $newStatus) {
                $record->status = $newStatus;
                $record->save();
            }

            return $record->fresh([
                'technicians',
                'items',
                'curvePoints',
                'manager:id,name',
                'supervisor:id,name',
            ]);
        });
    }

    private function signRecordRole(
        GroundCheckLlzRecord $record,
        string $role,
        string $base64,
        LocalUser $signer,
    ): void {
        $expectedName = match ($role) {
            'manager'    => $record->manager_name,
            'supervisor' => $record->supervisor_name,
            default      => null,
        };

        if (!$expectedName) {
            throw new SignerNotAuthorizedException(
                'Form ini tidak memiliki ' . ($role === 'manager' ? 'Manager Teknik' : 'Supervisor CNSD')
                . ' yang ditugaskan, sehingga tanda tangan tidak diperlukan.'
            );
        }

        $slotType = \App\Services\SignatureAuthorizationService::slotType($role);
        $targetId = match ($role) {
            'manager'    => $record->manager_id ? (int) $record->manager_id : null,
            'supervisor' => $record->supervisor_id ? (int) $record->supervisor_id : null,
            default      => null,
        };

        \App\Services\SignatureAuthorizationService::authorize($signer, $slotType, $targetId, $expectedName);

        $record->saveSignature($role, $base64, $signer->id);
    }

    private function signTechnicianRow(
        GroundCheckLlzRecord $record,
        string $base64,
        LocalUser $signer,
        ?int $technicianRowId,
    ): void {
        \App\Services\SignatureAuthorizationService::authorize($signer, 'technician', null, null);

        /** @var GroundCheckLlzTechnician|null $row */
        $row = null;
        if ($technicianRowId) {
            $row = $record->technicians()->where('id', $technicianRowId)->first();
        }
        if (!$row && $signer->id) {
            $row = $record->technicians()->where('technician_id', $signer->id)->first();
        }
        if (!$row) {
            $row = $record->technicians()
                ->get()
                ->first(fn (GroundCheckLlzTechnician $t) => WorkOrderService::namesMatch($t->technician_name, $signer->name));
        }

        if (!$row) {
            $row = $record->technicians()->whereNull('technician_signature')->first();
        }

        if (!$row) {
            throw new SignerNotAuthorizedException(
                'Tidak ada slot teknisi yang tersedia untuk ditandatangani pada form ini.'
            );
        }

        if (!empty($row->technician_signature)) {
            throw new RuntimeException('Tanda tangan teknisi sudah tersimpan dan tidak dapat diubah.');
        }

        $this->validateBase64PngSignature($base64);

        $row->technician_signature = $base64;
        $row->technician_signed_by = $signer->id;
        $row->technician_signed_at = now();
        if (in_array('technician_signed_by_name', $row->getFillable(), true) || array_key_exists('technician_signed_by_name', $row->getAttributes())) {
            $row->technician_signed_by_name = $signer->name;
            $row->technician_signed_by_role = $signer->role;
        }
        $row->save();
    }

    private function validateBase64PngSignature(string $base64): void
    {
        $prefix = 'data:image/png;base64,';
        if (!str_starts_with($base64, $prefix)) {
            throw new InvalidArgumentException('Signature must be a base64 PNG data URL.');
        }
        $payload = substr($base64, strlen($prefix));
        if ($payload === '') {
            throw new InvalidArgumentException('Signature payload cannot be empty.');
        }
        $decoded = base64_decode($payload, true);
        if ($decoded === false || $decoded === '') {
            throw new InvalidArgumentException('Signature payload is not valid base64.');
        }
    }

    // ─── Photos ────────────────────────────────────────────────

    public function addPhoto(
        GroundCheckLlzRecord $record,
        \Illuminate\Http\UploadedFile $file,
        ?string $caption,
        ?LocalUser $uploader,
    ): GroundCheckLlzPhoto {
        $dir = "ground-check/llz/{$record->id}";
        $path = $file->store($dir, 'public');
        if (!$path) {
            throw new RuntimeException('Gagal menyimpan file foto.');
        }

        $nextSort = (int) $record->photos()->max('sort_order') + 1;

        return GroundCheckLlzPhoto::create([
            'ground_check_llz_record_id' => $record->id,
            'path'             => $path,
            'original_name'    => $file->getClientOriginalName(),
            'caption'          => $caption,
            'mime_type'        => $file->getMimeType(),
            'size_bytes'       => $file->getSize() ?: 0,
            'uploaded_by_id'   => $uploader?->id,
            'uploaded_by_name' => $uploader?->name,
            'sort_order'       => $nextSort,
        ]);
    }

    public function updatePhotoCaption(GroundCheckLlzPhoto $photo, ?string $caption): GroundCheckLlzPhoto
    {
        $photo->caption = $caption;
        $photo->save();
        return $photo;
    }

    public function deletePhoto(GroundCheckLlzPhoto $photo): void
    {
        $path = $photo->path;
        $photo->delete();
        if ($path) {
            try {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
            } catch (\Throwable $e) {
                Log::warning('GroundCheckLlzService::deletePhoto failed removing file', [
                    'path'  => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    // ─── Delete ────────────────────────────────────────────────

    public function deleteRecord(GroundCheckLlzRecord $record): void
    {
        $record->delete();
    }
}
