<?php

namespace App\Support\Admin;

use Carbon\CarbonImmutable;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Laporan Penjualan Harian as a spreadsheet, laid out like the outlet's
 * old report: one row per day, a column per payment method, a TOTAL line.
 * Same Excel conventions as OrderLogCsv (';', BOM) for an Indonesian install.
 */
class DailySalesCsv
{
    public static function fileName(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return sprintf('laporan-penjualan-harian-%s-sd-%s.csv', $from->toDateString(), $to->toDateString());
    }

    /**
     * @param  array{methods: list<string>, rows: list<array{date: string, transactions: int, total: int, methods: array<string, int>}>, total: array{transactions: int, total: int, methods: array<string, int>}}  $report
     */
    public static function download(array $report, string $fileName): StreamedResponse
    {
        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new RuntimeException('Unable to open the daily sales CSV output stream.');
            }

            fwrite($handle, "\u{FEFF}");
            fputcsv(
                $handle,
                ['Tanggal', 'Jml Trs', 'Total Transaksi', ...array_map(
                    static fn (string $method): string => 'Jml Bayar '.$method,
                    $report['methods'],
                )],
                ';', '"', '', "\r\n",
            );

            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    CarbonImmutable::parse($row['date'])->format('d/m/Y'),
                    $row['transactions'],
                    $row['total'],
                    ...array_values($row['methods']),
                ], ';', '"', '', "\r\n");
            }

            fputcsv($handle, [
                'TOTAL',
                $report['total']['transactions'],
                $report['total']['total'],
                ...array_values($report['total']['methods']),
            ], ';', '"', '', "\r\n");

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, no-cache']);
    }
}
