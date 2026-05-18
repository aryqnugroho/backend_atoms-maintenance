<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TfpDvorItem extends Model
{
    protected $table = 'tfp_dvor_items';
    protected $fillable = [
        'dvor_record_id', 'parameter_number', 'parameter_name', 'unit',
        'panel_d01', 'panel_d03', 'panel_d04', 'panel_d05',
        'panel_ats_d06_input', 'panel_ats_d06_output',
        'is_disabled_map', 'sort_order',
    ];
    protected $casts = ['is_disabled_map' => 'array', 'sort_order' => 'integer'];
    public function record(): BelongsTo { return $this->belongsTo(TfpDvorRecord::class, 'dvor_record_id'); }
}
