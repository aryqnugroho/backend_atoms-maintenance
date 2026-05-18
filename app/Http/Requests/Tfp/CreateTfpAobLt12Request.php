<?php

namespace App\Http\Requests\Tfp;

use App\Models\Tfp\TfpAobLt12Record;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTfpAobLt12Request extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware + controller
    }

    public function rules(): array
    {
        return [
            'date'       => ['required', 'date'],
            'shift_type' => ['required', 'string', Rule::in(TfpAobLt12Record::SHIFT_TYPES)],
            'form_type'  => ['sometimes', 'string'],
            'location'   => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required'       => 'Tanggal harus diisi.',
            'date.date'           => 'Tanggal harus dalam format tanggal yang valid.',
            'shift_type.required' => 'Shift harus dipilih.',
            'shift_type.in'       => 'Shift harus pagi, siang, atau malam.',
        ];
    }
}
