<?php

namespace App\Models\WorkOrder;

use App\Models\LocalUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    use SoftDeletes;

    protected $table = 'work_orders';

    protected $fillable = [
        'wo_number',
        'wo_type',
        'division',
        'shift_type',
        'shift_date',
        'description',
        'status',
        'manager_id',
        'supervisor_id',
        'assigned_technician_id',
        'manager_name_snapshot',
        'supervisor_name_snapshot',
        'start_time',
        'end_time',
        'completion_status',
        'notes_kendala',
        'notes_usulan',
        'notes_pemberi_tugas',
        'created_by',
        'closed_at',
    ];

    protected $casts = [
        'shift_date' => 'date:Y-m-d',
        'closed_at' => 'datetime',
    ];

    /**
     * Valid work order statuses (aligned with frontend).
     */
    public const STATUSES = ['completed', 'on_hold', 'ongoing'];

    /**
     * Valid work order types.
     */
    public const TYPES = ['shift', 'personal'];

    /**
     * Valid divisions.
     */
    public const DIVISIONS = ['CNSD', 'TFP'];

    /**
     * Valid shift types.
     */
    public const SHIFT_TYPES = ['pagi', 'siang', 'malam'];

    /**
     * Valid completion statuses.
     */
    public const COMPLETION_STATUSES = ['selesai', 'belum_selesai_dilanjut', 'tidak_bisa'];

    // ─── Relationships ─────────────────────────────────────────

    public function manager(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'manager_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'supervisor_id');
    }

    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'assigned_technician_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'created_by');
    }

    public function personnel(): HasMany
    {
        return $this->hasMany(WorkOrderPersonnel::class, 'work_order_id');
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(WorkOrderOutput::class, 'work_order_id');
    }

    // ─── Scopes ────────────────────────────────────────────────

    public function scopeByDivision($query, string $division)
    {
        return $query->where('division', $division);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByShiftDate($query, string $date)
    {
        return $query->where('shift_date', $date);
    }

    public function scopeByShiftType($query, string $shiftType)
    {
        return $query->where('shift_type', $shiftType);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('wo_type', $type);
    }

    /**
     * Scope to filter work orders visible to a given Teknisi.
     * Teknisi can only see WOs they are assigned to.
     */
    public function scopeVisibleToTeknisi($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('assigned_technician_id', $userId)
              ->orWhereHas('personnel', function ($sub) use ($userId) {
                  $sub->where('user_id', $userId);
              });
        });
    }
}
