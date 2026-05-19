<?php

namespace App\Http\Requests\Reporting;

use App\Models\Reporting\ReportingDamageReport;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update payload for damage reports. report_number, report_date, and signatures
 * are NOT editable here.
 */
class UpdateReportingDamageReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $obstacleCodes = array_keys(ReportingDamageReport::OBSTACLE_CODES);

        return [
            'location'             => ['sometimes', 'required', 'string', 'max:200'],
            'facility'             => ['sometimes', 'required', 'string', 'max:200'],
            'equipment_name'       => ['sometimes', 'required', 'string', 'max:200'],
            'equipment_module'     => ['sometimes', 'nullable', 'string', 'max:200'],
            'damage_category'      => ['sometimes', 'required', 'string', Rule::in(ReportingDamageReport::DAMAGE_CATEGORIES)],
            'damage_description'   => ['sometimes', 'required', 'string'],
            'damage_cause'         => ['sometimes', 'nullable', 'string'],
            'repair_action'        => ['sometimes', 'nullable', 'string'],
            'repair_by_type'       => ['sometimes', 'nullable', 'string', Rule::in(ReportingDamageReport::REPAIR_BY_TYPES)],
            'damage_started_at'    => ['sometimes', 'nullable', 'date'],
            'repair_finished_at'   => ['sometimes', 'nullable', 'date'],
            'downtime_hours'       => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'obstacle_code'        => ['sometimes', 'nullable', 'string', Rule::in($obstacleCodes)],
            'obstacle_description' => ['sometimes', 'nullable', 'string'],

            'manager_id'   => ['sometimes', 'integer', 'exists:local_users,id'],

            'repairers'                    => ['sometimes', 'array', 'min:1'],
            'repairers.*.id'               => ['sometimes', 'nullable', 'integer'],
            'repairers.*.person_id'        => ['sometimes', 'nullable', 'integer'],
            'repairers.*.person_name'      => ['required_with:repairers', 'string', 'max:120'],
            'repairers.*.person_role'      => ['nullable', 'string', 'max:50'],
            'repairers.*.person_division'  => ['nullable', 'string', 'max:30'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (!$this->has('obstacle_code')) {
                return;
            }
            $code = $this->input('obstacle_code');
            $desc = $this->input('obstacle_description');

            if ($code === 'AL' && (empty($desc) || trim((string) $desc) === '')) {
                $v->errors()->add('obstacle_description', 'Alasan Lain wajib diisi ketika Kode Hambatan = AL.');
            }
        });
    }
}
