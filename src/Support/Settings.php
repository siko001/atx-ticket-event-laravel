<?php

namespace AtxDigital\Ticketing\Support;

use AtxDigital\Ticketing\Models\Setting;
use Throwable;

/**
 * Durable key/value settings backed by the ticketing_settings table.
 * Reads fail soft (returning the default) when the table does not exist
 * yet, so the package keeps working before the migration has run.
 */
class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $setting = Setting::query()->find($key);

            return $setting->value ?? $default;
        } catch (Throwable) {
            return $default;
        }
    }

    public static function set(string $key, mixed $value): void
    {
        try {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        } catch (Throwable) {
            // Table missing (migration not run yet) — nothing durable to do.
        }
    }
}
