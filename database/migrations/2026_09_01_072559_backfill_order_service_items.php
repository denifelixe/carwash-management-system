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
        DB::table('order_services')
            ->join('services', 'services.id', '=', 'order_services.service_id')
            ->leftJoin('service_groups', 'service_groups.id', '=', 'services.service_group_id')
            ->join('service_variations', 'service_variations.legacy_service_id', '=', 'services.id')
            ->select([
                'order_services.order_id',
                'service_variations.id as service_variation_id',
                DB::raw('COALESCE(service_groups.name, order_services.service_name) as service_name'),
                'service_variations.variations',
                'order_services.unit_price',
                DB::raw('1 as quantity'),
                DB::raw('order_services.unit_price as total_price'),
                'order_services.stamps',
            ])
            ->orderBy('order_services.order_id')
            ->orderBy('service_variations.id')
            ->chunk(200, function ($rows): void {
                DB::table('order_service_items')->insert(
                    $rows->map(fn (object $row): array => (array) $row)->all(),
                );
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('order_service_items')->delete();
    }
};
