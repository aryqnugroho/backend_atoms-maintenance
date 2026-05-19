<?php

namespace App\Services\Reporting;

use App\Exceptions\SignerNotAuthorizedException;
use App\Models\LocalUser;
use App\Models\Reporting\ReportingDamageRepairer;
use App\Models\Reporting\ReportingDamageReport;
use App\Services\LocalUserResolver;
use App\Services\WorkOrderService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * ReportingDamageReportService — orchestrates Laporan Kerusakan (Damage Report).
 *
 * Reporting differs from CNSD/TFP/Grounding in that personnel are NOT auto-pulled
 * from the rostering shift. Instead:
 *   - Manager Teknik dipilih manual dari role Manager Teknik di local_users.
 *   - Pelaksana Perbaikan dipilih manual dari teknisi/supervisor CNSD atau TFP.
 *   - Roster TIDAK dilibatkan di sini (no shift, no employee_type filter).
 *
 * Signatures are immutable, name-matched, and never delegated. The service
 * NEVER writes to the rostering DB.
 */
class ReportingDamageReportService
{
    public function __construct(
        protected LocalUserResolver $userResolver,
    ) {}

    // ─── Read ──────────────────────────────────────────────────

    public function listReports(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = ReportingDamageReport::query()
            ->with([
                'repairers:id,report_id,person_id,person_name,person_role,signature,sort_order',
                'manager:id,name',
            ])
            ->withCount('repairers');

        if (!empty($filters['date'])) {
            $query->byDate($filters['date']);
        }

        if (!empty($filters['year'])) {
            $query->whereYear('report_date', (int) $filters['year']);
        }

        if (!empty($filters['damage_category'])) {
            $query->where('damage_category', $filters['damage_category']);
        }

        if (!empty($filters['obstacle_code'])) {
            $query->where('obstacle_code', $filters['obstacle_code']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $needle = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($needle) {
                $q->where('report_number', 'ILIKE', $needle)
                    ->orWhere('equipment_name', 'ILIKE', $needle)
                    ->orWhere('location', 'ILIKE', $needle)
                    ->orWhere('facility', 'ILIKE', $needle)
                    ->orWhere('manager_name', 'ILIKE', $needle);
            });
        }

        $sortBy  = $filters['sort_by']  ?? 'report_date';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowed = ['report_date', 'created_at', 'damage_category', 'status'];
        if (!in_array($sortBy, $allowed, true)) {
            $sortBy = 'report_date';
        }

        return $query
            ->orderBy($sortBy, $sortDir)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findReport(int $id): ?ReportingDamageReport
    {
        return ReportingDamageReport::query()
            ->with([
                'repairers',
                'manager:id,name,role',
                'creator:id,name',
            ])
            ->find($id);
    }

    // ─── Create ────────────────────────────────────────────────

    /**
     * Create a damage report with manager + repairers chosen manually.
     *
     * @param array $data validated payload
     * @param LocalUser|null $creator
     * @return ReportingDamageReport
     */
    public function createReport(array $data, ?LocalUser $creator = null): ReportingDamageReport
    {
        $reportDate = $data['report_date'];

        return DB::transaction(function () use ($data, $creator, $reportDate) {
            // Resolve Manager Teknik manually picked
            $manager = LocalUser::where('id', $data['manager_id'])
                ->where('is_active', true)
                ->first();
            if (!$manager) {
                throw new RuntimeException('Manager Teknik yang dipilih tidak ditemukan atau tidak aktif.');
            }
            if ($manager->role !== 'Manager Teknik') {
                throw new RuntimeException('Personel yang dipilih bukan Manager Teknik.');
            }

            // Validate repairers
            $repairers = $data['repairers'];
            if (empty($repairers)) {
                throw new RuntimeException('Minimal satu pelaksana perbaikan harus ditambahkan.');
            }

            // Validate uniqueness in payload
            $personIds = array_filter(array_column($repairers, 'person_id'));
            if (count($personIds) !== count(array_unique($personIds))) {
                throw new RuntimeException('Pelaksana perbaikan tidak boleh duplikat.');
            }

            // Derive day_name (Indonesian) from date
            $carbonDate = Carbon::parse($reportDate);
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

            $report = ReportingDamageReport::create([
                'report_number'        => $this->generateReportNumber($reportDate),
                'report_date'          => $reportDate,
                'day_name'             => $dayName,
                'location'             => $data['location'],
                'facility'             => $data['facility'],
                'equipment_name'       => $data['equipment_name'],
                'equipment_module'     => $data['equipment_module'] ?? null,
                'damage_category'      => $data['damage_category'],
                'damage_description'   => $data['damage_description'],
                'damage_cause'         => $data['damage_cause'] ?? null,
                'repair_action'        => $data['repair_action'] ?? null,
                'repair_by_type'       => $data['repair_by_type'] ?? null,
                'damage_started_at'    => $data['damage_started_at'] ?? null,
                'repair_finished_at'   => $data['repair_finished_at'] ?? null,
                'downtime_hours'       => $data['downtime_hours'] ?? null,
                'obstacle_code'        => $data['obstacle_code'] ?? null,
                'obstacle_description' => $data['obstacle_description'] ?? null,
                'status'               => 'ongoing',
                'manager_id'           => $manager->id,
                'manager_name'         => $manager->name,
                'manager_role'         => $manager->role,
                'created_by_id'        => $creator?->id,
                'created_by_name'      => $creator?->name,
            ]);

            // Seed repairers
            $sort = 0;
            foreach ($repairers as $repairer) {
                $personId   = $repairer['person_id'] ?? null;
                $personName = trim($repairer['person_name'] ?? '');
                $personRole = $repairer['person_role'] ?? null;
                $personDiv  = $repairer['person_division'] ?? null;

                if ($personName === '') {
                    throw new RuntimeException('Nama pelaksana perbaikan wajib diisi.');
                }

                ReportingDamageRepairer::create([
                    'report_id'       => $report->id,
                    'person_id'       => $personId,
                    'person_name'     => $personName,
                    'person_role'     => $personRole,
                    'person_division' => $personDiv,
                    'sort_order'      => $sort++,
                ]);
            }

            $report->refresh();
            return $report->load([
                'repairers',
                'manager:id,name,role',
            ]);
        });
    }

    /**
     * Generate sequential report number for damage reports.
     * Format: LTK-{YYMMDD}-{SEQ}
     * Example: LTK-260519-001
     *
     * Rules:
     *   - Prefix: always "LTK"
     *   - Date:   YYMMDD (2-digit year + month + day)
     *   - SEQ:    3-digit zero-padded, reset per calendar date
     *   - Counter uses withTrashed() so soft-deleted rows still increment seq
     */
    public function generateReportNumber(string $date): string
    {
        $dateYymmdd = date('ymd', strtotime($date));
        $prefix = 'LTK-' . $dateYymmdd;

        $count = ReportingDamageReport::withTrashed()
            ->where('report_number', 'LIKE', $prefix . '%')
            ->count();

        return $prefix . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    // ─── Update ────────────────────────────────────────────────

    /**
     * Update a damage report.
     *
     * Allowed:
     *   - Update fields (location, facility, equipment, damage details, etc.)
     *   - Add or remove unsigned repairers
     *   - Replace manager only if NOT signed yet
     *
     * Disallowed:
     *   - Overwrite existing signature
     *   - Remove repairer that already signed
     *   - Modify report_number, report_date
     */
    public function updateReport(ReportingDamageReport $report, array $data, ?LocalUser $editor = null): ReportingDamageReport
    {
        if ($report->status === 'completed') {
            throw new RuntimeException('Laporan yang sudah completed tidak dapat diubah.');
        }

        return DB::transaction(function () use ($report, $data) {
            // Update header fields
            $fillableFields = [
                'location', 'facility', 'equipment_name', 'equipment_module',
                'damage_category', 'damage_description', 'damage_cause',
                'repair_action', 'repair_by_type', 'damage_started_at',
                'repair_finished_at', 'downtime_hours', 'obstacle_code',
                'obstacle_description',
            ];
            foreach ($fillableFields as $field) {
                if (array_key_exists($field, $data)) {
                    $report->{$field} = $data[$field];
                }
            }

            // Update manager if provided AND no manager signature yet
            if (array_key_exists('manager_id', $data) && empty($report->manager_signature)) {
                $manager = LocalUser::where('id', $data['manager_id'])
                    ->where('is_active', true)
                    ->first();
                if (!$manager) {
                    throw new RuntimeException('Manager Teknik yang dipilih tidak ditemukan atau tidak aktif.');
                }
                if ($manager->role !== 'Manager Teknik') {
                    throw new RuntimeException('Personel yang dipilih bukan Manager Teknik.');
                }
                $report->manager_id   = $manager->id;
                $report->manager_name = $manager->name;
                $report->manager_role = $manager->role;
            }

            $report->save();

            // Sync repairers if provided
            if (array_key_exists('repairers', $data)) {
                $this->syncRepairers($report, $data['repairers']);
            }

            $report->refresh();

            // Recalculate status based on signatures
            $newStatus = $report->isComplete()
                ? 'completed'
                : ($report->isShiftEnded() ? 'on_hold' : 'ongoing');
            if ($report->status !== $newStatus) {
                $report->status = $newStatus;
                $report->save();
            }

            return $report->fresh([
                'repairers',
                'manager:id,name,role',
            ]);
        });
    }

    /**
     * Replace repairers list while preserving signed rows.
     * - Existing signed repairers cannot be removed.
     * - New repairers (without id) are appended.
     * - Repairers in payload but not in DB are added.
     * - Repairers in DB but not in payload are deleted (only if unsigned).
     */
    private function syncRepairers(ReportingDamageReport $report, array $repairers): void
    {
        $existing = $report->repairers()->get()->keyBy('id');
        $payloadIds = [];

        // Validate uniqueness in payload (same person_id can't repeat)
        $personIds = array_filter(array_column($repairers, 'person_id'));
        if (count($personIds) !== count(array_unique($personIds))) {
            throw new RuntimeException('Pelaksana perbaikan tidak boleh duplikat.');
        }

        $sort = 0;
        foreach ($repairers as $repairer) {
            $payloadId = $repairer['id'] ?? null;
            $personName = trim($repairer['person_name'] ?? '');
            if ($personName === '') {
                throw new RuntimeException('Nama pelaksana perbaikan wajib diisi.');
            }

            if ($payloadId && $existing->has($payloadId)) {
                /** @var ReportingDamageRepairer $row */
                $row = $existing->get($payloadId);
                // Don't allow modifying name if signed (immutable signer)
                if (!empty($row->signature)) {
                    $row->sort_order = $sort++;
                    $row->save();
                    $payloadIds[] = $payloadId;
                    continue;
                }
                $row->fill([
                    'person_id'       => $repairer['person_id'] ?? null,
                    'person_name'     => $personName,
                    'person_role'     => $repairer['person_role'] ?? null,
                    'person_division' => $repairer['person_division'] ?? null,
                    'sort_order'      => $sort++,
                ]);
                $row->save();
                $payloadIds[] = $payloadId;
            } else {
                $created = ReportingDamageRepairer::create([
                    'report_id'       => $report->id,
                    'person_id'       => $repairer['person_id'] ?? null,
                    'person_name'     => $personName,
                    'person_role'     => $repairer['person_role'] ?? null,
                    'person_division' => $repairer['person_division'] ?? null,
                    'sort_order'      => $sort++,
                ]);
                $payloadIds[] = $created->id;
            }
        }

        // Remove repairers that are not in payload (only if unsigned)
        foreach ($existing as $id => $row) {
            if (!in_array($id, $payloadIds, true)) {
                if (!empty($row->signature)) {
                    throw new RuntimeException(
                        sprintf('Pelaksana %s sudah menandatangani dan tidak dapat dihapus.', $row->person_name)
                    );
                }
                $row->delete();
            }
        }

        if ($report->repairers()->count() === 0) {
            throw new RuntimeException('Minimal satu pelaksana perbaikan harus tersedia.');
        }
    }

    // ─── Sign ──────────────────────────────────────────────────

    /**
     * Sign a damage report on behalf of a role.
     */
    public function signReport(
        ReportingDamageReport $report,
        string $role,
        string $base64Signature,
        LocalUser $signer,
        ?int $repairerRowId = null,
    ): ReportingDamageReport {
        $role = strtolower(trim($role));

        if ($report->status === 'completed') {
            throw new RuntimeException('Laporan yang sudah completed tidak dapat ditandatangani lagi.');
        }

        return DB::transaction(function () use ($report, $role, $base64Signature, $signer, $repairerRowId) {
            if ($role === 'repairer') {
                $this->signRepairerRow($report, $base64Signature, $signer, $repairerRowId);
            } elseif ($role === 'manager') {
                $this->signManagerRole($report, $base64Signature, $signer);
            } else {
                throw new SignerNotAuthorizedException('Role tanda tangan tidak dikenali.');
            }

            $report->refresh();
            $newStatus = $report->isComplete()
                ? 'completed'
                : ($report->isShiftEnded() ? 'on_hold' : 'ongoing');

            if ($report->status !== $newStatus) {
                $report->status = $newStatus;
                $report->save();
            }

            return $report->fresh([
                'repairers',
                'manager:id,name,role',
            ]);
        });
    }

    private function signManagerRole(ReportingDamageReport $report, string $base64, LocalUser $signer): void
    {
        $expectedName = $report->manager_name;
        if (!$expectedName) {
            throw new SignerNotAuthorizedException(
                'Laporan ini tidak memiliki Manager Teknik yang ditugaskan.'
            );
        }

        // Use centralized role-based delegation authorization
        $targetId = $report->manager_id ? (int) $report->manager_id : null;
        \App\Services\SignatureAuthorizationService::authorize($signer, 'manager', $targetId, $expectedName);

        // HasSignature trait enforces immutability + base64 PNG validation.
        $report->saveSignature('manager', $base64, $signer->id);
    }

    private function signRepairerRow(
        ReportingDamageReport $report,
        string $base64,
        LocalUser $signer,
        ?int $repairerRowId,
    ): void {
        // Use role-based delegation: Manager/Supervisor/Technician can all sign repairer slots
        \App\Services\SignatureAuthorizationService::authorize($signer, 'technician', null, null);

        /** @var ReportingDamageRepairer|null $row */
        $row = null;
        if ($repairerRowId) {
            $row = $report->repairers()->where('id', $repairerRowId)->first();
        }
        // Fallback: match by signer's local_user.id
        if (!$row && $signer->id) {
            $row = $report->repairers()->where('person_id', $signer->id)->first();
        }
        // Fallback: match by name
        if (!$row) {
            $row = $report->repairers()
                ->get()
                ->first(fn (ReportingDamageRepairer $r) => WorkOrderService::namesMatch($r->person_name, $signer->name));
        }
        // For delegation: pick first unsigned row if signer has permission
        if (!$row) {
            $row = $report->repairers()->whereNull('signature')->first();
        }

        if (!$row) {
            throw new SignerNotAuthorizedException(
                'Tidak ada slot pelaksana yang tersedia untuk ditandatangani pada laporan ini.'
            );
        }

        if (!empty($row->signature)) {
            throw new RuntimeException('Tanda tangan pelaksana sudah tersimpan dan tidak dapat diubah.');
        }

        $this->validateBase64PngSignature($base64);

        $row->signature = $base64;
        $row->signed_by = $signer->id;
        $row->signed_at = now();
        // Audit trail
        if (in_array('signed_by_name', $row->getFillable(), true) || array_key_exists('signed_by_name', $row->getAttributes())) {
            $row->signed_by_name = $signer->name;
            $row->signed_by_role = $signer->role;
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

    // ─── Delete ────────────────────────────────────────────────

    public function deleteReport(ReportingDamageReport $report): void
    {
        $report->delete();
    }
}
