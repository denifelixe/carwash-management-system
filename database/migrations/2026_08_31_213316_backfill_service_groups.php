<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach ($this->groups() as $groupName => $serviceNames) {
                $groupId = DB::table('service_groups')->insertGetId([
                    'name' => $groupName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('services')
                    ->whereIn('name', $serviceNames)
                    ->update(['service_group_id' => $groupId]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $groupNames = array_keys($this->groups());
        $groupIds = DB::table('service_groups')->whereIn('name', $groupNames)->pluck('id');

        DB::table('services')->whereIn('service_group_id', $groupIds)->update(['service_group_id' => null]);
        DB::table('service_groups')->whereIn('id', $groupIds)->delete();
    }

    /** @return array<string, list<string>> */
    private function groups(): array
    {
        $sizes = ['Small', 'Medium', 'Large', 'Extra Large'];
        $groupNames = [
            'Coating Lite',
            'Coating Mobil',
            'Supreme Wash',
            'Interior Detailing',
            'Exterior Detailing',
            'Complete Detailing',
            'Express Polish',
            'Seat Remove Interior Detailing',
            'Coating Motor',
            'Complete Detailing Motor',
        ];

        return collect($groupNames)
            ->mapWithKeys(fn (string $groupName): array => [
                $groupName => collect($sizes)
                    ->map(fn (string $size): string => "$groupName - $size")
                    ->all(),
            ])
            ->all();
    }
};
