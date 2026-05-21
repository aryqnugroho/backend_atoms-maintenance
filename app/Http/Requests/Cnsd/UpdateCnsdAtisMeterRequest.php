<?php

namespace App\Http\Requests\Cnsd;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCnsdAtisMeterRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'merk'               => ['sometimes', 'nullable', 'string', 'max:60'],
            'type'               => ['sometimes', 'nullable', 'string', 'max:60'],
            'serial_number'      => ['sometimes', 'nullable', 'string', 'max:60'],
            'items'              => ['sometimes', 'array'],
            'items.*.id'         => ['required_with:items', 'integer'],
            'items.*.reading'    => ['nullable', 'string', 'max:255'],
            'items.*.keterangan' => ['nullable', 'string'],
        ];
    }
}
