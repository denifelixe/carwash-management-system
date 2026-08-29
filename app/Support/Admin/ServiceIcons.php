<?php

namespace App\Support\Admin;

/**
 * The icons a service may use, shared by the picker on the master service page
 * and the validation that guards it.
 */
class ServiceIcons
{
    public const DEFAULT = '🚗';

    /** @var array<string, string> */
    private const ICONS = [
        '🚗' => 'Mobil',
        '🚙' => 'SUV',
        '🚐' => 'Minibus',
        '🏍️' => 'Motor',
        '🛵' => 'Skuter',
        '🚿' => 'Cuci cepat',
        '🧼' => 'Sabun',
        '🫧' => 'Busa',
        '❄️' => 'Snow wash',
        '💧' => 'Air',
        '✨' => 'Wax',
        '💎' => 'Poles',
        '🛡️' => 'Coating',
        '🧽' => 'Interior',
        '🪑' => 'Jok & karpet',
        '🧴' => 'Cairan',
        '🔧' => 'Mesin',
        '🛞' => 'Ban',
        '⚫' => 'Semir ban',
        '💨' => 'Parfum & kaca',
        '⭐' => 'Paket spesial',
    ];

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::ICONS as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_keys(self::ICONS);
    }
}
