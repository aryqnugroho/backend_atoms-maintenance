<?php

namespace App\Models\GroundCheck;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroundCheckDvorBearingPoint extends Model
{
    protected $table = 'ground_check_dvor_bearing_points';

    protected $fillable = [
        'ground_check_dvor_record_id',
        'bearing',
        'tx1_reading',
        'tx1_error',
        'tx1_value',
        'tx2_reading',
        'tx2_error',
        'tx2_value',
        'sort_order',
    ];

    protected $casts = [
        'bearing'     => 'integer',
        'tx1_reading' => 'decimal:4',
        'tx1_error'   => 'decimal:4',
        'tx2_reading' => 'decimal:4',
        'tx2_error'   => 'decimal:4',
        'sort_order'  => 'integer',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(GroundCheckDvorRecord::class, 'ground_check_dvor_record_id');
    }
}
