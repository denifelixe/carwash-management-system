<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ReplaceAdminProfilePhoto
{
    public function handle(Admin $admin, UploadedFile $photo): void
    {
        $previousPath = $admin->profile_photo_path;
        $newPath = $photo->store('admin-profile-photos');

        if ($newPath === false) {
            throw new RuntimeException('Foto profil gagal disimpan.');
        }

        try {
            $admin->forceFill(['profile_photo_path' => $newPath])->save();
        } catch (\Throwable $exception) {
            Storage::delete($newPath);

            throw $exception;
        }

        if ($previousPath !== null) {
            Storage::delete($previousPath);
        }
    }
}
