<?php

namespace App\Models\Cnsd;

use App\Traits\HasSignature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\LocalUser;

class CnsdTdmeMeterRecord extends Model
{
    use SoftDeletes, HasSignature;

    protected $table = 'cnsd_tdme_meter_records';

    protected $fillable = [
        'form_number', 'form_type', 'facility', 'form_code',
        'merk', 'type', 'serial_number', 'tx1_mode', 'tx2_mode',
        'date', 'shift_type', 'day_name', 'time_filled', 'location',
        'status',
        'manager_id', 'manager_name', 'manager_signature', 'manager_signed_by', 'manager_signed_at',
        'supervisor_id', 'supervisor_name', 'supervisor_signature', 'supervisor_signed_by', 'supervisor_signed_at',
        'created_by_id', 'created_by_name',
    ];

    protected $casts = [
        'date'                 => 'date',
        'manager_signed_at'    => 'datetime',
        'supervisor_signed_at' => 'datetime',
    ];

    public function technicians(): HasMany
    {
        return $this->hasMany(CnsdTdmeMeterTechnician::class, 'tdme_meter_record_id')->orderBy('sort_order');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CnsdTdmeMeterItem::class, 'tdme_meter_record_id')->orderBy('sort_order');
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

    public function requiredSignatureRoles(): array
    {
        $roles = [];
        if ($this->manager_name) $roles[] = 'manager';
        if ($this->supervisor_name) $roles[] = 'supervisor';
        return $roles;
    }

    public function isComplete(): bool
    {
        if ($this->manager_name && empty($this->manager_signature)) return false;
        if ($this->supervisor_name && empty($this->supervisor_signature)) return false;
        foreach ($this->technicians as $tech) {
            if (empty($tech->technician_signature)) return false;
        }
        return true;
    }

    public function isShiftEnded(): bool
    {
        if (!$this->date || !$this->shift_type) return false;
        $shiftEndHours = ['pagi' => 13, 'siang' => 19, 'malam' => 7];
        $endHour = $shiftEndHours[$this->shift_type] ?? 13;
        $shiftDate = $this->date->format('Y-m-d');
        if ($this->shift_type === 'malam') $shiftDate = $this->date->addDay()->format('Y-m-d');
        return now()->gt(\Carbon\Carbon::parse($shiftDate . ' ' . str_pad($endHour, 2, '0', STR_PAD_LEFT) . ':00:00'));
    }
}
