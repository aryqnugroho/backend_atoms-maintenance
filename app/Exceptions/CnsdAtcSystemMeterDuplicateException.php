<?php

namespace App\Exceptions;

use App\Models\Cnsd\CnsdAtcSystemMeterRecord;
use RuntimeException;

class CnsdAtcSystemMeterDuplicateException extends RuntimeException
{
    public function __construct(public readonly CnsdAtcSystemMeterRecord $existingRecord)
    {
        parent::__construct(
            'Form ATC SYSTEM Meter Reading untuk tanggal '
            . $existingRecord->date?->format('Y-m-d')
            . ' shift ' . $existingRecord->shift_type
            . ' sudah ada (Form #' . $existingRecord->form_number . '). '
            . 'Hapus form yang ada terlebih dahulu jika ingin membuat ulang untuk shift yang sama.'
        );
    }
}
