<?php

namespace App\Exceptions;

use App\Models\Cnsd\CnsdVccsFreqMeterRecord;
use RuntimeException;

class CnsdVccsFreqMeterDuplicateException extends RuntimeException
{
    public CnsdVccsFreqMeterRecord $existingRecord;

    public function __construct(CnsdVccsFreqMeterRecord $existing)
    {
        $this->existingRecord = $existing;
        parent::__construct(
            'Form VCCS Frequentis Meter Reading untuk tanggal '
            . $existing->date->format('Y-m-d')
            . ' shift ' . $existing->shift_type
            . ' sudah ada (Form #' . $existing->form_number . '). '
            . 'Hapus form yang ada terlebih dahulu jika ingin membuat ulang untuk shift yang sama.'
        );
    }
}
