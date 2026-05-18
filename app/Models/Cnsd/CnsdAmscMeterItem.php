<?php

namespace App\Models\Cnsd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CnsdAmscMeterItem — item row for CNSD AMSC Meter Reading forms.
 *
 * Columns accommodate all 4 sections:
 *   - FRONT PANEL: hasil_a + hasil_b + keterangan
 *   - POWER SUPPLY UNIT: hasil + keterangan
 *   - CHANNEL AMSC: address + status_value + cct + keterangan
 *   - LINGKUNGAN KERJA: hasil + keterangan
 */
class CnsdAmscMeterItem extends Model
{
    protected $table = 'cnsd_amsc_meter_items';

    protected $fillable = [
        'amsc_meter_record_id',
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
        'address',
        'status_value',
        'cct',
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
        return $this->belongsTo(CnsdAmscMeterRecord::class, 'amsc_meter_record_id');
    }
}
