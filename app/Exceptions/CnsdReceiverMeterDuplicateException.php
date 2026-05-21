<?php

namespace App\Exceptions;

use App\Models\Cnsd\CnsdReceiverMeterRecord;
use RuntimeException;

class CnsdReceiverMeterDuplicateException extends RuntimeException
{
    public function __construct(
        public readonly CnsdReceiverMeterRecord $existingRecord,
    ) {
        parent::__construct(
            'Form Receiver Meter Reading untuk tanggal '
            . $existingRecord->date?->format('Y-m-d')
            . ' shift ' . $existingRecord->shift_type
            . ' sudah ada (Form #' . $existingRecord->form_number . '). '
            . 'Hapus form yang ada terlebih dahulu jika ingin membuat ulang untuk shift yang sama.'
        );
    }
}
