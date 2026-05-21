<?php

namespace App\Exceptions;

use App\Models\Cnsd\CnsdRadarMeterRecord;
use RuntimeException;

/**
 * Thrown when a CNSD Radar Meter record already exists for the same
 * (form_type, facility, date, shift_type) tuple.
 *
 * The existing record is attached so the controller can return it inside the
 * 409 response body (frontend uses it to navigate to the existing detail page
 * instead of forcing the user to search for it again).
 */
class CnsdRadarMeterDuplicateException extends RuntimeException
{
    public function __construct(
        public readonly CnsdRadarMeterRecord $existingRecord,
        string $message = '',
    ) {
        if ($message === '') {
            $message = sprintf(
                'Form Radar Meter Reading untuk tanggal %s shift %s sudah ada (Form #%s). '
                . 'Hapus form yang ada terlebih dahulu jika ingin membuat ulang untuk shift yang sama.',
                $existingRecord->date?->format('Y-m-d') ?? '',
                $existingRecord->shift_type,
                $existingRecord->form_number,
            );
        }
        parent::__construct($message);
    }
}
