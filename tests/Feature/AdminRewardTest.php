<?php

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\Member;
use App\Models\Order;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-24 10:00'));
});

/** @param array<string, bool> $abilities */
function rewardStaff(array $abilities): Admin
{
    $role = AdminRole::query()->create([
        'key' => 'reward_'.uniqid(),
        'name' => 'Reward Staff',
        'description' => 'Role uji akses reward.',
        'is_active' => true,
    ]);

    $role->modules()->attach(
        AdminModule::query()->where('key', 'rewards')->firstOrFail(),
        [
            'can_create' => $abilities['create'] ?? false,
            'can_read' => $abilities['read'] ?? false,
            'can_update' => $abilities['update'] ?? false,
            'can_delete' => $abilities['delete'] ?? false,
        ],
    );

    return Admin::factory()->create(['role_id' => $role->id]);
}

/** @return array<string, mixed> */
function rewardPayload(array $overrides = []): array
{
    return array_replace([
        'name' => '  Gratis   Cuci Mobil  ',
        'description' => ' Satu kali cuci reguler. ',
        'icon' => '🚗',
        'category' => 'Layanan',
        'required_stamps' => 10,
        'stock' => 25,
        'is_active' => true,
        'variation_discounts' => [],
    ], $overrides);
}

test('guests cannot open the reward module', function () {
    $this->get(route('admin.rewards.index'))
        ->assertRedirect(route('admin.login'));
});

test('staff without read access cannot open the reward module', function () {
    $this->actingAs(rewardStaff(['read' => false]), 'admin')
        ->get(route('admin.rewards.index'))
        ->assertForbidden();
});

test('an owner sees the live reward module and its sidebar entry', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create(['name' => 'Cuci Mobil Reguler']);
    $reward = Reward::factory()->create(['required_stamps' => 5]);
    $variation = $service->serviceVariations()->firstOrFail();
    $reward->serviceVariations()->attach($variation, ['quantity' => 1, 'discount_percent' => 100]);
    Reward::factory()->inactive()->create();
    $member = Member::factory()->create();
    Order::factory()->for($member)->create(['status' => 'selesai', 'stamps_earned' => 7]);
    RewardRedemption::factory()->create(['reward_id' => $reward->id, 'stamps' => 2]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.rewards.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/Rewards')
            ->where('mode', 'live')
            ->has('rewards', 2)
            ->where('rewards.0.id', $reward->id)
            ->where('rewards.0.applicableVariations', [['serviceVariationId' => $variation->id, 'quantity' => 1, 'discountPercent' => 100]])
            ->where('rewards.0.redeemed', 1)
            ->where('rewards.1.status', 'nonaktif')
            ->has('redemptions.data', 1)
            ->where('redemptions.data.0.stamps', 2)
            ->where('stats.total', 2)
            ->where('stats.active', 1)
            ->where('stats.redeemed', 1)
            ->where('stampBalances', [7])
            ->where('capabilities', ['create' => true, 'update' => true, 'delete' => true])
            ->where('modules.8.key', 'rewards')
            ->where('modules.8.enabled', true)
            ->where('modules.8.href', route('admin.rewards.index', absolute: false)));
});

test('a reward is created with variation discounts and normalised fields', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create();

    $this->actingAs($owner, 'admin')
        ->post(route('admin.rewards.store'), rewardPayload(['variation_discounts' => [[
            'service_variation_id' => $service->serviceVariations()->firstOrFail()->id,
            'quantity' => 2,
            'discount_percent' => 50,
        ]]]))
        ->assertRedirect(route('admin.rewards.index'))
        ->assertSessionHasNoErrors();

    $reward = Reward::query()->sole();

    expect($reward->name)->toBe('Gratis Cuci Mobil')
        ->and($reward->description)->toBe('Satu kali cuci reguler.')
        ->and($reward->required_stamps)->toBe(10)
        ->and($reward->serviceVariations->pluck('id')->all())->toBe([$service->serviceVariations()->firstOrFail()->id])
        ->and((int) $reward->serviceVariations->first()->pivot->quantity)->toBe(2)
        ->and((int) $reward->serviceVariations->first()->pivot->discount_percent)->toBe(50);
});

test('reward validation', function (array $overrides, string $field) {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->postJson(route('admin.rewards.store'), rewardPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing name' => [['name' => ' '], 'name'],
    'zero stamps' => [['required_stamps' => 0], 'required_stamps'],
    'negative stock' => [['stock' => -1], 'stock'],
    'unknown variation' => [['variation_discounts' => [['service_variation_id' => 999999, 'quantity' => 1, 'discount_percent' => 100]]], 'variation_discounts.0.service_variation_id'],
]);

test('a reward is edited, its variation discounts resynced, and deactivated', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    [$first, $second] = Service::factory()->count(2)->create();
    $reward = Reward::factory()->create();
    $reward->serviceVariations()->attach($first->serviceVariations()->firstOrFail(), ['quantity' => 1, 'discount_percent' => 100]);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.rewards.update', $reward), rewardPayload(['variation_discounts' => [[
            'service_variation_id' => $second->serviceVariations()->firstOrFail()->id,
            'quantity' => 3,
            'discount_percent' => 25,
        ]], 'stock' => 3]))
        ->assertSessionHasNoErrors();

    expect($reward->refresh()->stock)->toBe(3)
        ->and($reward->serviceVariations->pluck('id')->all())->toBe([$second->serviceVariations()->firstOrFail()->id])
        ->and((int) $reward->serviceVariations->first()->pivot->quantity)->toBe(3)
        ->and((int) $reward->serviceVariations->first()->pivot->discount_percent)->toBe(25);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.rewards.status.update', $reward), ['is_active' => false])
        ->assertSessionHasNoErrors();

    expect($reward->refresh()->is_active)->toBeFalse();
});

test('variation quantities percentages and duplicate selections are validated', function (array $rows, string $field) {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $variationId = Service::factory()->create()->serviceVariations()->firstOrFail()->id;
    $rows = array_map(fn (array $row): array => [
        'service_variation_id' => $variationId,
        ...$row,
    ], $rows);

    $this->actingAs($owner, 'admin')
        ->postJson(route('admin.rewards.store'), rewardPayload(['variation_discounts' => $rows]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'zero quantity' => [[['quantity' => 0, 'discount_percent' => 50]], 'variation_discounts.0.quantity'],
    'percentage above one hundred' => [[['quantity' => 1, 'discount_percent' => 101]], 'variation_discounts.0.discount_percent'],
    'duplicate variation' => [[['quantity' => 1, 'discount_percent' => 50], ['quantity' => 2, 'discount_percent' => 25]], 'variation_discounts.0.service_variation_id'],
]);

test('inactive variations remain editable on their reward but cannot be newly selected', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $variation = Service::factory()->create()->serviceVariations()->firstOrFail();
    $reward = Reward::factory()->create();
    $reward->serviceVariations()->attach($variation, ['quantity' => 1, 'discount_percent' => 100]);
    $variation->update(['is_active' => false]);
    $selection = [['service_variation_id' => $variation->id, 'quantity' => 2, 'discount_percent' => 30]];

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.rewards.update', $reward), rewardPayload(['variation_discounts' => $selection]))
        ->assertSessionHasNoErrors();

    $this->actingAs($owner, 'admin')
        ->postJson(route('admin.rewards.store'), rewardPayload(['variation_discounts' => $selection]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['variation_discounts.0.service_variation_id']);
});

test('a reward can only be deleted until it has been redeemed', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $unused = Reward::factory()->create();
    $redeemed = Reward::factory()->create();
    RewardRedemption::factory()->create(['reward_id' => $redeemed->id]);

    $this->actingAs($owner, 'admin')
        ->delete(route('admin.rewards.destroy', $unused))
        ->assertRedirect(route('admin.rewards.index'));

    $this->actingAs($owner, 'admin')
        ->deleteJson(route('admin.rewards.destroy', $redeemed))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reward']);

    expect(Reward::query()->pluck('id')->all())->toBe([$redeemed->id]);
});

test('reward writes follow the role permissions', function () {
    $reader = rewardStaff(['read' => true]);
    $reward = Reward::factory()->create();

    $this->actingAs($reader, 'admin')
        ->get(route('admin.rewards.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('capabilities', ['create' => false, 'update' => false, 'delete' => false]));

    $this->actingAs($reader, 'admin')
        ->post(route('admin.rewards.store'), rewardPayload())
        ->assertForbidden();
    $this->actingAs($reader, 'admin')
        ->patch(route('admin.rewards.update', $reward), rewardPayload())
        ->assertForbidden();
    $this->actingAs($reader, 'admin')
        ->patch(route('admin.rewards.status.update', $reward), ['is_active' => false])
        ->assertForbidden();
    $this->actingAs($reader, 'admin')
        ->delete(route('admin.rewards.destroy', $reward))
        ->assertForbidden();
});

test('the dashboard counts the stamps redeemed that day', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    RewardRedemption::factory()->create(['stamps' => 6]);
    RewardRedemption::factory()->create(['stamps' => 4, 'redeemed_at' => now()->subDay()]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stats.3.label', 'Stempel Ditukar')
            ->where('stats.3.value', '6')
            ->where('stats.3.caption', '1 reward diklaim')
            ->where('stats.3.trend', 'up'));
});
