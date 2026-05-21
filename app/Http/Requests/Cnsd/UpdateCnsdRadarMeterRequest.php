<?php

namespace App\Http\Requests\Cnsd;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Updating a CNSD Radar Meter record allows mutating:
 *   - Equipment metadata (merk / type / serial_number) — pre-filled from paper
 *     defaults but editable so Manager/Supervisor can correct identification.
 *   - Item values (kondisi_teknis_tx1/tx2, hasil, keterangan).
 *
 * Personnel, date, shift, signatures, and form_number remain immutable here.
 */
class UpdateCnsdRadarMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Equipment metadata (paper-form header: Merk / Type / SN)
            'merk'                           => ['nullable', 'string', 'max:60'],
            'type'                           => ['nullable', 'string', 'max:60'],
            'serial_number'                  => ['nullable', 'string', 'max:60'],

            'items'                          => ['required', 'array', 'min:1'],
            'items.*.id'                     => ['required', 'integer'],
            'items.*.kondisi_teknis_tx1'     => ['nullable', 'string', 'max:120'],
            'items.*.kondisi_teknis_tx2'     => ['nullable', 'string', 'max:120'],
            'items.*.hasil'                  => ['nullable', 'string', 'max:120'],
            'items.*.keterangan'             => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'      => 'Minimal satu item harus disertakan.',
            'items.array'         => 'Items harus berupa array.',
            'items.*.id.required' => 'Setiap item wajib menyertakan id.',
        ];
    }
}
