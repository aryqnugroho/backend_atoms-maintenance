<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * Key/value settings store. Use the static helpers — direct model access
 * works but the helpers handle absent rows + hashing semantics.
 */
class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = ['key', 'value'];

    public const KEY_MONITOR_PASSWORD_HASH = 'monitor_password_hash';

    public static function get(string $key, ?string $default = null): ?string
    {
        $row = static::query()->where('key', $key)->first(['value']);
        return $row?->value ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function verifyMonitorPassword(string $candidate): bool
    {
        $hash = static::get(self::KEY_MONITOR_PASSWORD_HASH);
        if (!$hash) return false;
        return Hash::check($candidate, $hash);
    }

    public static function setMonitorPassword(string $plain): void
    {
        static::set(self::KEY_MONITOR_PASSWORD_HASH, Hash::make($plain));
    }
}
