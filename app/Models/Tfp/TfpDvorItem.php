<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TfpDvorItem extends Model
{
    protected $table = 'tfp_dvor_items';

    protected $fillable = [
        'dvor_record_id', 'parameter_number', 'parameter_name', 'unit',
        'values', 'is_disabled_map', 'merge_map', 'sort_order',
    ];

    protected $casts = [
        'values'          => 'array',
        'is_disabled_map' => 'array',
        'merge_map'       => 'array',
        'sort_order'      => 'integer',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(TfpDvorRecord::class, 'dvor_record_id');
    }
}
