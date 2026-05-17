<?php

namespace App\Models\Cnsd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CnsdReadinessItem — one row per equipment line on the form.
 *
 * Generated automatically from the EQ-1 template at create time. Users update
 * status_peralatan, kondisi_operasional_1, kondisi_operasional_2, and
 * keterangan during the shift.
 */
class CnsdReadinessItem extends Model
{
    protected $table = 'cnsd_readiness_items';

    protected $fillable = [
        'readiness_record_id',
        'section_name',
        'item_number',
        'equipment_name',
        'sub_equipment_name',
        'status_peralatan',
        'kondisi_operasional_1',
        'kondisi_operasional_2',
        'keterangan',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(CnsdReadinessRecord::class, 'readiness_record_id');
    }
}
