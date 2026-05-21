<?php

namespace App\Exceptions;

use App\Models\Cnsd\CnsdDmeMeterRecord;
use RuntimeException;

class CnsdDmeMeterDuplicateException extends RuntimeException
{
    public function __construct(public readonly CnsdDmeMeterRecord $existingRecord)
    {
        parent::__construct(
            'Form DME Meter Reading untuk tanggal '
            . $existingRecord->date?->format('Y-m-d')
            . ' shift ' . $existingRecord->shift_type
            . ' sudah ada (Form #' . $existingRecord->form_number . '). '
            . 'Hapus form yang ada terlebih dahulu jika ingin membuat ulang untuk shift yang sama.'
        );
    }
}
