<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TfpAobGroundItem — one measurement parameter row for a TFP AOB Ground record.
 *
 * Columns map to the physical form columns:
 *   - panel_cos_a03_input / panel_cos_a03_output  → Panel COS A03
 *   - panel_ats_a12_input / panel_ats_a12_output  → Panel ATS A12
 *   - ups_tescom_a_input  / ups_tescom_a_output   → UPS TESCOM A
 *   - ups_tescom_b_input  / ups_tescom_b_output   → UPS TESCOM B
 *
 * is_disabled_map marks which columns are greyed-out for this parameter.
 */
class TfpAobGroundItem extends Model
{
    protected $table = 'tfp_aob_ground_items';

    protected $fillable = [
        'aob_ground_record_id',
        'parameter_number',
        'parameter_name',
        'unit',
        'panel_cos_a03_input',
        'panel_cos_a03_output',
        'panel_ats_a12_input',
        'panel_ats_a12_output',
        'ups_tescom_a_input',
        'ups_tescom_a_output',
        'ups_tescom_b_input',
        'ups_tescom_b_output',
        'is_disabled_map',
        'sort_order',
    ];

    protected $casts = [
        'is_disabled_map' => 'array',
        'sort_order'      => 'integer',
    ];

    // ─── Relationships ─────────────────────────────────────────

    public function record(): BelongsTo
    {
        return $this->belongsTo(TfpAobGroundRecord::class, 'aob_ground_record_id');
    }
}
