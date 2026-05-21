<?php

namespace App\Models\Reporting;

use App\Models\LocalUser;
use App\Traits\HasSignature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ReportingDamageReport — header for one Laporan Kerusakan (Damage Report).
 *
 * Reporting differs from Work Order, CNSD, TFP, Grounding — it does NOT
 * use the rostering shift personnel automatically. Manager Teknik dan
 * Pelaksana Perbaikan dipilih manual oleh user dari personel database.
 *
 * Signature contract:
 *   - Manager Teknik signature lives on this model (one column set).
 *   - Repairer signatures live on reporting_damage_repairers (per-row).
 *   - HasSignature trait handles only manager here.
 *   - Repairer signing is handled by the service directly.
 */
class ReportingDamageReport extends Model
{
    use HasSignature, SoftDeletes;

    protected $table = 'reporting_damage_reports';

    protected $fillable = [
        'report_number',
        'report_date',
        'day_name',
        'location',
        'facility',
        'equipment_name',
        'equipment_module',
        'damage_category',
        'damage_description',
        'damage_cause',
        'repair_action',
        'repair_by_type',
        'damage_started_at',
        'repair_finished_at',
        'downtime_hours',
        'obstacle_code',
        'obstacle_description',
        'status',
        'manager_id',
        'manager_name',
        'manager_role',
        'manager_signature',
        'manager_signed_by',
        'manager_signed_at',
        'manager_signed_by_name',
        'manager_signed_by_role',
        'created_by_id',
        'created_by_name',
    ];

    protected $casts = [
        'report_date'        => 'date:Y-m-d',
        'damage_started_at'  => 'datetime',
        'repair_finished_at' => 'datetime',
        'manager_signed_at'  => 'datetime',
        'downtime_hours'     => 'decimal:2',
    ];

    public const STATUSES         = ['ongoing', 'on_hold', 'completed'];
    public const DAMAGE_CATEGORIES = ['1', '2', '3'];
    public const REPAIR_BY_TYPES  = ['lokasi', 'pusat'];

    public const OBSTACLE_CODES = [
        'AU' => 'Tidak ada alat ukur',
        'PK' => 'Menunggu Penerbangan Kalibrasi',
        'TT' => 'Tidak ada teknisi',
        'SC' => 'Menunggu Suku Cadang',
        'TR' => 'Tidak Ada Transportasi',
        'ST' => 'Peralatan Belum Ada Serah Terima',
        'PC' => 'Pengaruh Cuaca',
        'AL' => 'Alasan Lain',
        'TH' => 'Tidak ada hambatan',
    ];

    // ─── Relationships ─────────────────────────────────────────

    public function manager(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'manager_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'created_by_id');
    }

    public function repairers(): HasMany
    {
        return $this->hasMany(ReportingDamageRepairer::class, 'report_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    // ─── HasSignature integration ──────────────────────────────

    public function signatureRoleMap(): array
    {
        return [
            'manager' => [
                'name'      => 'manager_name',
                'signature' => 'manager_signature',
                'signed_at' => 'manager_signed_at',
                'signed_by' => 'manager_signed_by',
            ],
        ];
    }

    public function requiredSignatureRoles(): array
    {
        $roles = [];
        if (!empty($this->manager_name)) {
            $roles[] = 'manager';
        }
        return $roles;
    }

    /**
     * Override isComplete() to also require every repairer row to be signed.
     */
    public function isComplete(): bool
    {
        if (!empty($this->getPendingSignatures())) {
            return false;
        }

        $unsignedRepairer = $this->repairers()
            ->whereNull('signature')
            ->exists();

        return !$unsignedRepairer;
    }

    /**
     * Reporting tidak terikat shift, sehingga "shift ended" berarti
     * tanggal laporan sudah lewat dari hari ini.
     */
    public function isShiftEnded(): bool
    {
        if (!$this->report_date) {
            return false;
        }
        return now()->startOfDay()->greaterThan($this->report_date->copy()->endOfDay());
    }

    // ─── Scopes ────────────────────────────────────────────────

    public function scopeByDate($q, string $date)
    {
        return $q->whereDate('report_date', $date);
    }
}
