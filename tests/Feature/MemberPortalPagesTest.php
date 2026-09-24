<?php

use App\Models\Admin;
use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Service;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.member_portal_enabled' => true]);
});

dataset('live member pages', [
    'dashboard' => ['member.dashboard', 'member/Dashboard', ['stampHistory', 'washHistory', 'rewards', 'promos']],
    'stamps' => ['member.stamps', 'member/Stamps', ['stampHistory', 'washHistory', 'rewards']],
    'services' => ['member.services', 'member/Services', ['services', 'categories']],
    'rewards' => ['member.rewards', 'member/Rewards', ['rewards', 'vouchers']],
    'profile' => ['member.profile', 'member/Profile', ['washHistory', 'vouchers']],
]);

test('guests are sent to the member login', function (string $routeName) {
    $this->get(route($routeName))
        ->assertRedirect(route('member.login'));
})->with([
    'dashboard' => 'member.dashboard',
    'stamps' => 'member.stamps',
    'rewards' => 'member.rewards',
]);

test('each live portal page shares the demo page with real props', function (string $routeName, string $component, array $props) {
    $member = Member::factory()->create();

    $this->actingAs($member, 'member')
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($component, $props, $member) {
            $page->component($component)
                ->where('mode', 'live')
                ->has('brand')
                ->where('member.id', $member->id)
                ->where('member.referralCode', null)
                ->where('notifications', []);

            foreach ($props as $prop) {
                $page->has($prop);
            }
        });
})->with('live member pages');

test('the portal shows the signed-in member their own wallet and visits only', function () {
    $member = Member::factory()->create();
    MemberVehicle::factory()->for($member)->create(['plate' => 'B1234CDE']);
    $settled = Order::factory()->for($member)->create(['status' => 'selesai', 'stamps_earned' => 9, 'total' => 85000]);
    Order::factory()->for($member)->create(['status' => 'proses', 'stamps_earned' => 2]);
    Order::factory()->for($member)->create(['status' => 'pelunasan', 'paid_amount' => 45000, 'stamps_earned' => 3]);
    Order::factory()->for($member)->create(['status' => 'batal', 'stamps_earned' => 4]);
    RewardRedemption::factory()->create(['member_id' => $member->id, 'order_id' => $settled->id, 'stamps' => 3]);

    $stranger = Member::factory()->create();
    Order::factory()->for($stranger)->create(['status' => 'selesai', 'stamps_earned' => 50]);

    $this->actingAs($member, 'member')
        ->get(route('member.stamps'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('member.stamps', 9)
            ->where('member.lifetimeStamps', 12)
            ->where('member.rewardsClaimed', 1)
            ->has('stampHistory', 3)
            ->has('washHistory', 3)
            ->where('washHistory.0.stamps', 3)
            ->where('washHistory.1.status', 'proses')
            ->where('washHistory.1.stamps', 0));
});

test('the portal lists only active rewards and services', function () {
    $member = Member::factory()->create();
    $reward = Reward::factory()->create();
    Reward::factory()->inactive()->create();
    $service = Service::factory()->create();
    Service::factory()->create(['is_active' => false]);

    $this->actingAs($member, 'member')
        ->get(route('member.rewards'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rewards', 1)
            ->where('rewards.0.id', $reward->id)
            ->missing('rewards.0.category')
            ->missing('categories'));

    $this->actingAs($member, 'member')
        ->get(route('member.services'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('services', 1)
            ->where('services.0.id', $service->id));
});

test('order history includes only the members order details and payment receipts', function () {
    $member = Member::factory()->create();
    $order = Order::factory()->for($member)->create([
        'status' => 'selesai',
        'subtotal' => 85000,
        'total' => 85000,
        'paid_amount' => 85000,
    ]);
    $transaction = OrderTransaction::factory()->for($order)->create([
        'amount' => 85000,
        'channel_breakdown' => [['label' => 'Tunai', 'amount' => 85000]],
    ]);
    $otherOrder = Order::factory()->for(Member::factory()->create())->create(['status' => 'selesai']);
    OrderTransaction::factory()->for($otherOrder)->create();

    $this->actingAs($member, 'member')
        ->get(route('member.stamps'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('washHistory', 1)
            ->where('washHistory.0.number', $order->number)
            ->where('washHistory.0.paidAmount', 85000)
            ->where('washHistory.0.transactions.0.reference', $transaction->reference)
            ->where('washHistory.0.transactions.0.amount', 85000)
            ->where('washHistory.0.transactions.0.receiptUrl', URL::signedRoute('receipts.show', $transaction)));

    $this->get(URL::signedRoute('receipts.show', $transaction))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('receipts/Show')
            ->where('receipt.reference', $transaction->reference));
});

test('the live portal exposes no mutating routes besides login and logout', function () {
    $mutating = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'member.'))
        ->reject(fn ($route): bool => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) === [])
        ->map(fn ($route): string => (string) $route->getName())
        ->values()
        ->all();

    expect($mutating)->toBe(['member.login.store', 'member.logout']);
});

test('an admin gives a member portal access and the member can sign in', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $member = Member::factory()->create(['email' => null, 'password' => null]);
    MemberVehicle::factory()->for($member)->create(['plate' => 'B1234CDE']);
    $payload = [
        'name' => $member->name,
        'phone' => $member->phone,
        'email' => 'budi@example.com',
        'password' => 'x',
        'password_confirmation' => 'x',
        'vehicles' => [[
            'id' => $member->vehicles()->value('id'),
            'name' => 'Toyota Avanza',
            'plate' => 'B1234CDE',
            'type' => 'Mobil',
        ]],
    ];

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.members.update', $member), $payload)
        ->assertSessionHasNoErrors();

    expect($member->refresh()->password)->not->toBeNull();

    $this->post(route('member.login.store'), [
        'email' => 'budi@example.com',
        'password' => 'x',
    ])->assertRedirect(route('member.dashboard'));

    $this->assertAuthenticatedAs($member, 'member');
});

test('saving a member without a password keeps the current login', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $member = Member::factory()->create(['email' => 'budi@example.com']);
    $hash = $member->password;
    $vehicle = MemberVehicle::factory()->for($member)->create(['plate' => 'B1234CDE']);
    $payload = [
        'name' => 'Budi Baru',
        'phone' => $member->phone,
        'email' => 'budi@example.com',
        'password' => '',
        'vehicles' => [['id' => $vehicle->id, 'name' => 'Avanza', 'plate' => 'B1234CDE', 'type' => 'Mobil']],
    ];

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.members.update', $member), $payload)
        ->assertSessionHasNoErrors();

    expect($member->refresh()->password)->toBe($hash);

    $this->actingAs($owner, 'admin')
        ->patchJson(route('admin.members.update', $member), [...$payload, 'email' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    $this->actingAs($owner, 'admin')
        ->patchJson(route('admin.members.update', $member), [...$payload, 'password' => 'x', 'password_confirmation' => 'y'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});
