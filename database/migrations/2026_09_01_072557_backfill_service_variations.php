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
            $groups = DB::table('service_groups')->orderBy('id')->get();
            $groupedServiceIds = [];

            foreach ($groups as $group) {
                $services = DB::table('services')->where('service_group_id', $group->id)
                    ->orderBy('sort_order')->orderBy('id')->get();

                if ($services->isEmpty()) {
                    continue;
                }

                $canonical = $services->firstWhere('name', $group->name.' - Small') ?? $services->first();
                $isSizeGroup = $services->every(fn (object $service): bool => in_array(
                    $this->variationValue($service->name, $group->name),
                    ['Small', 'Medium', 'Large', 'Extra Large'],
                    true,
                ));
                $attribute = $isSizeGroup ? 'Ukuran' : 'Pilihan';
                $values = $services->map(
                    fn (object $service): string => $this->variationValue($service->name, $group->name),
                )->unique()->values()->all();

                DB::table('services')->where('id', $canonical->id)->update([
                    'variations' => json_encode([$attribute => $values], JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);

                foreach ($services as $service) {
                    $groupedServiceIds[] = $service->id;
                    DB::table('service_variations')->insert([
                        'service_id' => $canonical->id,
                        'legacy_service_id' => $service->id,
                        'variations' => json_encode([
                            $attribute => $this->variationValue($service->name, $group->name),
                        ], JSON_UNESCAPED_UNICODE),
                        'price' => $service->price,
                        'is_active' => $service->is_active,
                        'created_at' => $service->created_at ?? now(),
                        'updated_at' => $service->updated_at ?? now(),
                    ]);
                }
            }

            DB::table('services')
                ->when($groupedServiceIds !== [], fn ($query) => $query->whereNotIn('id', $groupedServiceIds))
                ->orderBy('id')
                ->each(function (object $service): void {
                    DB::table('service_variations')->insert([
                        'service_id' => $service->id,
                        'legacy_service_id' => $service->id,
                        'variations' => null,
                        'price' => $service->price,
                        'is_active' => $service->is_active,
                        'created_at' => $service->created_at ?? now(),
                        'updated_at' => $service->updated_at ?? now(),
                    ]);
                });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('service_variations')->delete();
        DB::table('services')->update(['variations' => null]);
    }

    private function variationValue(string $serviceName, string $groupName): string
    {
        $prefix = $groupName.' - ';

        return str_starts_with($serviceName, $prefix)
            ? mb_substr($serviceName, mb_strlen($prefix))
            : $serviceName;
    }
};
