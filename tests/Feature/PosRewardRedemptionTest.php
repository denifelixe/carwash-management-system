<?php

use App\Actions\Admin\DeleteOrder;
use App\Actions\Admin\RecordOrderPayment;
use App\Models\Admin;
use App\Models\Member;
use App\Models\Order;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Service;
use App\Models\ServiceVariation;
use App\Support\Admin\MemberStamps;
use App\Support\Admin\RewardRedemptionRules;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A member holding $balance stamps, and one of their orders waiting at the
 * till, billed for $service at 45.000 and a 20.000 add-on.
 *
 * @return array{member: Member, order: Order, service: Service}
 */
function redemptionScene(int $balance = 10): array
{
    $member = Member::factory()->create();
    Order::factory()->for($member)->create(['status' => 'selesai', 'stamps_earned' => $balance]);

    $service = Service::factory()->create(['name' => 'Cuci Mobil Reguler']);
    $addOn = Service::factory()->create(['name' => 'Semir Ban']);
    $order = Order::factory()->for($member)->create([
        'status' => 'pelunasan',
        'subtotal' => 65000,
        'total' => 65000,
        'stamps_earned' => 1,
    ]);

    foreach ([[$service, 45000], [$addOn, 20000]] as [$billed, $price]) {
        $order->serviceVariations()->attach($billed->serviceVariations()->firstOrFail(), [
            'service_name' => $billed->name,
            'unit_price' => $price,
            'quantity' => 1,
            'total_price' => $price,
            'stamps' => 1,
        ]);
    }

    return ['member' => $member, 'order' => $order, 'service' => $service];
}

/** @return array<string, mixed> */
function redemptionPayment(?Reward $reward, int $amount, int $discount = 0): array
{
    return [
        'intent' => 'settlement',
        'discount' => $discount,
        'reward_id' => $reward?->id,
        'amount' => $amount,
        'channels' => $amount > 0
            ? [['method' => 'Tunai', 'amount' => $amount, 'provider' => '', 'reference' => '']]
            : [],
    ];
}

test('the cashier receives the active reward catalog', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $reward = Reward::factory()->create();
    Reward::factory()->inactive()->create();

    $this->actingAs($owner, 'admin')
        ->get(route('admin.pos.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rewards', 1)
            ->where('rewards.0.id', $reward->id));
});

test('redeeming a reward discounts a covered variation and spends the stamps', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    ['member' => $member, 'order' => $order, 'service' => $service] = redemptionScene(10);
    $reward = Reward::factory()->create(['name' => 'Gratis Cuci', 'required_stamps' => 8, 'stock' => 5]);
    $reward->serviceVariations()->attach($service->serviceVariations()->firstOrFail(), ['quantity' => 1, 'discount_percent' => 100]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.pos.payments.store', $order), redemptionPayment($reward, 15000, 5000))
        ->assertSessionHasNoErrors();

    $order->refresh();
    $redemption = RewardRedemption::query()->sole();

    expect($order->status)->toBe('selesai')
        ->and($order->discount)->toBe(50000)
        ->and($order->total)->toBe(15000)
        ->and($order->reward_name)->toBe('Gratis Cuci')
        ->and($redemption->discount)->toBe(45000)
        ->and($redemption->stamps)->toBe(8)
        ->and($redemption->redeemed_by_admin_id)->toBe($owner->id)
        ->and($reward->refresh()->stock)->toBe(4)
        /* 10 earned before + 1 from this order now that it is settled − 8 spent. */
        ->and(MemberStamps::balance($member))->toBe(3);
});

test('a bill cleared entirely by a reward is labelled as a reward payment', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    ['order' => $order, 'service' => $service] = redemptionScene();
    $order->update(['subtotal' => 45000, 'total' => 45000]);
    $order->serviceVariations()->wherePivot('unit_price', 20000)->detach();
    $reward = Reward::factory()->create(['required_stamps' => 5]);
    $reward->serviceVariations()->attach($service->serviceVariations()->firstOrFail(), ['quantity' => 1, 'discount_percent' => 100]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.pos.payments.store', $order), redemptionPayment($reward, 0))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe('selesai')
        ->and($order->total)->toBe(0)
        ->and($order->transactions()->sole()->channel_breakdown)->toBe([['label' => 'Reward', 'amount' => 0]]);
});

test('only the matching variation with the largest calculated discount is applied', function (bool $includeCoating, int $expectedDiscount) {
    $owner = Admin::factory()->create(['is_owner' => true]);
    ['order' => $order, 'service' => $wash] = redemptionScene(20);
    $coating = Service::factory()->create(['name' => 'Coating']);
    $coatingVariation = $coating->serviceVariations()->firstOrFail();

    if ($includeCoating) {
        $order->serviceVariations()->attach($coatingVariation, [
            'service_name' => $coating->name,
            'unit_price' => 100000,
            'quantity' => 1,
            'total_price' => 100000,
            'stamps' => 0,
        ]);
        $order->update(['subtotal' => 165000, 'total' => 165000]);
    }

    $reward = Reward::factory()->create(['required_stamps' => 5]);
    $reward->serviceVariations()->attach([
        $wash->serviceVariations()->firstOrFail()->id => ['quantity' => 1, 'discount_percent' => 100],
        $coatingVariation->id => ['quantity' => 1, 'discount_percent' => 50],
    ]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.pos.payments.store', $order), redemptionPayment($reward, $order->total - $expectedDiscount))
        ->assertSessionHasNoErrors();

    expect(RewardRedemption::query()->sole()->discount)->toBe($expectedDiscount)
        ->and($order->refresh()->status)->toBe('selesai');
})->with([[true, 50000], [false, 45000]]);

test('a reward for the large variation does not discount the standard variation', function () {
    ['order' => $order, 'service' => $wash] = redemptionScene();
    $wash->update(['variations' => ['Ukuran' => ['Standard', 'Large']]]);
    $wash->serviceVariations()->firstOrFail()->update(['variations' => ['Ukuran' => 'Standard']]);
    $large = ServiceVariation::factory()->for($wash)->create(['variations' => ['Ukuran' => 'Large']]);
    $reward = Reward::factory()->create(['required_stamps' => 5]);
    $reward->serviceVariations()->attach($large, ['quantity' => 1, 'discount_percent' => 100]);

    expect(RewardRedemptionRules::refusal($reward->load('serviceVariations'), $order->load('serviceVariations'), 10))
        ->toContain('tidak berlaku');

    $order->serviceVariations()->attach($large, [
        'service_name' => $wash->name,
        'unit_price' => 70000,
        'quantity' => 1,
        'total_price' => 70000,
        'stamps' => 1,
    ]);

    expect(RewardRedemptionRules::discountFor($reward, $order->load('serviceVariations')))->toBe(70000);
});

test('reward quantity caps the units discounted and only one variation wins a tie', function (int $orderedQuantity, int $rewardQuantity, int $expectedDiscount) {
    ['order' => $order, 'service' => $wash] = redemptionScene();
    $washVariation = $wash->serviceVariations()->firstOrFail();
    $order->serviceVariations()->updateExistingPivot($washVariation->id, [
        'quantity' => $orderedQuantity,
        'total_price' => 45000 * $orderedQuantity,
    ]);
    $reward = Reward::factory()->create();
    $reward->serviceVariations()->attach($washVariation, ['quantity' => $rewardQuantity, 'discount_percent' => 50]);

    expect(RewardRedemptionRules::discountFor(
        $reward->load('serviceVariations'),
        $order->load('serviceVariations'),
    ))->toBe($expectedDiscount);
})->with([[3, 2, 45000], [1, 3, 22500]]);

test('equal variation discounts are not combined', function () {
    ['order' => $order, 'service' => $wash] = redemptionScene();
    $addOnVariation = $order->serviceVariations->firstWhere('service_id', '!=', $wash->id);
    $order->serviceVariations()->updateExistingPivot($addOnVariation->id, ['unit_price' => 45000, 'total_price' => 45000]);
    $order->unsetRelation('serviceVariations');
    $reward = Reward::factory()->create();
    $reward->serviceVariations()->attach([
        $wash->serviceVariations()->firstOrFail()->id => ['quantity' => 1, 'discount_percent' => 100],
        $addOnVariation->id => ['quantity' => 1, 'discount_percent' => 100],
    ]);

    expect(RewardRedemptionRules::discountFor($reward->load('serviceVariations'), $order))->toBe(45000);
});

test('a previous payment prevents redeeming a reward on settlement', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    ['member' => $member, 'order' => $order, 'service' => $wash] = redemptionScene();
    $reward = Reward::factory()->create(['required_stamps' => 5, 'stock' => 3]);
    $reward->serviceVariations()->attach($wash->serviceVariations()->firstOrFail(), ['quantity' => 1, 'discount_percent' => 100]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.pos.payments.store', $order), [
            ...redemptionPayment(null, 50000),
            'intent' => 'partial',
        ])
        ->assertSessionHasNoErrors();

    $this->postJson(route('admin.pos.payments.store', $order), redemptionPayment($reward, 0))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reward_id' => 'Reward hanya dapat ditukar pada pembayaran pertama order.']);

    expect(fn () => app(RecordOrderPayment::class)->handle($order, $owner, [
        ...redemptionPayment($reward, 0),
        'transaction_shift_id' => null,
    ]))->toThrow(HttpException::class, 'Reward hanya dapat ditukar pada pembayaran pertama order.');

    expect($order->refresh()->paid_amount)->toBe(50000)
        ->and($order->total)->toBe(65000)
        ->and($order->transactions()->count())->toBe(1)
        ->and(RewardRedemption::query()->count())->toBe(0)
        ->and($reward->refresh()->stock)->toBe(3)
        ->and(MemberStamps::balance($member))->toBe(10);

    $this->post(route('admin.pos.payments.store', $order), redemptionPayment(null, 15000))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe('selesai')
        ->and($order->transactions()->count())->toBe(2)
        ->and(RewardRedemption::query()->count())->toBe(0);
});

test('a reward can be redeemed on the first partial payment and remains on the order', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    ['order' => $order, 'service' => $wash] = redemptionScene();
    $reward = Reward::factory()->create(['required_stamps' => 5, 'stock' => 3]);
    $reward->serviceVariations()->attach($wash->serviceVariations()->firstOrFail(), ['quantity' => 1, 'discount_percent' => 50]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.pos.payments.store', $order), [
            ...redemptionPayment($reward, 20000),
            'intent' => 'partial',
        ])
        ->assertSessionHasNoErrors();

    expect($order->refresh()->total)->toBe(42500)
        ->and($order->paid_amount)->toBe(20000)
        ->and($order->status)->toBe('pelunasan')
        ->and(RewardRedemption::query()->sole()->discount)->toBe(22500)
        ->and($reward->refresh()->stock)->toBe(2);

    $this->post(route('admin.pos.payments.store', $order), redemptionPayment(null, 22500))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe('selesai')
        ->and($order->total)->toBe(42500)
        ->and(RewardRedemption::query()->count())->toBe(1);
});

test('a recorded payment blocks a merchandise reward even when the stored paid amount is zero', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    ['order' => $order] = redemptionScene();
    $reward = Reward::factory()->create(['required_stamps' => 5, 'stock' => 3]);
    $order->transactions()->create([
        'reference' => 'TRX-PERTAMA',
        'type' => 'Pembayaran Sebagian',
        'amount' => 0,
        'channel_breakdown' => [['label' => 'Diskon', 'amount' => 0]],
        'paid_at' => now(),
    ]);

    $this->actingAs($owner, 'admin')
        ->postJson(route('admin.pos.payments.store', $order), redemptionPayment($reward, 65000))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reward_id' => 'Reward hanya dapat ditukar pada pembayaran pertama order.']);

    expect(RewardRedemption::query()->count())->toBe(0)
        ->and($reward->refresh()->stock)->toBe(3);
});

test('a reward becomes available after every payment transaction is deleted', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    ['order' => $order, 'service' => $wash] = redemptionScene();
    $reward = Reward::factory()->create(['required_stamps' => 5, 'stock' => 3]);
    $reward->serviceVariations()->attach($wash->serviceVariations()->firstOrFail(), ['quantity' => 1, 'discount_percent' => 100]);

    $this->actingAs($owner, 'admin');

    foreach ([20000, 10000] as $amount) {
        $this->post(route('admin.pos.payments.store', $order), [
            ...redemptionPayment(null, $amount),
            'intent' => 'partial',
        ])->assertSessionHasNoErrors();
    }

    $transactions = $order->transactions()->get();

    $this->delete(route('admin.finance.transactions.destroy', $transactions[0]))
        ->assertSessionHasNoErrors();

    $this->postJson(route('admin.pos.payments.store', $order), redemptionPayment($reward, 0))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reward_id' => 'Reward hanya dapat ditukar pada pembayaran pertama order.']);

    expect($order->refresh()->paid_amount)->toBe(10000)
        ->and($order->transactions()->count())->toBe(1);

    $this->delete(route('admin.finance.transactions.destroy', $transactions[1]))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->paid_amount)->toBe(0)
        ->and($order->transactions()->count())->toBe(0)
        ->and($order->transactions()->withTrashed()->count())->toBe(2);

    $this->post(route('admin.pos.payments.store', $order), redemptionPayment($reward, 20000))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->total)->toBe(20000)
        ->and($order->status)->toBe('selesai')
        ->and(RewardRedemption::query()->sole()->discount)->toBe(45000)
        ->and($reward->refresh()->stock)->toBe(2);
});

test('deleting the last payment voids its reward and restores the full bill before another reward is chosen', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    ['member' => $member, 'order' => $order, 'service' => $wash] = redemptionScene(10);
    $washReward = Reward::factory()->create(['required_stamps' => 5, 'stock' => 3]);
    $washReward->serviceVariations()->attach($wash->serviceVariations()->firstOrFail(), ['quantity' => 1, 'discount_percent' => 100]);
    $addOnVariation = $order->serviceVariations()->wherePivot('unit_price', 20000)->firstOrFail();
    $addOnReward = Reward::factory()->create(['required_stamps' => 4, 'stock' => 2]);
    $addOnReward->serviceVariations()->attach($addOnVariation, ['quantity' => 1, 'discount_percent' => 100]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.pos.payments.store', $order), redemptionPayment($washReward, 15000, 5000))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe('selesai')
        ->and($order->total)->toBe(15000)
        ->and($order->discount)->toBe(50000)
        ->and(MemberStamps::balance($member))->toBe(6);

    $this->delete(route('admin.finance.transactions.destroy', $order->transactions()->sole()))
        ->assertSessionHasNoErrors();

    $voided = RewardRedemption::withTrashed()->where('order_id', $order->id)->sole();

    expect($order->refresh()->status)->toBe('pelunasan')
        ->and($order->paid_amount)->toBe(0)
        ->and($order->total)->toBe(65000)
        ->and($order->discount)->toBe(0)
        ->and($order->reward_name)->toBeNull()
        ->and($order->transactions()->count())->toBe(0)
        ->and($order->rewardRedemption()->exists())->toBeFalse()
        ->and($voided->trashed())->toBeTrue()
        ->and($voided->active_slot)->toBeNull()
        ->and($washReward->refresh()->stock)->toBe(3)
        ->and(MemberStamps::balance($member))->toBe(10);

    $this->post(route('admin.pos.payments.store', $order), redemptionPayment($addOnReward, 45000))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe('selesai')
        ->and($order->total)->toBe(45000)
        ->and($order->reward_name)->toBe($addOnReward->name)
        ->and($order->rewardRedemption()->sole()->discount)->toBe(20000)
        ->and($order->rewardRedemption()->sole()->active_slot)->toBe(1)
        ->and(RewardRedemption::withTrashed()->where('order_id', $order->id)->count())->toBe(2)
        ->and($addOnReward->refresh()->stock)->toBe(1)
        ->and(MemberStamps::balance($member))->toBe(7);
});

test('a reward stays applied while another payment transaction remains', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    ['order' => $order, 'service' => $wash] = redemptionScene();
    $reward = Reward::factory()->create(['required_stamps' => 5, 'stock' => 2]);
    $reward->serviceVariations()->attach($wash->serviceVariations()->firstOrFail(), ['quantity' => 1, 'discount_percent' => 50]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.pos.payments.store', $order), [
            ...redemptionPayment($reward, 10000),
            'intent' => 'partial',
        ])->assertSessionHasNoErrors();
    $this->post(route('admin.pos.payments.store', $order), [
        ...redemptionPayment(null, 10000),
        'intent' => 'partial',
    ])->assertSessionHasNoErrors();

    $transactions = $order->transactions()->get();
    $this->delete(route('admin.finance.transactions.destroy', $transactions[0]))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->total)->toBe(42500)
        ->and($order->discount)->toBe(22500)
        ->and($order->rewardRedemption()->exists())->toBeTrue()
        ->and($reward->refresh()->stock)->toBe(1);

    $this->delete(route('admin.finance.transactions.destroy', $transactions[1]))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->total)->toBe(65000)
        ->and($order->reward_name)->toBeNull()
        ->and($order->rewardRedemption()->exists())->toBeFalse()
        ->and($reward->refresh()->stock)->toBe(2);
});

test('deleting a discounted payment without a reward restores the service total', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    ['order' => $order] = redemptionScene();

    $this->actingAs($owner, 'admin')
        ->post(route('admin.pos.payments.store', $order), redemptionPayment(null, 60000, 5000))
        ->assertSessionHasNoErrors();

    $this->delete(route('admin.finance.transactions.destroy', $order->transactions()->sole()))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->total)->toBe(65000)
        ->and($order->discount)->toBe(0)
        ->and($order->paid_amount)->toBe(0);
});

test('deleting the last payment for a merchandise reward returns its stock and stamps', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    ['member' => $member, 'order' => $order] = redemptionScene(10);
    $reward = Reward::factory()->create(['required_stamps' => 6, 'stock' => 2]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.pos.payments.store', $order), redemptionPayment($reward, 65000))
        ->assertSessionHasNoErrors();

    $this->delete(route('admin.finance.transactions.destroy', $order->transactions()->sole()))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->total)->toBe(65000)
        ->and($order->reward_name)->toBeNull()
        ->and($reward->refresh()->stock)->toBe(2)
        ->and(MemberStamps::balance($member))->toBe(10)
        ->and($order->rewardRedemption()->exists())->toBeFalse();
});

test('migration repairs an older reward left on an order after its payments were removed', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    ['member' => $member, 'order' => $order, 'service' => $wash] = redemptionScene(10);
    $reward = Reward::factory()->create(['required_stamps' => 5, 'stock' => 3]);
    $reward->serviceVariations()->attach($wash->serviceVariations()->firstOrFail(), ['quantity' => 1, 'discount_percent' => 100]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.pos.payments.store', $order), redemptionPayment($reward, 20000))
        ->assertSessionHasNoErrors();

    $order->transactions()->sole()->delete();
    $order->update(['paid_amount' => 0, 'status' => 'pelunasan', 'payment_method' => null]);
    $repair = require database_path('migrations/2026_09_24_195419_repair_rewards_left_on_orders_without_payments.php');
    $repair->up();

    expect($order->refresh()->total)->toBe(65000)
        ->and($order->discount)->toBe(0)
        ->and($order->reward_name)->toBeNull()
        ->and($order->rewardRedemption()->exists())->toBeFalse()
        ->and(RewardRedemption::withTrashed()->where('order_id', $order->id)->sole()->active_slot)->toBeNull()
        ->and($reward->refresh()->stock)->toBe(3)
        ->and(MemberStamps::balance($member))->toBe(10);

    $repair->up();

    expect($reward->refresh()->stock)->toBe(3);
});

test('a merchandise reward is redeemable on any member order and takes nothing off', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    ['member' => $member, 'order' => $order] = redemptionScene(10);
    $reward = Reward::factory()->create(['required_stamps' => 6]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.pos.payments.store', $order), redemptionPayment($reward, 65000))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->total)->toBe(65000)
        ->and(RewardRedemption::query()->sole()->discount)->toBe(0)
        ->and(MemberStamps::balance($member))->toBe(5);
});

test('a reward is refused when the order does not qualify', function (Closure $arrange, string $message) {
    $owner = Admin::factory()->create(['is_owner' => true]);
    ['order' => $order, 'service' => $service] = redemptionScene(5);
    $reward = Reward::factory()->create(['required_stamps' => 5, 'stock' => 3]);
    $reward->serviceVariations()->attach($service->serviceVariations()->firstOrFail(), ['quantity' => 1, 'discount_percent' => 100]);
    $arrange($reward, $order);

    $this->actingAs($owner, 'admin')
        ->postJson(route('admin.pos.payments.store', $order), redemptionPayment($reward, 20000))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reward_id' => $message]);

    expect(RewardRedemption::query()->count())->toBe($order->rewardRedemption()->withTrashed()->count())
        ->and($order->refresh()->status)->toBe('pelunasan');
})->with([
    'not enough stamps' => [fn (Reward $reward) => $reward->update(['required_stamps' => 6]), 'Stempel member belum cukup'],
    'inactive reward' => [fn (Reward $reward) => $reward->update(['is_active' => false]), 'nonaktif'],
    'out of stock' => [fn (Reward $reward) => $reward->update(['stock' => 0]), 'Stok reward'],
    'variation not in the order' => [fn (Reward $reward) => $reward->serviceVariations()->sync([Service::factory()->create()->serviceVariations()->firstOrFail()->id => ['quantity' => 1, 'discount_percent' => 100]]), 'tidak berlaku'],
    'walk-in order' => [fn (Reward $reward, Order $order) => $order->update(['member_id' => null]), 'hanya bisa ditukar oleh member'],
    'second reward on one order' => [fn (Reward $reward, Order $order) => RewardRedemption::factory()->create([
        'member_id' => $order->member_id,
        'order_id' => $order->id,
        'stamps' => 0,
    ]), 'sudah memakai reward'],
]);

test('stamps count only for completed or fully paid non-cancelled orders', function () {
    $member = Member::factory()->create();

    Order::factory()->for($member)->create(['status' => 'selesai', 'stamps_earned' => 2]);
    $fullyPaid = Order::factory()->for($member)->create(['status' => 'pelunasan', 'paid_amount' => 45000, 'stamps_earned' => 3]);
    Order::factory()->for($member)->create(['status' => 'booking', 'paid_amount' => 45000, 'stamps_earned' => 4]);
    Order::factory()->for($member)->create(['status' => 'menunggu', 'stamps_earned' => 5]);
    Order::factory()->for($member)->create(['status' => 'pelunasan', 'paid_amount' => 20000, 'stamps_earned' => 6]);
    Order::factory()->for($member)->create(['status' => 'batal', 'paid_amount' => 45000, 'stamps_earned' => 7]);

    expect(MemberStamps::balance($member))->toBe(9)
        ->and(MemberStamps::earned($member))->toBe(9)
        ->and(MemberStamps::earned(MemberStamps::withBalances(Member::query())->findOrFail($member->id)))->toBe(9)
        ->and(MemberStamps::circulating())->toBe(9)
        ->and(MemberStamps::history($member))->toHaveCount(3);

    $fullyPaid->update(['paid_amount' => 20000]);

    expect(MemberStamps::balance($member))->toBe(6)
        ->and(MemberStamps::history($member))->toHaveCount(2);
});

test('deleting an order gives its reward back', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    ['member' => $member, 'order' => $order, 'service' => $service] = redemptionScene(10);
    $reward = Reward::factory()->create(['required_stamps' => 8, 'stock' => 5]);
    $reward->serviceVariations()->attach($service->serviceVariations()->firstOrFail(), ['quantity' => 1, 'discount_percent' => 100]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.pos.payments.store', $order), redemptionPayment($reward, 20000))
        ->assertSessionHasNoErrors();

    expect(MemberStamps::balance($member))->toBe(3);

    app(DeleteOrder::class)->handle($order->refresh(), $owner);

    expect(RewardRedemption::query()->count())->toBe(0)
        ->and($reward->refresh()->stock)->toBe(5)
        ->and(MemberStamps::balance($member))->toBe(10);
});
