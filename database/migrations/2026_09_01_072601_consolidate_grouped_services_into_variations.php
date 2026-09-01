<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $groups = DB::table('service_groups')->orderBy('id')->get();

            foreach ($groups as $group) {
                $services = DB::table('services')->where('service_group_id', $group->id)
                    ->orderBy('sort_order')->orderBy('id')->get();

                if ($services->isEmpty()) {
                    continue;
                }

                $canonical = $services->firstWhere('name', $group->name.' - Small') ?? $services->first();
                $description = preg_replace(
                    '/\s+Ukuran\s+(small|medium|large|extra large)\.$/iu',
                    '',
                    (string) ($canonical->description ?? ''),
                );

                DB::table('services')->where('service_group_id', $group->id)
                    ->where('id', '!=', $canonical->id)->delete();

                DB::table('services')->where('id', $canonical->id)->update([
                    'name' => $group->name,
                    'description' => Str::of($description)->trim()->value() ?: null,
                    'sort_order' => (int) $services->min('sort_order'),
                    'is_active' => $services->contains(fn (object $service): bool => (bool) $service->is_active),
                    'is_popular' => $services->contains(fn (object $service): bool => (bool) $service->is_popular),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Konsolidasi service group menjadi parent variation bersifat satu arah.');
    }
};
