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
            $services = DB::table('services')
                ->whereIn('name', ['Regular Wash', 'Regular Wash (Large)'])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('name');
            $regular = $services->get('Regular Wash');
            $large = $services->get('Regular Wash (Large)');

            if ($regular === null || $large === null) {
                return;
            }

            if ($regular->category !== $large->category
                || $regular->category_group !== $large->category_group
                || (int) $regular->stamps !== (int) $large->stamps) {
                throw new RuntimeException('Regular Wash tidak dapat digabung: kategori, grup, atau stempel berbeda.');
            }

            $variations = DB::table('service_variations')
                ->whereIn('service_id', [$regular->id, $large->id])
                ->orderBy('id')->lockForUpdate()->get();

            foreach ([$regular, $large] as $service) {
                $serviceVariations = $variations->where('service_id', $service->id);

                if ($service->variations !== null
                    || $serviceVariations->count() !== 1
                    || $serviceVariations->first()->variations !== null) {
                    throw new RuntimeException('Regular Wash tidak dapat digabung: struktur variasi bentrok atau bukan satu variasi bawaan.');
                }
            }

            if (DB::table('reward_service')->whereIn('service_id', [$regular->id, $large->id])
                ->lockForUpdate()->get()->isNotEmpty()) {
                throw new RuntimeException('Regular Wash tidak dapat digabung: layanan masih terhubung ke reward.');
            }

            $updatedAt = now();

            foreach (['Standard' => $regular, 'Large' => $large] as $size => $service) {
                $variation = $variations->firstWhere('service_id', $service->id);

                DB::table('service_variations')->where('id', $variation->id)->update([
                    'service_id' => $regular->id,
                    'variations' => json_encode(['Ukuran' => $size], JSON_THROW_ON_ERROR),
                    'is_active' => (bool) $service->is_active && (bool) $variation->is_active,
                    'updated_at' => $updatedAt,
                ]);
            }

            DB::table('services')->where('id', $regular->id)->update([
                'variations' => json_encode(['Ukuran' => ['Standard', 'Large']], JSON_THROW_ON_ERROR),
                'is_active' => (bool) $regular->is_active || (bool) $large->is_active,
                'updated_at' => $updatedAt,
            ]);

            DB::table('services')->where('id', $large->id)->delete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Penggabungan Regular Wash bersifat satu arah; gunakan migrasi koreksi untuk membatalkannya.');
    }
};
