<?php

namespace App\Exceptions;

use App\Models\Tfp\TfpLocalizerRecord;
use RuntimeException;

class TfpLocalizerDuplicateException extends RuntimeException
{
    public function __construct(
        public readonly TfpLocalizerRecord $existingRecord,
        string $message = '',
    ) {
        if ($message === '') {
            $message = sprintf(
                'Form %s untuk tanggal %s shift %s sudah ada (No. %s).',
                $existingRecord->form_type,
                $existingRecord->date?->format('Y-m-d') ?? '',
                $existingRecord->shift_type,
                $existingRecord->form_number,
            );
        }
        parent::__construct($message);
    }
}
