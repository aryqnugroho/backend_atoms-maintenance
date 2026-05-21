<?php

namespace App\Models\GroundCheck;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroundCheckGpNavItem extends Model
{
    protected $table = 'ground_check_gp_nav_items';

    protected $fillable = [
        'ground_check_gp_record_id',
        'section_code',
        'section_label',
        'section_keterangan',
        'item_code',
        'parameter_name',
        'tx1_value',
        'tx2_value',
        'keterangan',
        'is_section_header',
        'sort_order',
    ];

    protected $casts = [
        'is_section_header' => 'boolean',
        'sort_order'        => 'integer',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(GroundCheckGpRecord::class, 'ground_check_gp_record_id');
    }
}
