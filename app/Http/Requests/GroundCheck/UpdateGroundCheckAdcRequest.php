<?php

namespace App\Http\Requests\GroundCheck;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroundCheckAdcRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equipment_function' => ['nullable', 'string', 'max:1000'],
            'technical_data'     => ['nullable', 'string', 'max:1000'],
            'last_calibration'   => ['nullable', 'string', 'max:1000'],
            // Editable timestamp — default-nya diisi otomatis oleh backend saat create,
            // tapi teknisi boleh override secara manual (mis. untuk telat mengisi).
            'time_filled'        => ['nullable', 'string', 'max:10'],
            'items'              => ['nullable', 'array'],
            'items.*.id'         => ['required_with:items', 'integer'],
            'items.*.calibration_result'   => ['nullable', 'string', 'max:255'],
            'items.*.tolerance'            => ['nullable', 'string', 'max:255'],
            'items.*.tx1_hasil_pd'         => ['nullable', 'string', 'max:255'],
            'items.*.tx1_in_tolerance'     => ['nullable', 'string', 'max:10'],
            'items.*.tx1_out_of_tolerance' => ['nullable', 'string', 'max:10'],
            'items.*.tx2_hasil_pd'         => ['nullable', 'string', 'max:255'],
            'items.*.tx2_in_tolerance'     => ['nullable', 'string', 'max:10'],
            'items.*.tx2_out_of_tolerance' => ['nullable', 'string', 'max:10'],
            'items.*.keterangan'           => ['nullable', 'string', 'max:1000'],
        ];
    }
}
