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
     * @param  array{app_name: string, whatsapp: string, instagram: string, app_photo: UploadedFile|null, favicon: UploadedFile|null}  $settings
     */
    public function handle(array $settings, Admin $admin): void
    {
        AppSettings::put(AppSettings::APP_NAME, $settings['app_name'], $admin->id);
        AppSettings::put(AppSettings::WHATSAPP, $settings['whatsapp'], $admin->id);
        AppSettings::put(AppSettings::INSTAGRAM, $settings['instagram'], $admin->id);

        $this->storeImage($settings['app_photo'], AppSettings::APP_PHOTO, $admin);
        $this->storeImage($settings['favicon'], AppSettings::FAVICON, $admin);

        AppSettings::applyBranding();
    }

    private function storeImage(?UploadedFile $image, string $settingKey, Admin $admin): void
    {
        if ($image === null) {
            return;
        }

        $oldPath = AppSettings::get($settingKey);
        $newPath = $image->store('app-branding', 'public');

        if ($newPath === false) {
            throw new RuntimeException('Gambar aplikasi gagal disimpan.');
        }

        AppSettings::put($settingKey, $newPath, $admin->id);

        if ($oldPath !== null && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }
    }
}
