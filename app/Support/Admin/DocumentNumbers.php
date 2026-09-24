<?php

namespace App\Support\Admin;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class DocumentNumbers
{
    public static function order(bool $booking, ?string $createdAt = null): string
    {
        $period = substr($createdAt ?? now()->toDateTimeString(), 0, 7);
        $sequence = self::next('order', $period, 99999999);
        $monthYear = substr($period, 5, 2).substr($period, 2, 2);

        return str_pad((string) $sequence, 8, '0', STR_PAD_LEFT).'/ORD/'.($booking ? 'BK/' : '').$monthYear;
    }

    public static function cashEntry(string $category, string $date): string
    {
        $sequence = self::next('cash', $date, 9999);

        return FinanceReference::make($category, $date, $sequence);
    }

    private static function next(string $scope, string $period, int $limit): int
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Penerbitan nomor harus berada dalam transaksi database.');
        }

        DB::table('number_sequences')->insertOrIgnore([
            'scope' => $scope,
            'period' => $period,
            'last_number' => 0,
        ]);

        $query = DB::table('number_sequences')->where('scope', $scope)->where('period', $period);
        $sequence = (int) $query->lockForUpdate()->value('last_number') + 1;

        if ($sequence > $limit) {
            throw new RuntimeException("Nomor {$scope} untuk {$period} sudah mencapai batas.");
        }

        $query->update(['last_number' => $sequence]);

        return $sequence;
    }
}
