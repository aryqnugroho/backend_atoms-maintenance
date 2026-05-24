<?php

namespace App\Http\Requests\Cnsd;

use Illuminate\Foundation\Http\FormRequest;

class SignCnsdVccsFreqMeterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'role'              => ['required', 'in:manager,supervisor,technician'],
            'signature'         => ['required', 'string'],
            'technician_row_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
