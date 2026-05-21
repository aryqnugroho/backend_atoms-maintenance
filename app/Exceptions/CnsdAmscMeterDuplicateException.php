<?php

namespace App\Exceptions;

use App\Models\Cnsd\CnsdAmscMeterRecord;
use RuntimeException;

class CnsdAmscMeterDuplicateException extends RuntimeException
{
    public CnsdAmscMeterRecord $existingRecord;

    public function __construct(CnsdAmscMeterRecord $existing)
    {
        $this->existingRecord = $existing;
        parent::__construct(
            'Form AMSC Meter Reading untuk tanggal '
            . $existing->date->format('Y-m-d')
            . ' shift ' . $existing->shift_type
            . ' sudah ada (Form #' . $existing->form_number . '). '
            . 'Hapus form yang ada terlebih dahulu jika ingin membuat ulang untuk shift yang sama.'
        );
    }
}
