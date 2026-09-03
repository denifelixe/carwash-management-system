<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use App\Support\AppSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Saves how the thermal slip is dressed (Master > Struk).
 *
 * Kept apart from UpdateAppBranding: the slip's trading name and mark are not
 * the app's, and an outlet can dress the roll without touching the console.
 */
class UpdateReceiptSettings
{
    /**
     * @param  array{receipt_business_name: string, receipt_footer_note: string, receipt_show_logo: bool, receipt_show_qr: bool, receipt_logo_width: int, remove_receipt_photo: bool, receipt_photo: UploadedFile|null}  $settings
     */
    public function handle(array $settings, Admin $admin): void
    {
        AppSettings::put(AppSettings::RECEIPT_BUSINESS_NAME, $settings['receipt_business_name'], $admin->id);
        AppSettings::put(AppSettings::RECEIPT_FOOTER_NOTE, $settings['receipt_footer_note'], $admin->id);
        AppSettings::put(AppSettings::RECEIPT_SHOW_LOGO, $settings['receipt_show_logo'] ? '1' : '0', $admin->id);
        AppSettings::put(AppSettings::RECEIPT_SHOW_QR, $settings['receipt_show_qr'] ? '1' : '0', $admin->id);
        AppSettings::put(AppSettings::RECEIPT_LOGO_WIDTH, (string) $settings['receipt_logo_width'], $admin->id);

        if ($settings['remove_receipt_photo']) {
            $this->removePhoto();

            return;
        }

        $this->storePhoto($settings['receipt_photo'], $admin);
    }

    private function storePhoto(?UploadedFile $photo, Admin $admin): void
    {
        if ($photo === null) {
            return;
        }

        $oldPath = AppSettings::get(AppSettings::RECEIPT_PHOTO);
        $newPath = $photo->store('app-branding', 'public');

        if ($newPath === false) {
            throw new RuntimeException('Logo struk gagal disimpan.');
        }

        AppSettings::put(AppSettings::RECEIPT_PHOTO, $newPath, $admin->id);

        if ($oldPath !== null && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }
    }

    /** Dropping the slip's own mark hands it back to the app photo. */
    private function removePhoto(): void
    {
        $path = AppSettings::get(AppSettings::RECEIPT_PHOTO);

        if ($path === null) {
            return;
        }

        Storage::disk('public')->delete($path);
        AppSettings::forget(AppSettings::RECEIPT_PHOTO);
    }
}
