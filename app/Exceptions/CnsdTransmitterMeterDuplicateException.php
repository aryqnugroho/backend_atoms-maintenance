<?php

namespace App\Exceptions;

use App\Models\Cnsd\CnsdTransmitterMeterRecord;
use RuntimeException;

class CnsdTransmitterMeterDuplicateException extends RuntimeException
{
    public CnsdTransmitterMeterRecord $existingRecord;

    public function __construct(CnsdTransmitterMeterRecord $existing)
    {
        $this->existingRecord = $existing;
        parent::__construct(
            'Form Transmitter Meter Reading untuk tanggal '
            . $existing->date->format('Y-m-d')
            . ' shift ' . $existing->shift_type
            . ' sudah ada (Form #' . $existing->form_number . '). '
            . 'Hapus form yang ada terlebih dahulu jika ingin membuat ulang untuk shift yang sama.'
        );
    }
}
