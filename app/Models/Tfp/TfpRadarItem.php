<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TfpRadarItem extends Model
{
    protected $table = 'tfp_radar_items';

    protected $fillable = [
        'radar_record_id', 'parameter_number', 'parameter_name', 'unit',
        'panel_rd01', 'panel_rd02',
        'panel_cos_rd03_input', 'panel_cos_rd03_output',
        'ups_topaz_input', 'ups_topaz_output',
        'panel_rd04', 'panel_rd05', 'panel_rd06', 'panel_rd07', 'panel_rd08',
        'is_disabled_map', 'sort_order',
    ];

    protected $casts = ['is_disabled_map' => 'array', 'sort_order' => 'integer'];

    public function record(): BelongsTo { return $this->belongsTo(TfpRadarRecord::class, 'radar_record_id'); }
}
