<?php

namespace App\Models\Cnsd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CnsdTransmitterMeterItem — item row for Transmitter Meter Reading.
 *
 * Covers both Section 1 (TRANSMITTER / TX RADIO) and Section 2 (LINGKUNGAN KERJA).
 */
class CnsdTransmitterMeterItem extends Model
{
    protected $table = 'cnsd_transmitter_meter_items';

    protected $fillable = [
        'transmitter_meter_record_id',
        'section_code',
        'section_name',
        'group_number',
        'group_name',
        'frequency_label',
        'merk',
        'tx_label',
        'status_value',
        'power_output',
        'modulasi',
        'keterangan',
        'nominal',
        'hasil',
        'is_header',
        'is_blocked',
        'block_reason',
        'sort_order',
    ];

    protected $casts = [
        'is_header'  => 'boolean',
        'is_blocked' => 'boolean',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(CnsdTransmitterMeterRecord::class, 'transmitter_meter_record_id');
    }
}
