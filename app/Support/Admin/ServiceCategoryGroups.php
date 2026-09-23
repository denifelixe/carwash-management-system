<?php

namespace App\Support\Admin;

/**
 * The top level of the service catalog: the order picker opens on these groups
 * (Cuci, Detailing, Coating, Paket, Add-on) before their categories.
 */
class ServiceCategoryGroups
{
    public const FALLBACK = 'Lainnya';

    /**
     * The group a category falls under when none was chosen: its first word,
     * which is how the catalog names things ("Coating Motor" → Coating).
     */
    public static function defaultFor(string $category): string
    {
        $firstWord = strtok(trim($category), ' ');

        return $firstWord === false ? self::FALLBACK : $firstWord;
    }
}
