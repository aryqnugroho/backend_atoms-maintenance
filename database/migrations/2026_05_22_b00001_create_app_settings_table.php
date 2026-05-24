<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * App-wide key/value settings table.
 *
 * Currently houses:
 *   - `monitor_password_hash`  bcrypt hash for the workshop TV monitor gate
 *
 * Designed to grow over time — keys are free-form strings, values are text.
 * Keep secrets here (hashed), not in `.env`, so they can be rotated through
 * the UI without redeploys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed the default monitor password ("surabaya") so /monitor works
        // immediately after migrate without a separate seeder step.
        DB::table('app_settings')->insert([
            'key'        => 'monitor_password_hash',
            'value'      => Hash::make('surabaya'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
