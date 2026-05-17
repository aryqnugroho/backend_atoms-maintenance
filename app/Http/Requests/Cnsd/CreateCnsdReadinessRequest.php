<?php

namespace App\Http\Requests\Cnsd;

use App\Models\Cnsd\CnsdReadinessRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCnsdReadinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware + controller
    }

    public function rules(): array
    {
        return [
            'form_type'  => ['sometimes', 'string', Rule::in(CnsdReadinessRecord::FORM_TYPES)],
            'facility'   => ['sometimes', 'string', Rule::in(CnsdReadinessRecord::FACILITIES)],
            'date'       => ['required', 'date_format:Y-m-d'],
            'shift_type' => ['required', 'string', Rule::in(CnsdReadinessRecord::SHIFT_TYPES)],
            'location'   => ['sometimes', 'string', 'max:100'],
            'room'       => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required'        => 'Tanggal harus diisi.',
            'date.date_format'     => 'Tanggal harus dalam format YYYY-MM-DD.',
            'shift_type.required'  => 'Shift harus dipilih.',
            'shift_type.in'        => 'Shift harus pagi, siang, atau malam.',
            'form_type.in'         => 'Form type tidak valid.',
            'facility.in'          => 'Facility tidak valid.',
        ];
    }
}
