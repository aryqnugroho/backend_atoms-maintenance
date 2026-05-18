<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TfpGlidepathItem extends Model
{
    protected $table = 'tfp_glidepath_items';
    protected $fillable = ['glidepath_record_id', 'parameter_number', 'parameter_name', 'unit', 'panel_gp01', 'is_disabled_map', 'sort_order'];
    protected $casts = ['is_disabled_map' => 'array', 'sort_order' => 'integer'];
    public function record(): BelongsTo { return $this->belongsTo(TfpGlidepathRecord::class, 'glidepath_record_id'); }
}
