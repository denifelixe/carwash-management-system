<?php

namespace App\Support\Admin;

use App\Models\CashEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Stores a cash entry's proofs on the default (private) disk, under the
 * entry's reference, and remembers what it wrote so a failed transaction can
 * take the files back out.
 */
class CashEntryAttachments
{
    /**
     * @param  list<UploadedFile>  $files
     * @param  list<array{disk: string, path: string}>  $storedFiles  appended to as files land
     */
    public static function store(CashEntry $entry, array $files, array &$storedFiles): void
    {
        $disk = (string) config('filesystems.default');

        foreach ($files as $file) {
            $path = $file->store($entry->reference, $disk);

            if ($path === false) {
                throw new RuntimeException('Lampiran keuangan gagal disimpan.');
            }

            $storedFiles[] = ['disk' => $disk, 'path' => $path];
            $entry->attachments()->create([
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ]);
        }
    }

    /** @param list<array{disk: string, path: string}> $storedFiles */
    public static function delete(array $storedFiles): void
    {
        foreach ($storedFiles as $storedFile) {
            Storage::disk($storedFile['disk'])->delete($storedFile['path']);
        }
    }
}
