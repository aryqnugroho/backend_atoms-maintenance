<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add `input_type` to ground_check_adc_items so the frontend can render the
 * correct widget per row:
 *   - numeric           : numeric/text input (Power, Modulation, Squelch)
 *   - dropdown_function : Berfungsi / Tidak Berfungsi
 *   - dropdown_quality  : Baik / Tidak Baik
 *   - dropdown_clarity  : Clear / Tidak Clear
 *   - text              : free-text fallback (Audio Distorsi, dst.)
 *   - header            : section header row, no input
 *
 * Backfill existing rows from the master template by matching parameter_name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ground_check_adc_items', function (Blueprint $table) {
            $table->string('input_type', 30)->default('text')->after('parameter_name');
        });

        // Backfill — keyed by parameter_name (case-insensitive)
        $map = [
            // numeric (transmitter / receiver)
            'power'                => 'numeric',
            'modulation (tx)'      => 'numeric',
            'squelch on'           => 'numeric',
            // text (special phrase like "Tidak Ada")
            'audio distorsi'       => 'text',
            // dropdown_quality
            'interconnection (connector, cable)' => 'dropdown_quality',
            // dropdown_clarity
            'interference'         => 'dropdown_clarity',
        ];

        $items = DB::table('ground_check_adc_items')->get();
        foreach ($items as $row) {
            if ($row->is_header) {
                DB::table('ground_check_adc_items')->where('id', $row->id)->update(['input_type' => 'header']);
                continue;
            }
            $key = mb_strtolower(trim((string) $row->parameter_name));
            $type = $map[$key] ?? null;
            if ($type === null) {
                // Default: all remaining CONSOLE items follow the "Berfungsi" pattern
                $calib = mb_strtolower(trim((string) ($row->calibration_result ?? '')));
                if ($calib === 'berfungsi') {
                    $type = 'dropdown_function';
                } elseif ($calib === 'baik') {
                    $type = 'dropdown_quality';
                } elseif ($calib === 'clear') {
                    $type = 'dropdown_clarity';
                } else {
                    $type = 'text';
                }
            }
            DB::table('ground_check_adc_items')->where('id', $row->id)->update(['input_type' => $type]);
        }
    }

    public function down(): void
    {
        Schema::table('ground_check_adc_items', function (Blueprint $table) {
            $table->dropColumn('input_type');
        });
    }
};
