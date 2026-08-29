<?php

namespace App\Support\Admin;

/**
 * Sidebar groups shared by the live shell (AdminShell) and the demo shell
 * (Demo\AdminController), so both consoles nest the same modules.
 */
class ModuleGroups
{
    /**
     * Modules rendered as a child of an expandable sidebar group.
     *
     * @var array<string, string>
     */
    private const MODULE_GROUPS = [
        'master_services' => 'master',
        'master_work_shifts' => 'master',
        'master_timezone' => 'master',
    ];

    /** @var array<string, array{label: string, caption: string, icon: string}> */
    private const GROUPS = [
        'master' => ['label' => 'Master', 'caption' => 'Master data', 'icon' => 'master'],
    ];

    /**
     * Folds grouped modules into an expandable parent placed where the first
     * of its children sat. Modules without a group are returned untouched.
     *
     * @param  list<array<string, mixed>>  $modules
     * @return list<array<string, mixed>>
     */
    public static function fold(array $modules): array
    {
        /** @var list<array<string, mixed>|string> $slots */
        $slots = [];
        /** @var array<string, list<array<string, mixed>>> $children */
        $children = [];

        foreach ($modules as $module) {
            $groupKey = self::MODULE_GROUPS[$module['key']] ?? null;

            if ($groupKey === null) {
                $slots[] = $module;

                continue;
            }

            if (! array_key_exists($groupKey, $children)) {
                $children[$groupKey] = [];
                $slots[] = $groupKey;
            }

            $children[$groupKey][] = $module;
        }

        $folded = [];

        foreach ($slots as $slot) {
            if (! is_string($slot)) {
                $folded[] = $slot;

                continue;
            }

            $folded[] = [
                'key' => $slot,
                'label' => self::GROUPS[$slot]['label'],
                'caption' => self::GROUPS[$slot]['caption'],
                'icon' => self::GROUPS[$slot]['icon'],
                'href' => null,
                'enabled' => true,
                'active' => array_filter(
                    $children[$slot],
                    static fn (array $child): bool => (bool) $child['active'],
                ) !== [],
                'children' => $children[$slot],
            ];
        }

        return $folded;
    }
}
