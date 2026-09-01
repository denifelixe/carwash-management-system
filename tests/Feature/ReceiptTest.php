<?php

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\Service;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;

test('a signed receipt link is publicly viewable without login', function () {
    $cashier = Admin::factory()->create(['name' => 'Deni Victoria']);
    $service = Service::factory()->create(['name' => 'Express Wash']);
    $order = Order::factory()->create([
        'number' => 'ORD-20260901-AIHOQS',
        'invoice_number' => 'ZW-20260901-AIHOQS',
        'status' => 'selesai',
        'subtotal' => 45000,
        'total' => 45000,
        'paid_amount' => 45000,
    ]);
    $order->serviceVariations()->attach($service->serviceVariations()->sole(), [
        'service_name' => 'Express Wash',
        'unit_price' => 45000,
        'quantity' => 1,
        'total_price' => 45000,
        'stamps' => 1,
    ]);
    $transaction = OrderTransaction::factory()->for($order)->create([
        'recorded_by_admin_id' => $cashier->id,
        'reference' => 'TRX-PLO-260901-ORD20260901AIHOQS-TRX1',
        'type' => 'Pembayaran Lunas',
        'amount' => 45000,
        'channel_breakdown' => [['label' => 'Tunai', 'amount' => 45000]],
        'paid_at' => '2026-09-01 19:02:00',
    ]);

    $response = $this->get(URL::signedRoute('receipts.show', $transaction));

    $response->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('receipts/Show')
            ->where('receipt.invoice', 'ZW-20260901-AIHOQS')
            ->where('receipt.reference', 'TRX-PLO-260901-ORD20260901AIHOQS-TRX1')
            ->where('receipt.cashier', 'Deni Victoria')
            ->where('receipt.lines.0.name', 'Express Wash')
            ->where('receipt.publicUrl', URL::signedRoute('receipts.show', $transaction))
            ->where('receipt.verificationQr', fn (string $qrCode): bool => str_starts_with($qrCode, 'data:image/svg+xml;base64,'))
            ->etc());
});

test('a receipt cannot be viewed through a tampered unsigned link', function () {
    $transaction = OrderTransaction::factory()->create();

    $this->get(route('receipts.show', $transaction))->assertForbidden();
});

test('the receipt offers print, PDF download, and link copying modes', function () {
    $receiptModule = file_get_contents(resource_path('js/lib/posReceipt.ts'));

    expect($receiptModule)
        ->toContain('data-receipt-download')
        ->toContain('data-receipt-copy')
        ->toContain('receiptWindow.print()')
        ->toContain('copyToClipboard(receiptWindow, receipt.publicUrl)')
        ->toContain('Pindai untuk memeriksa keabsahan struk');
});

/*
 * Downloading used to reopen the print prompt for the customer to pick "Save as
 * PDF" from, and then briefly wrote a screenshot of the slip. The file is drawn
 * from the receipt now, so its text has to stay text.
 */
test('downloading draws a real text PDF rather than a picture of the slip', function () {
    $pdfModule = file_get_contents(resource_path('js/lib/posReceiptPdf.ts'));

    expect($pdfModule)
        ->toContain('export async function downloadPosReceiptPdf(')
        ->toContain("import { jsPDF } from 'jspdf'")
        ->toContain('renderPosReceiptPdf(receipt, brand, logo).save(')
        ->toContain('return pdfFileName(`Struk-${number}`);')
        // One continuous page at the roll's printable width, like the printer.
        ->toContain("const ROLL: PageMetrics = {\n    width: 78,")
        ->toContain('format: [PAGE_WIDTH, height]')
        // The layout is measured on a throwaway page, then cut to that height.
        ->toContain('const height = layoutSlip(measured, receipt, brand, logo)')
        // Nothing is rasterised but the mark the fonts cannot draw.
        ->not->toContain('html2canvas');

    expect(file_get_contents(resource_path('js/lib/pdfDocument.ts')))
        ->toContain("this.doc.setFont(this.page.font, bold === true ? 'bold' : 'normal')")
        ->toContain('this.doc.text(');

    $receiptModule = file_get_contents(resource_path('js/lib/posReceipt.ts'));

    expect($receiptModule)
        // Fetched on the click so the POS bundle does not carry jsPDF.
        ->toContain("await import('@/lib/posReceiptPdf')")
        // The button is held while the file is built, then handed back.
        ->toContain('downloadButton.disabled = true')
        ->toContain("downloadLabel.textContent = 'Membuat…'");
});

test('the verification link is clickable in the downloaded PDF', function () {
    expect(file_get_contents(resource_path('js/lib/posReceiptPdf.ts')))
        // Placed by hand: textWithLink measures its box off the wrong baseline.
        ->toContain('slip.doc.link(left, slip.y, width, step, { url: receipt.publicUrl })')
        ->not->toContain('doc.textWithLink(');

    expect(file_get_contents(resource_path('js/lib/pdfDocument.ts')))
        ->toContain('export const LINK: [number, number, number] = [29, 78, 216]');
});

/*
 * Three copies of one address is two too many: the screen is already at the
 * verification URL, the PDF spells it out as a clickable link, and the QR is
 * left to the printout that cannot be tapped.
 */
test('the QR is printed only, the file carries the link, and the screen shows neither', function () {
    expect(file_get_contents(resource_path('js/lib/posReceiptPdf.ts')))
        ->toContain('Verifikasi struk:')
        ->not->toContain('verificationQr')
        ->not->toContain('Pindai untuk memeriksa keabsahan struk');

    $receiptModule = file_get_contents(resource_path('js/lib/posReceipt.ts'));

    expect($receiptModule)
        // Kept in the markup and hidden, so a plain Ctrl+P prints it too.
        ->toContain('.verification { border-top: 1px dashed #94a3b8; display: none;')
        ->toContain('.verification { display: block; }')
        // The link is gone from the slip's own page; the page is that link.
        ->not->toContain('verification-link')
        ->not->toContain('Verifikasi struk:')
        // With nothing left to stage, printing no longer swaps a body mode.
        ->not->toContain('outputMode');
});

test('copying the link confirms itself with a toast on the slip', function () {
    $receiptModule = file_get_contents(resource_path('js/lib/posReceipt.ts'));

    expect($receiptModule)
        ->toContain('const showToast = documentToaster(receiptWindow, TOAST_DURATION)')
        ->toContain("showToast('Link struk disalin')")
        ->toContain("showToast('Link gagal disalin', 'error')")
        // The download can fail too, and used to say nothing when it did.
        ->toContain("showToast('PDF gagal dibuat', 'error')")
        ->toContain('${toastMarkup()}')
        ->toContain('.toolbar, .toast { display: none; }');

    // The toast itself is shared with the recap, so it lives with the escaper.
    expect(file_get_contents(resource_path('js/lib/printDocument.ts')))
        ->toContain('export function documentToaster(')
        ->toContain('data-print-toast')
        // Top right of the document's own window, and never on the printout.
        ->toContain('right: 14px;')
        ->toContain('top: 14px;');
});

/*
 * The handler awaits the clipboard, and by the time it resumes the click has
 * finished dispatching, so reading the button off the event yielded null and
 * the slip silently confirmed nothing.
 */
test('the copy button is held rather than read back off the finished click event', function () {
    $receiptModule = file_get_contents(resource_path('js/lib/posReceipt.ts'));

    expect($receiptModule)
        ->toContain("const copyButton = receiptWindow.document.querySelector<HTMLButtonElement>(\n        '[data-receipt-copy]',\n    )")
        ->not->toContain('event.currentTarget as HTMLButtonElement');
});

/* An outlet served over plain http has no navigator.clipboard at all. */
test('copying falls back to a selection when the clipboard API is unavailable', function () {
    // One copy of it, shared with the recap, beside the one escaper.
    expect(file_get_contents(resource_path('js/lib/printDocument.ts')))
        ->toContain('export async function copyToClipboard(')
        ->toContain('documentWindow.navigator.clipboard.writeText(url)')
        ->toContain('documentWindow.isSecureContext && documentWindow.navigator.clipboard')
        ->toContain("documentWindow.document.execCommand('copy')");

    expect(file_get_contents(resource_path('js/lib/posReceipt.ts')))
        ->not->toContain('function copyReceiptLink(');
});
