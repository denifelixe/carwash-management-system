<?php

use App\Models\Admin;
use App\Models\AdminShift;
use App\Models\CashEntry;
use App\Models\Member;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Support\Admin\TransactionShiftResolver;
use Illuminate\Auth\Events\Login;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    AdminShift::query()->where('key', 'morning')->update(['ends_at' => '15:00:00']);
    AdminShift::query()->where('key', 'evening')->update(['starts_at' => '15:00:00']);
});

test('scheduled transactions and the shell keep the login shift until another login', function (string $loginTime, ?string $expectedShift) {
    $this->travelTo('2026-09-01 '.$loginTime);
    $admin = Admin::factory()->create(['is_owner' => true, 'shift_mode' => 'schedule', 'shift_id' => null]);

    $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->travelTo('2026-09-01 15:15:00');
    $evening = AdminShift::query()->where('key', 'evening')->firstOrFail();
    $order = Order::factory()->create(['status' => 'pelunasan', 'total' => 50000]);

    $this->post(route('admin.pos.payments.store', $order), [
        'intent' => 'settlement',
        'discount' => 0,
        'amount' => 50000,
        'channels' => [['method' => 'Tunai', 'amount' => 50000]],
        'transaction_shift_id' => $evening->id,
    ])->assertSessionHasNoErrors();

    $this->post(route('admin.finance.store'), [
        'direction' => 'in',
        'category' => 'Penjualan Produk',
        'description' => 'Parfum mobil',
        'amount' => 50000,
        'method' => 'Tunai',
        'entry_date' => '2026-09-01',
        'entry_time' => '15:15',
    ])->assertSessionHasNoErrors();

    expect(OrderTransaction::query()->sole()->shift_name)->toBe($expectedShift)
        ->and(CashEntry::query()->sole()->shift_name)->toBe($expectedShift);

    $this->get(route('admin.pos.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('transactionShift.label', $expectedShift ?? 'Tanpa Shift')
            ->where('transactionShift.locked_at_login', true)
            ->has('transactionShift.shifts', $expectedShift === null ? 0 : 1));

    $this->post(route('admin.logout'))->assertRedirect();
    $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertSessionHasNoErrors();
    $this->get(route('admin.pos.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('transactionShift.label', 'Shift Sore'));

    expect(OrderTransaction::query()->sole()->shift_name)->toBe($expectedShift);
})->with([
    'morning overtime' => ['14:45:00', 'Shift Pagi'],
    'outside schedule remains unassigned' => ['07:00:00', null],
]);

test('remembered admin login captures a new shift without affecting member logins', function () {
    $this->travelTo('2026-09-01 14:45:00');
    $admin = Admin::factory()->create(['shift_mode' => 'schedule']);
    event(new Login('admin', $admin, true));

    $this->travelTo('2026-09-01 15:15:00');
    event(new Login('member', Member::factory()->create(), false));

    $resolver = app(TransactionShiftResolver::class);
    expect($resolver->resolve($admin, null, now())?->name)->toBe('Shift Pagi');
    expect($resolver->loginPresentation($admin)['pending'])->toBeTrue();
    $resolver->confirmLogin($admin, null);
    expect($resolver->loginPresentation($admin)['pending'])->toBeFalse();

    event(new Login('admin', $admin, true));
    expect($resolver->resolve($admin, null, now())?->name)->toBe('Shift Sore');
    expect($resolver->loginPresentation($admin)['pending'])->toBeTrue();
});
