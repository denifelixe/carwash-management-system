<?php

use App\Models\Reward;
use App\Models\Service;
use App\Models\ServiceVariation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('legacy service rewards are backfilled to every variation at one unit and full discount', function () {
    Schema::create('reward_service', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('reward_id');
        $table->foreignId('service_id');
    });

    $reward = Reward::factory()->create();
    $merchandise = Reward::factory()->create();
    $service = Service::factory()->create();
    $first = $service->serviceVariations()->firstOrFail();
    $second = ServiceVariation::factory()->for($service)->create(['is_active' => false]);
    DB::table('reward_service')->insert(['reward_id' => $reward->id, 'service_id' => $service->id]);

    $migration = require database_path('migrations/2026_09_24_161825_backfill_reward_service_variations.php');
    $migration->up();

    expect($reward->serviceVariations()->orderBy('service_variations.id')->get()->map(fn (ServiceVariation $variation): array => [
        'id' => $variation->id,
        'quantity' => (int) $variation->pivot->quantity,
        'discount_percent' => (int) $variation->pivot->discount_percent,
    ])->all())->toBe([
        ['id' => $first->id, 'quantity' => 1, 'discount_percent' => 100],
        ['id' => $second->id, 'quantity' => 1, 'discount_percent' => 100],
    ])->and($merchandise->serviceVariations()->count())->toBe(0);
});
