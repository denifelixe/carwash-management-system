<?php

namespace App\Support\Admin;

use Carbon\CarbonImmutable;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Penjualan per Layanan as a spreadsheet, laid out like the outlet's old
 * "daftar penjualan per item per merek": a block per category group, a total
 * line under each, and a grand total. Same Excel conventions as OrderLogCsv.
 */
class ItemSalesCsv
{
    public static function fileName(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return sprintf('laporan-penjualan-per-layanan-%s-sd-%s.csv', $from->toDateString(), $to->toDateString());
    }

    /**
     * @param  array{groups: list<array{group: string, items: list<array{name: string, category: string, quantity: int, total: int}>, quantity: int, total: int}>, quantity: int, total: int}  $report
     */
    public static function download(array $report, string $fileName): StreamedResponse
    {
        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new RuntimeException('Unable to open the item sales CSV output stream.');
            }

            $write = static function (array $fields) use ($handle): void {
                /** Service names are typed in Master > Layanan; none may become a formula. */
                $fields = array_map(static fn (string|int $value): string|int => is_string($value) && preg_match('/^[\s]*[=+@-]/u', $value) === 1
                    ? "'".$value
                    : $value, $fields);
                fputcsv($handle, $fields, ';', '"', '', "\r\n");
            };

            fwrite($handle, "\u{FEFF}");
            $write(['Grup Kategori', 'Layanan', 'Kategori', 'Qty', 'Total Harga']);

            foreach ($report['groups'] as $group) {
                foreach ($group['items'] as $item) {
                    $write([$group['group'], $item['name'], $item['category'], $item['quantity'], $item['total']]);
                }

                $write(['Total '.$group['group'], '', '', $group['quantity'], $group['total']]);
            }

            $write(['TOTAL', '', '', $report['quantity'], $report['total']]);

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, no-cache']);
    }
}
