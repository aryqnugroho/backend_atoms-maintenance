<?php

namespace App\Models\Cnsd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CnsdAtcSystemMeterItem extends Model
{
    protected $table = 'cnsd_atc_system_meter_items';

    protected $fillable = [
        'atc_system_meter_record_id', 'section_code', 'section_name',
        'group_number', 'group_name', 'item_name', 'sub_item_label', 'nominal',
        'value_1', 'value_2', 'value_3', 'value_4', 'status_flags', 'keterangan',
        'is_header', 'sort_order',
    ];

    protected $casts = ['is_header' => 'boolean'];

    public function record(): BelongsTo
    {
        return $this->belongsTo(CnsdAtcSystemMeterRecord::class, 'atc_system_meter_record_id');
    }
}
