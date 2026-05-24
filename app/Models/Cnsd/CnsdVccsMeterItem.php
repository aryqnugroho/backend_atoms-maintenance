<?php

namespace App\Models\Cnsd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CnsdVccsMeterItem — item row for CNSD VCCS Meter Reading forms.
 *
 * Columns accommodate all 4 sections:
 *   - FRONT PANEL (dual_adaptive): hasil_a + hasil_b adapt to per-row nominal
 *   - MSC & RCMS / CWP (dual_toggle_nf): hasil_a = Normal toggle, hasil_b = Fault toggle
 *   - LINGKUNGAN KERJA (environment): hasil = single result
 */
class CnsdVccsMeterItem extends Model
{
    protected $table = 'cnsd_vccs_meter_items';

    protected $fillable = [
        'vccs_meter_record_id',
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
        return $this->belongsTo(CnsdVccsMeterRecord::class, 'vccs_meter_record_id');
    }
}
