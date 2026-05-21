<?php

namespace App\Exceptions;

use App\Models\Cnsd\CnsdTdmeMeterRecord;
use RuntimeException;

class CnsdTdmeMeterDuplicateException extends RuntimeException
{
    public function __construct(public readonly CnsdTdmeMeterRecord $existingRecord)
    {
        parent::__construct(
            'Form T-DME Meter Reading untuk tanggal '
            . $existingRecord->date?->format('Y-m-d')
            . ' shift ' . $existingRecord->shift_type
            . ' sudah ada (Form #' . $existingRecord->form_number . '). '
            . 'Hapus form yang ada terlebih dahulu jika ingin membuat ulang untuk shift yang sama.'
        );
    }
}
