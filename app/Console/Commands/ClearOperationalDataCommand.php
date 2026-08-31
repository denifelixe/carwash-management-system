<?php

namespace App\Console\Commands;

use App\Actions\ClearOperationalData;
use App\Support\DangerousKeyManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:clear-data
    {--force : Izinkan penghapusan tanpa konfirmasi dan wajib digunakan di production}
    {--key= : Dangerous key sekali pakai dari DANGEROUS_KEY}')]
#[Description('Hapus seluruh data operasional tanpa menghapus user, role, dan data master')]
class ClearOperationalDataCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(
        ClearOperationalData $clearOperationalData,
        DangerousKeyManager $dangerousKeyManager,
    ): int {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Production mewajibkan opsi --force.');

            return self::FAILURE;
        }

        $providedKey = $this->option('key');

        if (! is_string($providedKey) || $providedKey === '') {
            $this->error('Opsi --key wajib diisi dengan DANGEROUS_KEY saat ini.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            'Seluruh data operasional akan dihapus permanen. Lanjutkan?',
        )) {
            $this->info('Penghapusan dibatalkan.');

            return self::SUCCESS;
        }

        try {
            $dangerousKeyManager->validateAndRotate($providedKey);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        try {
            $deletedRows = $clearOperationalData->handle();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Penghapusan gagal. DANGEROUS_KEY sudah dirotasi; gunakan key baru untuk mencoba lagi.');

            return self::FAILURE;
        }

        $this->table(
            ['Tabel', 'Baris dihapus'],
            collect($deletedRows)
                ->map(fn (int $count, string $table): array => [$table, $count])
                ->values()
                ->all(),
        );
        $this->info('Data operasional berhasil dibersihkan. User, role, dan data master tetap tersimpan.');
        $this->info('DANGEROUS_KEY telah dirotasi di file environment.');

        return self::SUCCESS;
    }
}
