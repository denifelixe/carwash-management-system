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
            DB::table('services')
                ->where('category', 'Paket Premium Mobil')
                ->update(['category' => 'Paket Mobil']);

            $services = DB::table('services')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'category']);

            $categoriesBeforeAddOn = collect($this->categories())->reject(
                fn (string $category): bool => $category === 'Add-on',
            );
            $knownCategories = $categoriesBeforeAddOn->concat(['Add-on']);

            $orderedServices = $categoriesBeforeAddOn
                ->flatMap(fn (string $category) => $services->where('category', $category))
                ->concat($services->whereNotIn('category', $knownCategories))
                ->concat($services->where('category', 'Add-on'))
                ->values();

            foreach ($orderedServices as $index => $service) {
                DB::table('services')
                    ->where('id', $service->id)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The previous operator-defined order cannot be reconstructed safely.
    }

    /** @return list<string> */
    private function categories(): array
    {
        return [
            'Cuci Mobil',
            'Paket Mobil',
            'Cuci Motor',
            'Detailing Mobil',
            'Detailing Motor',
            'Coating Mobil',
            'Coating Motor',
            'Add-on',
        ];
    }
};
