<?php

namespace App\Models\GroundCheck;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroundCheckLlzCurvePoint extends Model
{
    protected $table = 'ground_check_llz_curve_points';

    protected $fillable = [
        'ground_check_llz_record_id',
        'side',
        'jarak_m',
        'degrees',
        'tx1_ddm_pct',
        'tx1_ddm_ua',
        'tx1_sum_pct',
        'tx1_mod_90hz',
        'tx1_mod_150hz',
        'tx1_rf_level_db',
        'tx2_ddm_pct',
        'tx2_ddm_ua',
        'tx2_sum_pct',
        'tx2_mod_90hz',
        'tx2_mod_150hz',
        'tx2_rf_level_db',
        'sort_order',
    ];

    protected $casts = [
        'jarak_m'         => 'decimal:2',
        'degrees'         => 'decimal:2',
        'tx1_ddm_pct'     => 'decimal:4',
        'tx1_ddm_ua'      => 'decimal:4',
        'tx1_sum_pct'     => 'decimal:4',
        'tx1_mod_90hz'    => 'decimal:4',
        'tx1_mod_150hz'   => 'decimal:4',
        'tx1_rf_level_db' => 'decimal:4',
        'tx2_ddm_pct'     => 'decimal:4',
        'tx2_ddm_ua'      => 'decimal:4',
        'tx2_sum_pct'     => 'decimal:4',
        'tx2_mod_90hz'    => 'decimal:4',
        'tx2_mod_150hz'   => 'decimal:4',
        'tx2_rf_level_db' => 'decimal:4',
        'sort_order'      => 'integer',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(GroundCheckLlzRecord::class, 'ground_check_llz_record_id');
    }
}
