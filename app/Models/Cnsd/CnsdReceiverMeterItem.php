<?php

namespace App\Models\Cnsd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CnsdReceiverMeterItem extends Model
{
    protected $table = 'cnsd_receiver_meter_items';

    protected $fillable = [
        'receiver_meter_record_id',
        'section_code',
        'section_name',
        'group_number',
        'group_name',
        'item_name',
        'status_a',
        'status_b',
        'sequelsh_on',
        'keterangan',
        'nominal',
        'hasil',
        'is_header',
        'sort_order',
    ];

    protected $casts = [
        'is_header' => 'boolean',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(CnsdReceiverMeterRecord::class, 'receiver_meter_record_id');
    }
}
