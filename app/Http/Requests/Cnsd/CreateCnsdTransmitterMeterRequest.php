<?php

namespace App\Http\Requests\Cnsd;

use Illuminate\Foundation\Http\FormRequest;

class CreateCnsdTransmitterMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'       => ['required', 'date'],
            'shift_type' => ['required', 'in:pagi,siang,malam'],
            'form_type'  => ['sometimes', 'string', 'max:30'],
            'facility'   => ['sometimes', 'string', 'max:20'],
            'form_code'  => ['sometimes', 'string', 'max:20'],
            'location'   => ['sometimes', 'string', 'max:100'],
        ];
    }
}
