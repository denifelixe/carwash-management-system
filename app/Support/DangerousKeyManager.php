<?php

namespace App\Support;

use Dotenv\Dotenv;
use Illuminate\Support\Str;
use RuntimeException;

class DangerousKeyManager
{
    public function __construct(private readonly string $environmentFilePath) {}

    public function validateAndRotate(string $providedKey): void
    {
        if (! is_file($this->environmentFilePath)) {
            throw new RuntimeException('File environment tidak ditemukan.');
        }

        $handle = fopen($this->environmentFilePath, 'r+');

        if ($handle === false) {
            throw new RuntimeException('File environment tidak dapat dibuka.');
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('File environment sedang digunakan proses lain.');
            }

            $contents = stream_get_contents($handle);

            if ($contents === false) {
                throw new RuntimeException('File environment tidak dapat dibaca.');
            }

            $values = Dotenv::parse($contents);
            $storedKey = $values['DANGEROUS_KEY'] ?? null;

            if (! is_string($storedKey) || $storedKey === '') {
                throw new RuntimeException('DANGEROUS_KEY belum diatur di file environment.');
            }

            if (! hash_equals($storedKey, $providedKey)) {
                throw new RuntimeException('Dangerous key tidak valid.');
            }

            $updatedContents = $this->replaceKey($contents, Str::random(64));

            rewind($handle);

            if (! ftruncate($handle, 0)) {
                throw new RuntimeException('File environment tidak dapat diperbarui.');
            }

            $bytesWritten = fwrite($handle, $updatedContents);

            if ($bytesWritten === false || $bytesWritten !== strlen($updatedContents) || ! fflush($handle)) {
                throw new RuntimeException('File environment tidak dapat diperbarui.');
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function replaceKey(string $contents, string $newKey): string
    {
        $pattern = '/^([ \t]*(?:export[ \t]+)?DANGEROUS_KEY[ \t]*=)[^\r\n]*(\r?)$/m';
        $matches = preg_match_all($pattern, $contents);

        if ($matches !== 1) {
            throw new RuntimeException('DANGEROUS_KEY harus didefinisikan tepat satu kali.');
        }

        $updatedContents = preg_replace($pattern, '${1}'.$newKey.'${2}', $contents, 1);

        if (! is_string($updatedContents)) {
            throw new RuntimeException('DANGEROUS_KEY tidak dapat diperbarui.');
        }

        return $updatedContents;
    }
}
