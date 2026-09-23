<?php

use App\Models\Admin;
use App\Models\Order;

test('a recorded payment flashes a success toast', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['status' => 'pelunasan', 'total' => 50000, 'paid_amount' => 0]);

    $this->actingAs($owner, 'admin')
        ->from(route('admin.pos.index'))
        ->post(route('admin.pos.payments.store', $order), [
            'intent' => 'settlement',
            'discount' => 0,
            'amount' => 50000,
            'channels' => [['method' => 'Tunai', 'amount' => 50000, 'provider' => '', 'reference' => '']],
        ])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Pembayaran berhasil dicatat.');
});

test('moving an order to settlement flashes a success toast', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['status' => 'proses']);

    $this->actingAs($owner, 'admin')
        ->from(route('admin.orders.index'))
        ->patch(route('admin.orders.status.update', $order), ['status' => 'pelunasan'])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Status order berhasil diperbarui.');
});

test('a failed request flashes no success toast', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['status' => 'pelunasan', 'total' => 50000, 'paid_amount' => 0]);

    $this->actingAs($owner, 'admin')
        ->from(route('admin.pos.index'))
        ->post(route('admin.pos.payments.store', $order), ['intent' => 'settlement', 'amount' => 0, 'channels' => []])
        ->assertSessionHasErrors()
        ->assertInertiaFlashMissing('toast');
});
