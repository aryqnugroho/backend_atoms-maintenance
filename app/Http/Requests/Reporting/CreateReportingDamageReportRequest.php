<?php

namespace App\Http\Requests\Reporting;

use App\Models\Reporting\ReportingDamageReport;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateReportingDamageReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware + controller
    }

    public function rules(): array
    {
        $obstacleCodes = array_keys(ReportingDamageReport::OBSTACLE_CODES);

        return [
            'report_date'          => ['required', 'date'],
            'location'             => ['required', 'string', 'max:200'],
            'facility'             => ['required', 'string', 'max:200'],
            'equipment_name'       => ['required', 'string', 'max:200'],
            'equipment_module'     => ['nullable', 'string', 'max:200'],
            'damage_category'      => ['required', 'string', Rule::in(ReportingDamageReport::DAMAGE_CATEGORIES)],
            'damage_description'   => ['required', 'string'],
            'damage_cause'         => ['nullable', 'string'],
            'repair_action'        => ['nullable', 'string'],
            'repair_by_type'       => ['nullable', 'string', Rule::in(ReportingDamageReport::REPAIR_BY_TYPES)],
            'damage_started_at'    => ['nullable', 'date'],
            'repair_finished_at'   => ['nullable', 'date'],
            'downtime_hours'       => ['nullable', 'numeric', 'min:0'],
            'obstacle_code'        => ['nullable', 'string', Rule::in($obstacleCodes)],
            'obstacle_description' => ['nullable', 'string'],

            'manager_id'   => ['required', 'integer', 'exists:local_users,id'],

            'repairers'                    => ['required', 'array', 'min:1'],
            'repairers.*.person_id'        => ['nullable', 'integer'],
            'repairers.*.person_name'      => ['required', 'string', 'max:120'],
            'repairers.*.person_role'      => ['nullable', 'string', 'max:50'],
            'repairers.*.person_division'  => ['nullable', 'string', 'max:30'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $code = $this->input('obstacle_code');
            $desc = $this->input('obstacle_description');

            if ($code === 'AL' && (empty($desc) || trim((string) $desc) === '')) {
                $v->errors()->add('obstacle_description', 'Alasan Lain wajib diisi ketika Kode Hambatan = AL.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'report_date.required'        => 'Tanggal laporan harus diisi.',
            'report_date.date'            => 'Tanggal laporan tidak valid.',
            'location.required'           => 'Lokasi harus diisi.',
            'facility.required'           => 'Fasilitas harus diisi.',
            'equipment_name.required'     => 'Nama peralatan harus diisi.',
            'damage_category.required'    => 'Kategori kerusakan harus dipilih.',
            'damage_category.in'          => 'Kategori kerusakan harus 1, 2, atau 3.',
            'damage_description.required' => 'Uraian kerusakan harus diisi.',
            'manager_id.required'         => 'Manager Teknik harus dipilih.',
            'manager_id.exists'           => 'Manager Teknik yang dipilih tidak ditemukan.',
            'repairers.required'          => 'Minimal satu pelaksana perbaikan harus ditambahkan.',
            'repairers.min'               => 'Minimal satu pelaksana perbaikan harus ditambahkan.',
            'repairers.*.person_name.required' => 'Nama pelaksana perbaikan wajib diisi.',
            'obstacle_code.in'            => 'Kode hambatan tidak valid.',
        ];
    }
}
