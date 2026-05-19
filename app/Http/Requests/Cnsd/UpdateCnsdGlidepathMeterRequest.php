<?php

namespace App\Http\Requests\Cnsd;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCnsdGlidepathMeterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'items'              => ['required', 'array'],
            'items.*.id'         => ['required', 'integer'],
            'items.*.hasil_1'    => ['nullable', 'string', 'max:255'],
            'items.*.hasil_2'    => ['nullable', 'string', 'max:255'],
            'items.*.keterangan' => ['nullable', 'string'],
        ];
    }
}
