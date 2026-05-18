<?php

namespace App\Models\Grounding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GroundingReportItem — one row per checklist item in a Grounding Report.
 *
 * Items belong to one of two sections:
 *   - VISUAL:      availability (Ada | Tidak Ada) + condition + notes
 *   - PENGUKURAN:  condition + notes (availability is always null)
 */
class GroundingReportItem extends Model
{
    protected $table = 'grounding_report_items';

    protected $fillable = [
        'grounding_report_record_id',
        'section_name',
        'item_number',
        'item_name',
        'standard',
        'availability',
        'condition',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'item_number' => 'integer',
        'sort_order'  => 'integer',
    ];

    // ─── Relationships ─────────────────────────────────────────

    public function record(): BelongsTo
    {
        return $this->belongsTo(GroundingReportRecord::class, 'grounding_report_record_id');
    }
}
