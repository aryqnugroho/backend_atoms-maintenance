<?php

namespace App\Models\Cnsd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CnsdGlidepathMeterItem extends Model
{
    protected $table = 'cnsd_glidepath_meter_items';

    protected $fillable = [
        'glidepath_meter_record_id',
        'section_code',
        'section_name',
        'group_number',
        'group_name',
        'item_name',
        'nominal',
        'hasil_layout',
        'hasil_1',
        'hasil_2',
        'keterangan',
        'is_header',
        'sort_order',
    ];

    protected $casts = [
        'is_header' => 'boolean',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(CnsdGlidepathMeterRecord::class, 'glidepath_meter_record_id');
    }
}
