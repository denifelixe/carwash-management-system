<?php

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderTransaction;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function notedPaymentPayload(array $overrides = []): array
{
    return [
        'intent' => 'settlement',
        'discount' => 0,
        'amount' => 50000,
        'channels' => [['method' => 'Tunai', 'amount' => 50000, 'provider' => '', 'reference' => '']],
        ...$overrides,
    ];
}

test('a payment keeps the cashier note and hands it to the slip', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['status' => 'pelunasan', 'total' => 50000, 'paid_amount' => 0]);

    $this->actingAs($owner, 'admin')
        ->from(route('admin.pos.index'))
        ->post(route('admin.pos.payments.store', $order), notedPaymentPayload([
            'note' => '  Garansi   coating 6 bulan  ',
        ]))
        ->assertSessionHasNoErrors();

    $transaction = OrderTransaction::query()->sole();

    expect($transaction->note)->toBe('Garansi coating 6 bulan');

    $this->get(route('admin.pos.index', ['date' => $order->service_date->toDateString()]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('dailyOrders.0.transactions.0.note', 'Garansi coating 6 bulan'));

    auth('admin')->logout();

    $this->get(URL::signedRoute('receipts.show', $transaction))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('receipt.note', 'Garansi coating 6 bulan'));
});

test('a payment without a note stores none and a note is capped at 255 characters', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['status' => 'pelunasan', 'total' => 50000, 'paid_amount' => 0]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.pos.payments.store', $order), notedPaymentPayload(['note' => str_repeat('a', 256)]))
        ->assertSessionHasErrors('note');

    $this->post(route('admin.pos.payments.store', $order), notedPaymentPayload(['note' => '   ']))
        ->assertSessionHasNoErrors();

    expect(OrderTransaction::query()->sole()->note)->toBeNull();

    $this->get(URL::signedRoute('receipts.show', OrderTransaction::query()->sole()))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('receipt.note', ''));
});

test('both slip layouts print the note under the status stamp', function () {
    $html = file_get_contents(resource_path('js/lib/posReceipt.ts'));
    $pdf = file_get_contents(resource_path('js/lib/posReceiptPdf.ts'));
    $pos = file_get_contents(resource_path('js/pages/admin/Pos.vue'));

    expect($html)
        ->toContain('<p class="transaction-note">${escapeHtml(receipt.note)}</p>')
        ->and(mb_strpos($html, "<p class=\"status\">\${receipt.isSettled ? 'LUNAS' : 'BELUM LUNAS'}</p>"))
        ->toBeLessThan(mb_strpos($html, '<p class="transaction-note">'))
        ->and(mb_strpos($html, '<p class="transaction-note">'))
        ->toBeLessThan(mb_strpos($html, '<p>Terima kasih atas kunjungan Anda.</p>'));

    expect(mb_strpos($pdf, "spacedOut(receipt.isSettled ? 'LUNAS' : 'BELUM LUNAS')"))
        ->toBeLessThan(mb_strpos($pdf, "slip.paragraph(receipt.note, 'center');"));

    expect($pos)
        ->toContain('v-model="paymentNote"')
        ->toContain('maxlength="255"')
        ->toContain('paymentForm.note = snapshot.note;')
        ->toContain("note: settlement?.note ?? '',");
});
