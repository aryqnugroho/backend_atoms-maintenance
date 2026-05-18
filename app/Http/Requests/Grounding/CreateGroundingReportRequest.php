<?php

namespace App\Http\Requests\Grounding;

use App\Models\Grounding\GroundingReportRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateGroundingReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware + controller
    }

    public function rules(): array
    {
        return [
            'date'               => ['required', 'date'],
            'shift_type'         => ['required', 'string', Rule::in(GroundingReportRecord::SHIFT_TYPES)],
            'equipment_name'     => ['required', 'string', 'max:200'],
            'equipment_location' => ['required', 'string', 'max:200'],
            'work_unit'          => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required'               => 'Tanggal harus diisi.',
            'date.date'                   => 'Tanggal harus dalam format tanggal yang valid.',
            'shift_type.required'         => 'Shift harus dipilih.',
            'shift_type.in'               => 'Shift harus pagi, siang, atau malam.',
            'equipment_name.required'     => 'Nama peralatan harus diisi.',
            'equipment_name.max'          => 'Nama peralatan maksimal 200 karakter.',
            'equipment_location.required' => 'Lokasi peralatan harus diisi.',
            'equipment_location.max'      => 'Lokasi peralatan maksimal 200 karakter.',
        ];
    }
}
