<?php

namespace App\Http\Requests\Cnsd;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCnsdDvorMeterRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            // Allow updating tx modes on the record header
            'tx1_mode'                    => ['nullable', 'string', 'in:MAIN,STANDBY'],
            'tx2_mode'                    => ['nullable', 'string', 'in:MAIN,STANDBY'],
            'items'                       => ['required', 'array'],
            'items.*.id'                  => ['required', 'integer'],
            'items.*.hasil_pemeriksaan'   => ['nullable', 'string', 'max:255'],
            'items.*.keterangan'          => ['nullable', 'string'],
        ];
    }
}
