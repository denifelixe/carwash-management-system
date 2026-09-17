<?php

namespace App\Support\Admin;

use Carbon\CarbonImmutable;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceLogCsv
{
    public static function fileName(CarbonImmutable $from, CarbonImmutable $to, string $direction): string
    {
        $suffix = match (FinanceReportQueries::direction($direction)) {
            'in' => '-pemasukan',
            'out' => '-pengeluaran',
            default => '',
        };

        return sprintf('laporan-keuangan%s-%s-sd-%s.csv', $suffix, $from->toDateString(), $to->toDateString());
    }

    /** @param iterable<int, array<string, mixed>> $rows */
    public static function download(iterable $rows, string $fileName): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new RuntimeException('Unable to open the finance CSV output stream.');
            }

            fwrite($handle, "\u{FEFF}");
            fputcsv($handle, ['Tanggal', 'Jam', 'Referensi', 'Jenis', 'Sumber', 'Kategori', 'Keterangan', 'Metode', 'Shift', 'Dicatat Oleh', 'No Order', 'Pemasukan', 'Pengeluaran'], ';', '"', '', "\r\n");

            foreach ($rows as $row) {
                $fields = [
                    CarbonImmutable::parse($row['date'])->format('d/m/Y'),
                    str_replace('.', ':', $row['time']),
                    $row['ref'],
                    $row['direction'] === 'in' ? 'Pemasukan' : 'Pengeluaran',
                    ($row['source'] ?? 'manual') === 'pos' ? 'POS' : 'Manual',
                    $row['category'],
                    $row['description'],
                    $row['method'],
                    $row['shift'] ?? 'Tanpa Shift',
                    $row['recordedBy'],
                    $row['orderNo'] ?? '',
                    $row['direction'] === 'in' ? (int) $row['amount'] : 0,
                    $row['direction'] === 'out' ? (int) $row['amount'] : 0,
                ];

                /** User-entered text must never become an Excel formula. */
                $fields = array_map(static function (string|int $value): string|int {
                    return is_string($value) && preg_match('/^[\s]*[=+@-]/u', $value) === 1
                        ? "'".$value
                        : $value;
                }, $fields);
                fputcsv($handle, $fields, ';', '"', '', "\r\n");
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, no-cache']);
    }
}
