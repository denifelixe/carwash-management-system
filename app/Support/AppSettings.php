<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The installation's own settings, and the timezone above all.
 *
 * Datetime columns hold the outlet's wall clock, not a UTC instant. That works
 * because the configured zone is pushed into PHP's default timezone once at
 * boot: from then on now(), the model casts, and every presenter already speak
 * local time, so nothing downstream converts anything. Reading a day back is
 * therefore a plain date lookup.
 */
class AppSettings
{
    public const TIMEZONE = 'timezone';

    private const CACHE_KEY = 'app_settings';

    /**
     * The zone every stored datetime is written in. Falls back to the config
     * default while the settings table does not exist yet — during the very
     * first migrate, and in CI before the schema is built.
     */
    public static function timezone(): string
    {
        $timezone = self::get(self::TIMEZONE);

        return $timezone !== null && Timezones::has($timezone)
            ? $timezone
            : (string) config('app.timezone', Timezones::FALLBACK);
    }

    public static function get(string $key): ?string
    {
        return self::all()[$key] ?? null;
    }

    public static function put(string $key, string $value, ?int $adminId = null): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by_admin_id' => $adminId],
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Hand the configured zone to PHP itself. Called first thing in
     * AppServiceProvider::boot, and again right after the zone is changed so
     * the response that saved it already reads on the new clock.
     */
    public static function applyTimezone(): void
    {
        $timezone = self::timezone();

        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);
    }

    /**
     * @return array<string, string>
     */
    private static function all(): array
    {
        try {
            if (! Schema::hasTable('app_settings')) {
                return [];
            }

            /** @var array<string, string> $settings */
            $settings = Cache::rememberForever(
                self::CACHE_KEY,
                fn (): array => AppSetting::query()->pluck('value', 'key')->all(),
            );

            return $settings;
        } catch (Throwable) {
            /* No database yet: the caller falls back to the config default. */
            return [];
        }
    }
}
