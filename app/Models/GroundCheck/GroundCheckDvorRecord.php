<?php

namespace App\Models\GroundCheck;

use App\Models\LocalUser;
use App\Traits\HasSignature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroundCheckDvorRecord extends Model
{
    use HasSignature, SoftDeletes;

    protected $table = 'ground_check_dvor_records';

    protected $fillable = [
        'form_number',
        'form_type',
        'report_month',
        'airport',
        'equipment_name',
        'equipment_location',
        'equipment_function',
        'technical_data',
        'identification',
        'last_calibration',
        'vor_equipment_name',
        'vor_frequency',
        'vor_station',
        'curve_organization',
        'nav_analyzer_title',
        'note',
        'date',
        'time_filled',
        'day_name',
        'shift_type',
        'status',
        'manager_id',
        'manager_name',
        'manager_signature',
        'manager_signed_by',
        'manager_signed_at',
        'supervisor_id',
        'supervisor_name',
        'supervisor_signature',
        'supervisor_signed_by',
        'supervisor_signed_at',
        'created_by_id',
        'created_by_name',
    ];

    protected $casts = [
        'date' => 'date',
        'manager_signed_at' => 'datetime',
        'supervisor_signed_at' => 'datetime',
    ];

    /**
     * Override HasSignature default map — Ground Check uses `manager_*`
     * columns, not `mt_*`. See GroundCheckAdcRecord for the full rationale.
     */
    public function signatureRoleMap(): array
    {
        return [
            'manager' => [
                'name'      => 'manager_name',
                'signature' => 'manager_signature',
                'signed_at' => 'manager_signed_at',
                'signed_by' => 'manager_signed_by',
            ],
            'supervisor' => [
                'name'      => 'supervisor_name',
                'signature' => 'supervisor_signature',
                'signed_at' => 'supervisor_signed_at',
                'signed_by' => 'supervisor_signed_by',
            ],
        ];
    }

    public function technicians(): HasMany
    {
        return $this->hasMany(GroundCheckDvorTechnician::class, 'ground_check_dvor_record_id')
            ->orderBy('sort_order');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GroundCheckDvorItem::class, 'ground_check_dvor_record_id')
            ->orderBy('sort_order');
    }

    public function bearingPoints(): HasMany
    {
        return $this->hasMany(GroundCheckDvorBearingPoint::class, 'ground_check_dvor_record_id')
            ->orderBy('sort_order');
    }

    public function navItems(): HasMany
    {
        return $this->hasMany(GroundCheckDvorNavItem::class, 'ground_check_dvor_record_id')
            ->orderBy('sort_order');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(GroundCheckDvorPhoto::class, 'ground_check_dvor_record_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'manager_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'supervisor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'created_by_id');
    }

    public function scopeByFormType($query, string $formType)
    {
        return $query->where('form_type', $formType);
    }

    public function scopeByDate($query, string $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeByShift($query, string $shiftType)
    {
        return $query->where('shift_type', $shiftType);
    }

    public function isComplete(): bool
    {
        if ($this->manager_name && empty($this->manager_signature)) {
            return false;
        }

        if ($this->supervisor_name && empty($this->supervisor_signature)) {
            return false;
        }

        foreach ($this->technicians as $tech) {
            if (empty($tech->technician_signature)) {
                return false;
            }
        }

        return true;
    }

    public function isShiftEnded(): bool
    {
        $now = now();
        $recordDate = $this->date->format('Y-m-d');
        $today = $now->format('Y-m-d');

        if ($recordDate < $today) {
            return true;
        }

        if ($recordDate === $today) {
            $hour = (int) $now->format('H');
            return match ($this->shift_type) {
                'pagi'  => $hour >= 13,
                'siang' => $hour >= 19,
                'malam' => $hour >= 7 && $hour < 19,
                default => false,
            };
        }

        return false;
    }
}
