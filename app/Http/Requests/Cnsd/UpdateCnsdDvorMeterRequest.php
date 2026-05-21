<?php

namespace App\Http\Requests\Cnsd;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCnsdDvorMeterRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'merk'                        => ['sometimes', 'nullable', 'string', 'max:60'],
            'type'                        => ['sometimes', 'nullable', 'string', 'max:60'],
            'serial_number'               => ['sometimes', 'nullable', 'string', 'max:60'],
            'tx1_mode'                    => ['sometimes', 'nullable', 'string', 'in:MAIN,STANDBY'],
            'tx2_mode'                    => ['sometimes', 'nullable', 'string', 'in:MAIN,STANDBY'],
            'items'                       => ['sometimes', 'array'],
            'items.*.id'                  => ['required_with:items', 'integer'],
            'items.*.hasil_pemeriksaan'   => ['nullable', 'string', 'max:255'],
            'items.*.keterangan'          => ['nullable', 'string'],
        ];
    }
}
