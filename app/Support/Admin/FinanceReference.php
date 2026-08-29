<?php

namespace App\Support\Admin;

use Illuminate\Support\Str;

/**
 * The one transaction reference format the finance ledger uses, so a demo row
 * and a live row are read the same way: TRX-{category code}-{YYMMDD}-{id}.
 */
class FinanceReference
{
    public static function make(string $category, string $date, string|int $identifier): string
    {
        $categoryWords = preg_split(
            '/[^A-Z0-9]+/',
            Str::upper($category),
            flags: PREG_SPLIT_NO_EMPTY,
        );
        $categoryCode = implode('', array_map(
            fn (string $word): string => Str::substr($word, 0, 1),
            $categoryWords ?: [],
        ));
        $dateCode = Str::of($date)->remove('-')->substr(2);
        $stableIdentifier = is_int($identifier)
            ? str_pad((string) $identifier, 4, '0', STR_PAD_LEFT)
            : Str::of($identifier)->upper()->replaceMatches('/[^A-Z0-9]+/', '');

        return "TRX-{$categoryCode}-{$dateCode}-{$stableIdentifier}";
    }
}
