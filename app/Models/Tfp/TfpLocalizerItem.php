<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TfpLocalizerItem extends Model
{
    protected $table = 'tfp_localizer_items';
    protected $fillable = [
        'localizer_record_id', 'parameter_number', 'parameter_name', 'unit',
        'panel_lz01',
        'panel_cos_lz02_input', 'panel_cos_lz02_output',
        'panel_lz03',
        'panel_cos_lz04_input', 'panel_cos_lz04_output',
        'panel_mlat_ru04',
        'is_disabled_map', 'sort_order',
    ];
    protected $casts = ['is_disabled_map' => 'array', 'sort_order' => 'integer'];
    public function record(): BelongsTo { return $this->belongsTo(TfpLocalizerRecord::class, 'localizer_record_id'); }
}
