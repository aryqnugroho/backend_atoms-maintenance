<?php

namespace App\Models\Cnsd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CnsdVccsFreqMeterItem extends Model
{
    protected $table = 'cnsd_vccs_freq_meter_items';

    protected $fillable = [
        'vccs_freq_meter_record_id',
        'section_code',
        'section_name',
        'group_number',
        'group_name',
        'item_number',
        'item_name',
        'nominal',
        'hasil_a',
        'hasil_b',
        'hasil',
        'keterangan',
        'is_blocked',
        'block_reason',
        'sort_order',
    ];

    protected $casts = [
        'is_blocked' => 'boolean',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(CnsdVccsFreqMeterRecord::class, 'vccs_freq_meter_record_id');
    }
}
