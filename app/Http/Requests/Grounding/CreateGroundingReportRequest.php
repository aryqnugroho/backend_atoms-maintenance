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
        $requiresManualSigners = fn (): bool => $this->input('work_unit', 'Cabang Surabaya') !== 'Cabang Surabaya';

        return [
            'date'               => ['required', 'date'],
            'shift_type'         => ['required', 'string', Rule::in(GroundingReportRecord::SHIFT_TYPES)],
            'equipment_name'     => ['required', 'string', 'max:200'],
            'equipment_location' => ['required', 'string', 'max:200'],
            'work_unit'          => ['sometimes', 'string', Rule::in([
                'Cabang Surabaya',
                'Cabang Kediri',
                'Cabang Malang',
                'Cabang Sumenep',
                'Cabang Jember',
                'Cabang Banyuwangi',
                'Cabang Bawean',
            ])],
            'time_filled'        => ['sometimes', 'date_format:H:i'],
            'manager_id'         => [Rule::requiredIf($requiresManualSigners), 'nullable', 'integer', 'exists:local_users,id'],
            'supervisor_id'      => [Rule::requiredIf($requiresManualSigners), 'nullable', 'integer', 'exists:local_users,id'],
            'technician_ids'     => [Rule::requiredIf($requiresManualSigners), 'array', 'min:1'],
            'technician_ids.*'   => ['integer', 'distinct', 'exists:local_users,id'],
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
            'work_unit.in'                => 'Kantor Unit Kerja yang dipilih tidak tersedia.',
            'time_filled.date_format'     => 'Jam laporan harus dalam format HH:MM.',
            'manager_id.required'         => 'Manager Teknik harus dipilih untuk cabang non-Surabaya.',
            'supervisor_id.required'      => 'Supervisor TFP harus dipilih untuk cabang non-Surabaya.',
            'technician_ids.required'     => 'Minimal satu pelaksana teknisi harus dipilih.',
            'technician_ids.min'          => 'Minimal satu pelaksana teknisi harus dipilih.',
        ];
    }
}
