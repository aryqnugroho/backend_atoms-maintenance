<?php

namespace App\Exceptions;

use App\Models\Cnsd\CnsdVccsMeterRecord;
use RuntimeException;

class CnsdVccsMeterDuplicateException extends RuntimeException
{
    public CnsdVccsMeterRecord $existingRecord;

    public function __construct(CnsdVccsMeterRecord $existing)
    {
        $this->existingRecord = $existing;
        parent::__construct(
            'Form VCCS Meter Reading untuk tanggal '
            . $existing->date->format('Y-m-d')
            . ' shift ' . $existing->shift_type
            . ' sudah ada (Form #' . $existing->form_number . '). '
            . 'Hapus form yang ada terlebih dahulu jika ingin membuat ulang untuk shift yang sama.'
        );
    }
}
