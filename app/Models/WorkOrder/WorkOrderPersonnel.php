<?php

namespace App\Models\WorkOrder;

use App\Models\LocalUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderPersonnel extends Model
{
    protected $table = 'work_order_personnel';

    protected $fillable = [
        'work_order_id',
        'user_id',
        'role_label',
    ];

    // ─── Relationships ─────────────────────────────────────────

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'user_id');
    }
}
