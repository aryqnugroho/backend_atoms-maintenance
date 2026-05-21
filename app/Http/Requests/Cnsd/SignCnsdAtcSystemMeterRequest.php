<?php

namespace App\Http\Requests\Cnsd;

use Illuminate\Foundation\Http\FormRequest;

class SignCnsdAtcSystemMeterRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'role'              => ['required', 'string', 'in:manager,supervisor,technician'],
            'signature'         => ['required', 'string'],
            'technician_row_id' => ['nullable', 'integer'],
        ];
    }
}
