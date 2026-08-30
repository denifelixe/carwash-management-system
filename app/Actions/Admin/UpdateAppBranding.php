<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use App\Support\AppSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UpdateAppBranding
{
    /**
     * @param  array{app_name: string, whatsapp: string, instagram: string, app_photo: UploadedFile|null, favicon: UploadedFile|null, favicon_16: UploadedFile|null, favicon_32: UploadedFile|null, apple_touch_icon: UploadedFile|null, android_chrome_192: UploadedFile|null, android_chrome_512: UploadedFile|null, site_webmanifest: UploadedFile|null}  $settings
     */
    public function handle(array $settings, Admin $admin): void
    {
        AppSettings::put(AppSettings::APP_NAME, $settings['app_name'], $admin->id);
        AppSettings::put(AppSettings::WHATSAPP, $settings['whatsapp'], $admin->id);
        AppSettings::put(AppSettings::INSTAGRAM, $settings['instagram'], $admin->id);

        $this->storeAsset($settings['app_photo'], AppSettings::APP_PHOTO, $admin);
        $this->storeAsset($settings['favicon'], AppSettings::FAVICON, $admin);
        $this->storeAsset($settings['favicon_16'], AppSettings::FAVICON_16, $admin);
        $this->storeAsset($settings['favicon_32'], AppSettings::FAVICON_32, $admin);
        $this->storeAsset($settings['apple_touch_icon'], AppSettings::APPLE_TOUCH_ICON, $admin);
        $this->storeAsset($settings['android_chrome_192'], AppSettings::ANDROID_CHROME_192, $admin);
        $this->storeAsset($settings['android_chrome_512'], AppSettings::ANDROID_CHROME_512, $admin);
        $this->storeAsset($settings['site_webmanifest'], AppSettings::SITE_WEBMANIFEST, $admin);

        AppSettings::applyBranding();
    }

    private function storeAsset(?UploadedFile $asset, string $settingKey, Admin $admin): void
    {
        if ($asset === null) {
            return;
        }

        $oldPath = AppSettings::get($settingKey);
        $newPath = $asset->store('app-branding', 'public');

        if ($newPath === false) {
            throw new RuntimeException('Asset aplikasi gagal disimpan.');
        }

        AppSettings::put($settingKey, $newPath, $admin->id);

        if ($oldPath !== null && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }
    }
}
