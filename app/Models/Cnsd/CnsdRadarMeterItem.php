<?php

namespace App\Models\Cnsd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CnsdRadarMeterItem — one row per equipment line on the Radar Meter Reading form.
 *
 * Generated automatically from CnsdRadarMeterTemplate at create time. Users
 * update kondisi_teknis_tx1, kondisi_teknis_tx2, hasil (for environment), and
 * keterangan during the shift.
 */
class CnsdRadarMeterItem extends Model
{
    protected $table = 'cnsd_radar_meter_items';

    protected $fillable = [
        'radar_meter_record_id',
        'section_code',
        'section_name',
        'group_number',
        'group_name',
        'item_number',
        'item_name',
        'standard',
        'kondisi_teknis_tx1',
        'kondisi_teknis_tx2',
        'hasil',
        'keterangan',
        'sort_order',
    ];

    protected $casts = [
        'sort_order'   => 'integer',
        'group_number' => 'integer',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(CnsdRadarMeterRecord::class, 'radar_meter_record_id');
    }
}
