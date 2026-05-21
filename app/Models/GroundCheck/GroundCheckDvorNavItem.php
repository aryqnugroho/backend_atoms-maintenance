<?php

namespace App\Models\GroundCheck;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroundCheckDvorNavItem extends Model
{
    protected $table = 'ground_check_dvor_nav_items';

    protected $fillable = [
        'ground_check_dvor_record_id',
        'section_code',
        'section_label',
        'item_code',
        'parameter_name',
        'ref_tx1_value',
        'ref_tx2_value',
        'eq_tx1_value',
        'eq_tx2_value',
        'is_section_header',
        'sort_order',
    ];

    protected $casts = [
        'is_section_header' => 'boolean',
        'sort_order'        => 'integer',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(GroundCheckDvorRecord::class, 'ground_check_dvor_record_id');
    }
}
