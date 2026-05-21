<?php

namespace App\Models\Cnsd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CnsdAtisMeterItem extends Model
{
    protected $table = 'cnsd_atis_meter_items';

    protected $fillable = [
        'atis_meter_record_id', 'section_code', 'section_name',
        'group_number', 'group_name', 'item_name', 'nominal',
        'reading', 'keterangan', 'is_header', 'sort_order',
    ];

    protected $casts = ['is_header' => 'boolean'];

    public function record(): BelongsTo
    {
        return $this->belongsTo(CnsdAtisMeterRecord::class, 'atis_meter_record_id');
    }
}
