<?php

namespace App\Support\Admin;

class AdminModuleActions
{
    public const EDIT_CASH_ENTRY_BACKDATE = 'edit_cash_entry_backdate';

    public const VIEW_NON_CASH_BALANCE = 'view_non_cash_balance';

    /**
     * @return array<string, list<array{key: string, label: string}>>
     */
    public static function definitions(): array
    {
        return [
            'finance' => [
                [
                    'key' => self::VIEW_NON_CASH_BALANCE,
                    'label' => 'Lihat akumulasi saldo non-tunai',
                ],
                [
                    'key' => self::EDIT_CASH_ENTRY_BACKDATE,
                    'label' => 'Ubah tanggal cash entry (backdate)',
                ],
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function for(string $moduleKey): array
    {
        return self::definitions()[$moduleKey] ?? [];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return collect(self::definitions())
            ->flatten(1)
            ->pluck('key')
            ->values()
            ->all();
    }
}
