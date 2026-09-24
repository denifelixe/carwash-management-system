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
            $service = DB::table('services')->where('name', 'Regular Wash')->lockForUpdate()->first();

            if ($service === null) {
                return;
            }

            $variations = DB::table('service_variations')->where('service_id', $service->id)
                ->orderBy('id')->lockForUpdate()->get();

            foreach (['Standard' => 'Regular Wash', 'Large' => 'Regular Wash (Large)'] as $size => $legacyName) {
                $selection = ['Ukuran' => $size];
                $variationIds = $variations->filter(
                    fn (object $variation): bool => json_decode($variation->variations ?? 'null', true, flags: JSON_THROW_ON_ERROR) === $selection,
                )->pluck('id')->all();

                DB::table('order_services')
                    ->whereIn('service_variation_id', $variationIds)
                    ->where('service_name', $legacyName)
                    ->whereNull('variations')
                    ->update([
                        'service_name' => 'Regular Wash',
                        'variations' => json_encode($selection, JSON_THROW_ON_ERROR),
                    ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Normalisasi nama layanan pada order bersifat satu arah; gunakan migrasi koreksi untuk membatalkannya.');
    }
};
