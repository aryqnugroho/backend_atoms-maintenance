<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TfpTowerItem extends Model
{
    protected $table = 'tfp_tower_items';

    protected $fillable = [
        'tower_record_id', 'parameter_number', 'parameter_name', 'unit',
        'panel_a10', 'panel_a11',
        'panel_ats_a13_input', 'panel_ats_a13_output',
        'panel_a14', 'panel_a16', 'panel_a17', 'panel_a18', 'panel_a19', 'panel_a20',
        'panel_milat_ru1213',
        'is_disabled_map', 'sort_order',
    ];

    protected $casts = ['is_disabled_map' => 'array', 'sort_order' => 'integer'];

    public function record(): BelongsTo { return $this->belongsTo(TfpTowerRecord::class, 'tower_record_id'); }
}
