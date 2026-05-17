<?php

namespace App\Models\Cnsd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CnsdRecorderMeterItem — one row per equipment line on the Recorder Meter
 * Reading form.
 *
 * Generated automatically from CnsdRecorderMeterTemplate at create time.
 * Users update hasil_server_a, hasil_server_b (for section A: Peralatan), or
 * hasil (for section B: Lingkungan Kerja), and keterangan during the shift.
 *
 * Items with `is_blocked = true` represent U/S (Un-Serviceable) channels:
 *   - Frontend renders them as a red strip with "U/S" label.
 *   - All inputs are disabled.
 *   - Backend rejects edits to these rows during update.
 */
class CnsdRecorderMeterItem extends Model
{
    protected $table = 'cnsd_recorder_meter_items';

    protected $fillable = [
        'recorder_meter_record_id',
        'section_code',
        'section_name',
        'group_number',
        'group_name',
        'item_number',
        'item_name',
        'nominal',
        'hasil_server_a',
        'hasil_server_b',
        'hasil',
        'keterangan',
        'is_blocked',
        'block_reason',
        'sort_order',
    ];

    protected $casts = [
        'sort_order'   => 'integer',
        'group_number' => 'integer',
        'is_blocked'   => 'boolean',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(CnsdRecorderMeterRecord::class, 'recorder_meter_record_id');
    }
}
