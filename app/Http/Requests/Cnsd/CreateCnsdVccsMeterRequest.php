<?php

namespace App\Http\Requests\Cnsd;

use Illuminate\Foundation\Http\FormRequest;

class CreateCnsdVccsMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'          => ['required', 'date_format:Y-m-d'],
            'shift_type'    => ['required', 'in:pagi,siang,malam'],
            'form_type'     => ['sometimes', 'string', 'max:30'],
            'facility'      => ['sometimes', 'string', 'max:20'],
            'location'      => ['sometimes', 'string', 'max:100'],
            'merk'          => ['sometimes', 'nullable', 'string', 'max:60'],
            'type'          => ['sometimes', 'nullable', 'string', 'max:60'],
            'serial_number' => ['sometimes', 'nullable', 'string', 'max:60'],
        ];
    }
}
