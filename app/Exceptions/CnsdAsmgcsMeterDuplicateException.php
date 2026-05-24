<?php

namespace App\Exceptions;

use App\Models\Cnsd\CnsdAsmgcsMeterRecord;
use RuntimeException;

class CnsdAsmgcsMeterDuplicateException extends RuntimeException
{
    public CnsdAsmgcsMeterRecord $existingRecord;

    public function __construct(CnsdAsmgcsMeterRecord $existing)
    {
        $this->existingRecord = $existing;
        parent::__construct(
            'Form ASMGCS Meter Reading untuk tanggal '
            . $existing->date->format('Y-m-d')
            . ' shift ' . $existing->shift_type
            . ' sudah ada (Form #' . $existing->form_number . '). '
            . 'Hapus form yang ada terlebih dahulu jika ingin membuat ulang untuk shift yang sama.'
        );
    }
}
