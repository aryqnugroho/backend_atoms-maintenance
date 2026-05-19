<?php

namespace App\Http\Requests\Cnsd;

use Illuminate\Foundation\Http\FormRequest;

class CreateCnsdGlidepathMeterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date'          => ['required', 'date'],
            'shift_type'    => ['required', 'in:pagi,siang,malam'],
            'location'      => ['nullable', 'string', 'max:255'],
            'form_type'     => ['nullable', 'string', 'max:100'],
            'facility'      => ['nullable', 'string', 'max:100'],
            'form_code'     => ['nullable', 'string', 'max:50'],
            'merk'          => ['nullable', 'string', 'max:255'],
            'type'          => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
