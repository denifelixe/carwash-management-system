<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

    public const APP_NAME = 'app_name';

    public const APP_PHOTO = 'app_photo';

    public const FAVICON = 'favicon';

    public const FAVICON_16 = 'favicon_16';

    public const FAVICON_32 = 'favicon_32';

    public const APPLE_TOUCH_ICON = 'apple_touch_icon';

    public const ANDROID_CHROME_192 = 'android_chrome_192';

    public const ANDROID_CHROME_512 = 'android_chrome_512';

    public const SITE_WEBMANIFEST = 'site_webmanifest';

    public const META_TITLE = 'meta_title';

    public const META_DESCRIPTION = 'meta_description';

    public const META_IMAGE = 'meta_image';

    public const WHATSAPP = 'whatsapp';

    public const INSTAGRAM = 'instagram';

    public const RECEIPT_BUSINESS_NAME = 'receipt_business_name';

    public const RECEIPT_SHOW_LOGO = 'receipt_show_logo';

    public const RECEIPT_SHOW_QR = 'receipt_show_qr';

    public const RECEIPT_FOOTER_NOTE = 'receipt_footer_note';

    public const RECEIPT_PHOTO = 'receipt_photo';

    /**
     * Printed width of the slip's mark, in millimetres.
     *
     * The default is the 15mm the slip printed before the size was adjustable.
     * The ceiling is the full 72mm printable area of an 80mm roll.
     */
    public const RECEIPT_LOGO_WIDTH = 'receipt_logo_width';

    public const RECEIPT_LOGO_WIDTH_DEFAULT = 15;

    public const RECEIPT_LOGO_WIDTH_MIN = 8;

    public const RECEIPT_LOGO_WIDTH_MAX = 72;

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

    public static function appName(): string
    {
        return self::brand(self::APP_NAME) ?? (string) config('app.name', 'Carwash');
    }

    public static function appPhotoUrl(): ?string
    {
        return self::publicUrl(self::APP_PHOTO);
    }

    public static function faviconUrl(): ?string
    {
        return self::publicUrl(self::FAVICON);
    }

    public static function favicon16Url(): ?string
    {
        return self::publicUrl(self::FAVICON_16);
    }

    public static function favicon32Url(): ?string
    {
        return self::publicUrl(self::FAVICON_32);
    }

    public static function appleTouchIconUrl(): ?string
    {
        return self::publicUrl(self::APPLE_TOUCH_ICON);
    }

    public static function androidChrome192Url(): ?string
    {
        return self::publicUrl(self::ANDROID_CHROME_192);
    }

    public static function androidChrome512Url(): ?string
    {
        return self::publicUrl(self::ANDROID_CHROME_512);
    }

    public static function siteWebmanifestUrl(): ?string
    {
        return self::publicUrl(self::SITE_WEBMANIFEST);
    }

    public static function metaTitle(): string
    {
        return self::brand(self::META_TITLE) ?? self::appName().' Management System';
    }

    public static function metaDescription(): string
    {
        return self::brand(self::META_DESCRIPTION)
            ?? 'Aplikasi manajemen carwash '.self::appName().': kasir POS, order & antrean, booking, stok, keuangan, serta kartu stempel digital untuk member.';
    }

    public static function metaImageUrl(): ?string
    {
        return self::publicUrl(self::META_IMAGE);
    }

    public static function whatsapp(): string
    {
        return self::brand(self::WHATSAPP) ?? '6281800090009';
    }

    public static function instagram(): string
    {
        return self::brand(self::INSTAGRAM) ?? 'zenwash.id';
    }

    /**
     * The trading name printed on the thermal slip.
     *
     * An outlet often bills under a name the app is not installed under, so the
     * receipt carries its own; left unset it simply follows the app name.
     */
    public static function receiptBusinessName(): string
    {
        return self::brand(self::RECEIPT_BUSINESS_NAME) ?? self::appName();
    }

    /**
     * The fine print under the thank-you line. An outlet that saves it blank
     * prints no line at all, which is why the default only stands in for an
     * unset key, not for an empty one.
     */
    public static function receiptFooterNote(): string
    {
        return self::brand(self::RECEIPT_FOOTER_NOTE)
            ?? 'Struk ini adalah bukti pembayaran yang sah.';
    }

    /**
     * The mark printed on the slip.
     *
     * A slip is not the app: an outlet often wants a plain, high-contrast mark
     * on the roll and a richer one in the console. Until it uploads its own,
     * the slip borrows the app photo, so nothing on paper changes by adding
     * this setting.
     */
    public static function receiptPhotoUrl(): ?string
    {
        return self::publicUrl(self::RECEIPT_PHOTO) ?? self::appPhotoUrl();
    }

    /**
     * Clamped on the way out as well as validated on the way in: a value left
     * over from an older range must still print inside the roll.
     */
    public static function receiptLogoWidth(): int
    {
        $width = self::brand(self::RECEIPT_LOGO_WIDTH);

        if ($width === null || ! ctype_digit($width)) {
            return self::RECEIPT_LOGO_WIDTH_DEFAULT;
        }

        return max(
            self::RECEIPT_LOGO_WIDTH_MIN,
            min(self::RECEIPT_LOGO_WIDTH_MAX, (int) $width),
        );
    }

    /** Whether the slip carries its own mark rather than borrowing the app photo. */
    public static function hasOwnReceiptPhoto(): bool
    {
        return self::brand(self::RECEIPT_PHOTO) !== null;
    }

    public static function receiptShowsLogo(): bool
    {
        return self::flag(self::RECEIPT_SHOW_LOGO, true);
    }

    /**
     * The verification QR stays off until the outlet asks for it: it costs the
     * roll about 30mm, and it is the block the print tests keep off the paper.
     */
    public static function receiptShowsQr(): bool
    {
        return self::flag(self::RECEIPT_SHOW_QR, false);
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

    public static function forget(string $key): void
    {
        AppSetting::query()->where('key', $key)->delete();

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

    public static function applyBranding(): void
    {
        config(['app.name' => self::appName()]);
    }

    /**
     * Branding as the demo must read it.
     *
     * The demo runs on the same installation as the live console, so the
     * outlet's own name, photo, favicons, handles and page metadata would leak
     * onto it through the shared settings table. Every branding key is
     * therefore answered as unset on the demo domain, dropping each getter back
     * to the shipped defaults. The timezone is not branding and still reads
     * straight from the settings.
     */
    private static function brand(string $key): ?string
    {
        return self::isDemoRequest() ? null : self::get($key);
    }

    /** Flags are stored as the strings '1' and '0', like every other value. */
    private static function flag(string $key, bool $default): bool
    {
        $value = self::brand($key);

        return $value === null ? $default : $value === '1';
    }

    private static function isDemoRequest(): bool
    {
        return (string) config('app.type') === 'DEMO'
            || request()->getHost() === (string) config('domains.demo');
    }

    private static function publicUrl(string $key): ?string
    {
        $path = self::brand($key);

        return $path !== null ? Storage::disk('public')->url($path) : null;
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
