<?php

namespace App\Support\Admin;

/**
 * The role-related icons available to the admin role form and its validation.
 */
class RoleIcons
{
    public const DEFAULT = '🛡️';

    /** @var array<string, string> */
    private const ICONS = [
        '🛡️' => 'Staf',
        '🧑‍💼' => 'Manager',
        '👔' => 'Supervisor',
        '🎧' => 'Customer service',
        '💳' => 'Kasir',
        '🧾' => 'Finance',
        '📊' => 'Analis',
        '📋' => 'Administrator',
        '🔧' => 'Teknisi',
        '🚗' => 'Operator',
        '🧽' => 'Petugas cuci',
        '📦' => 'Inventory',
        '📣' => 'Marketing',
        '🏆' => 'Leader',
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
