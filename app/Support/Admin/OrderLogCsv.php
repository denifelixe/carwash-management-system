<?php

namespace App\Support\Admin;

use App\Support\VehiclePlate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The report's order log as a spreadsheet.
 *
 * One copy of the file for both modes: the live report and the demo console
 * hand it different rows and get back the same columns, so a downloaded file
 * never depends on which console produced it.
 */
class OrderLogCsv
{
    /**
     * Excel reads ';' as the column break on an Indonesian install, where ','
     * is the decimal separator. A comma-delimited file opens there as one
     * column of text, which is the whole point of the download lost.
     */
    private const DELIMITER = ';';

    /** @var list<string> */
    private const HEADINGS = [
        'Tanggal',
        'Jam',
        'No Order',
        'Kendaraan',
        'Plat',
        'Customer',
        'No HP',
        'Layanan',
        'Status',
        'Total',
    ];

    /** How the saved file names itself, including what it was narrowed to. */
    public static function fileName(CarbonImmutable $from, CarbonImmutable $to, ?string $serviceName): string
    {
        $service = $serviceName === null || $serviceName === ''
            ? ''
            : '-'.Str::slug($serviceName);

        return sprintf(
            'laporan-order%s-%s-sd-%s.csv',
            $service,
            $from->toDateString(),
            $to->toDateString(),
        );
    }

    /**
     * Streamed rather than built in memory: a year-wide range is thousands of
     * orders, and the rows arrive lazily from the database anyway.
     *
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    public static function download(iterable $rows, string $fileName): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($rows): void {
                $handle = fopen('php://output', 'w');

                /* Excel only reads the file as UTF-8 when it opens with a byte
                 * order mark; without it every 'é' and '—' arrives mangled. */
                fwrite($handle, "\u{FEFF}");
                self::write($handle, self::HEADINGS);

                foreach ($rows as $row) {
                    self::write($handle, [
                        (string) $row['date'],
                        (string) $row['time'],
                        (string) $row['orderNo'],
                        (string) $row['vehicle'],
                        VehiclePlate::format($row['plate']),
                        (string) $row['customer'],
                        self::phone($row['phone']),
                        (string) $row['services'],
                        (string) $row['status'],
                        /* Left as a bare integer so the column can be summed. */
                        (string) (int) $row['total'],
                    ]);
                }

                fclose($handle);
            },
            $fileName,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache',
            ],
        );
    }

    /**
     * Grouped rather than written as bare digits, because Excel reads a run of
     * digits as a number and silently eats the leading zero — "081510239393"
     * would land in the sheet as 81510239393. The dashes keep it text, and
     * match how the app already prints a phone number.
     */
    private static function phone(mixed $phone): string
    {
        $digits = (string) $phone;

        if (preg_match('/\A\d{9,15}\z/', $digits) !== 1) {
            return $digits;
        }

        $groups = str_split($digits, 4);
        $last = count($groups) - 1;

        /* A one or two digit tail reads as a typo, so it joins the group
         * before it: 0812345678 groups as 0812-345678, not 0812-3456-78. */
        if ($last > 0 && strlen($groups[$last]) < 3) {
            $groups[$last - 1] .= array_pop($groups);
        }

        return implode('-', $groups);
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $fields
     */
    private static function write($handle, array $fields): void
    {
        fputcsv($handle, $fields, self::DELIMITER, '"', '', "\r\n");
    }
}
