<?php

namespace App\Models\Cnsd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CnsdDvorMeterItem extends Model
{
    protected $table = 'cnsd_dvor_meter_items';

    protected $fillable = [
        'dvor_meter_record_id', 'section_code', 'section_name',
        'group_code', 'group_name', 'item_name', 'limit_value',
        'hasil_pemeriksaan', 'keterangan', 'is_header', 'sort_order',
    ];

    protected $casts = ['is_header' => 'boolean'];

    public function record(): BelongsTo
    {
        return $this->belongsTo(CnsdDvorMeterRecord::class, 'dvor_meter_record_id');
    }
}
