<?php

namespace App\Support\Admin;

use App\Support\Demo\Finance;

/**
 * The finance ledger's categories, shared with the demo console so both sides
 * label the same movement the same way.
 */
class FinanceCategories
{
    /**
     * Income categories the cashier's payments are filed under. They are
     * derived from an order transaction and can never be written by hand.
     *
     * @var list<string>
     */
    public const POS_INCOME = [
        'Pembayaran Sisa/Lunas (Order Selesai)',
        'Pembayaran Sebagian/Booking Order',
    ];

    /**
     * Cash taken from the drawer into a non-cash account. Listed on both sides
     * (its out and in entries share it) but only the Setor Tunai form writes
     * it, always as a pair, so it is kept off the hand-written entry form.
     */
    public const CASH_DEPOSIT = 'Setor Tunai';

    /**
     * @return list<string>
     */
    public static function income(): array
    {
        return Finance::incomeCategories();
    }

    /**
     * @return list<string>
     */
    public static function expense(): array
    {
        return Finance::expenseCategories();
    }

    /**
     * What a hand-written entry of this direction may be filed under.
     *
     * @return list<string>
     */
    public static function recordable(mixed $direction): array
    {
        if ($direction === 'out') {
            return array_values(array_diff(self::expense(), [self::CASH_DEPOSIT]));
        }

        return array_values(array_diff(self::income(), [...self::POS_INCOME, self::CASH_DEPOSIT]));
    }
}
