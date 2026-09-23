<?php

use App\Http\Middleware\HandleInertiaRequests;
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
        ->assertInertiaFlash('toast.message', 'Pembayaran berhasil dicatat.')
        ->assertInertiaFlash('toast.sound', 'pembayaran-berhasil');
});

test('moving an order to settlement flashes a success toast', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['status' => 'proses']);

    $this->actingAs($owner, 'admin')
        ->from(route('admin.orders.index'))
        ->patch(route('admin.orders.status.update', $order), ['status' => 'pelunasan'])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Status order berhasil diperbarui.')
        ->assertInertiaFlash('toast.sound', 'status-order-telah-berhasil-diperbarui');
});

test('back-office successes show a silent toast', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $booking = Order::factory()->create(['source' => 'booking', 'status' => 'booking']);

    $this->actingAs($owner, 'admin')
        ->delete(route('admin.bookings.destroy', $booking))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Booking berhasil dihapus.')
        ->assertInertiaFlash('toast.sound', null);
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

test('every announced route has its voice clip on disk and in the player list', function () {
    $sounds = (new ReflectionClassConstant(HandleInertiaRequests::class, 'SOUND_ROUTES'))->getValue();
    $player = file_get_contents(resource_path('js/lib/notificationSound.ts'));

    expect($sounds)->toHaveKeys(['admin.orders.store', 'admin.orders.status.update', 'admin.pos.payments.store']);

    foreach ($sounds as $sound) {
        expect(public_path("notification-sounds/{$sound}.mp3"))->toBeFile()
            ->and($player)->toContain("'{$sound}'");
    }
});

test('flash toasts play their clip at full volume', function () {
    expect(file_get_contents(resource_path('js/lib/flashToast.ts')))
        ->toContain('if (data.sound && isNotificationSound(data.sound)) {')
        ->toContain('playNotificationSound(data.sound);')
        ->and(file_get_contents(resource_path('js/lib/notificationSound.ts')))
        ->toContain('`/notification-sounds/${name}.mp3`')
        ->toContain('playingClip.volume = 1;');
});
