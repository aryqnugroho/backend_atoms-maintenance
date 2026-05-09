<?php

namespace App\Models\WorkOrder;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderOutput extends Model
{
    protected $table = 'work_order_outputs';

    public $timestamps = false;

    protected $fillable = [
        'work_order_id',
        'output_type',
        'output_other',
    ];

    /**
     * Valid output types (aligned with frontend OutputType).
     */
    public const OUTPUT_TYPES = ['meter_reading', 'status_peralatan', 'logbook', 'other'];

    // ─── Relationships ─────────────────────────────────────────

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }
}
