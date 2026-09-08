<?php

use App\Actions\Admin\CancelOrder;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderCancellation;
use App\Models\OrderCancellationPhoto;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

test('cancellation records a reason and optional private photos without changing payments', function (int $photoCount) {
    Storage::fake('local');
    $admin = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['status' => 'proses', 'paid_amount' => 20000]);
    $total = $order->total;
    $photos = collect(range(1, $photoCount))->take($photoCount)->map(fn (int $index) => UploadedFile::fake()->image("bukti-$index.jpg"))->all();

    $this->actingAs($admin, 'admin')->post(route('admin.orders.status.update', $order), [
        '_method' => 'patch', 'status' => 'batal', 'reason' => '  Pelanggan berhalangan  ', 'photos' => $photos,
    ])->assertSessionHasNoErrors();

    expect($order->refresh())->status->toBe('batal')->paid_amount->toBe(20000)->total->toBe($total);
    $cancellation = $order->cancellations()->sole();
    expect($cancellation)->reason->toBe('Pelanggan berhalangan')->previous_status->toBe('proses')
        ->cancelled_by_admin_id->toBe($admin->id)->cancelled_by_name->toBe($admin->name);
    expect($cancellation->photos)->toHaveCount($photoCount);
    foreach ($cancellation->photos as $photo) {
        expect($photo->disk)->toBe('local');
        Storage::disk('local')->assertExists($photo->path);
    }

    $this->get(route('admin.orders.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('orders.0.cancellations', 1)
        ->where('orders.0.cancellations.0.reason', 'Pelanggan berhalangan')
        ->has('orders.0.cancellations.0.photos', $photoCount));
})->with([0, 2]);

test('invalid cancellation inputs leave the order and storage unchanged', function (string $case, string $error) {
    Storage::fake('local');
    $order = Order::factory()->create(['status' => 'menunggu']);
    $data = ['status' => 'batal', 'reason' => 'Pelanggan batal'];
    $data = match ($case) {
        'empty' => [...$data, 'reason' => '   '],
        'long' => [...$data, 'reason' => str_repeat('a', 2001)],
        'pdf' => [...$data, 'photos' => [UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf')]],
        'large' => [...$data, 'photos' => [UploadedFile::fake()->image('bukti.jpg')->size(20481)]],
        'many' => [...$data, 'photos' => array_map(fn () => UploadedFile::fake()->image('bukti.png'), range(1, 11))],
    };
    $this->actingAs(Admin::factory()->create(['is_owner' => true]), 'admin')
        ->post(route('admin.orders.status.update', $order), ['_method' => 'patch', ...$data])
        ->assertSessionHasErrors($error);

    expect($order->refresh()->status)->toBe('menunggu');
    expect(OrderCancellation::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    ['empty', 'reason'], ['long', 'reason'], ['pdf', 'photos.0'], ['large', 'photos.0'], ['many', 'photos'],
]);

test('reopening and cancelling again preserves previous evidence and rejects duplicate cancellation', function () {
    Storage::fake('local');
    $order = Order::factory()->create(['status' => 'menunggu']);
    $this->actingAs(Admin::factory()->create(['is_owner' => true]), 'admin');
    $url = route('admin.orders.status.update', $order);
    $this->post($url, ['_method' => 'patch', 'status' => 'batal', 'reason' => 'Pertama', 'photos' => [UploadedFile::fake()->image('bukti.jpg')]])->assertSessionHasNoErrors();
    $photo = OrderCancellationPhoto::sole();
    $this->patch($url, ['status' => 'batal', 'reason' => 'Duplikat'])->assertSessionHasErrors('status');
    $this->patch($url, ['status' => 'menunggu'])->assertSessionHasNoErrors();
    $this->patch($url, ['status' => 'batal', 'reason' => 'Kedua'])->assertSessionHasNoErrors();

    expect($order->cancellations()->pluck('reason')->all())->toBe(['Kedua', 'Pertama']);
    Storage::disk('local')->assertExists($photo->path);
    $order->delete();
    expect(OrderCancellation::count())->toBe(2);
    expect(OrderCancellationPhoto::count())->toBe(1);
    Storage::disk('local')->assertExists($photo->path);
});

test('completed and expired orders cannot be cancelled', function (string $case) {
    $order = Order::factory()->create([
        'status' => $case === 'completed' ? 'selesai' : 'menunggu',
        'service_date' => $case === 'expired' ? today()->subDays(31) : today(),
    ]);
    $response = $this->actingAs(Admin::factory()->create(['is_owner' => true]), 'admin')
        ->patch(route('admin.orders.status.update', $order), ['status' => 'batal', 'reason' => 'Alasan']);
    if ($case === 'completed') {
        $response->assertSessionHasErrors('status');
    } else {
        $response->assertUnprocessable();
    }
    expect(OrderCancellation::count())->toBe(0);
    expect($order->refresh()->status)->not->toBe('batal');
})->with(['completed', 'expired']);

test('cancellation requires update permission', function () {
    $order = Order::factory()->create(['status' => 'menunggu']);
    $this->actingAs(Admin::factory()->create(['is_owner' => false, 'role_id' => null]), 'admin')
        ->patch(route('admin.orders.status.update', $order), ['status' => 'batal', 'reason' => 'Alasan'])->assertForbidden();
    expect($order->refresh()->status)->toBe('menunggu');
});

test('cancellation photo access requires permission and an existing order and file', function () {
    Storage::fake('local');
    $photo = OrderCancellationPhoto::factory()->create();
    Storage::disk('local')->put($photo->path, 'photo-content');
    $url = route('admin.orders.cancellation-photos.show', $photo);
    $this->get($url)->assertRedirect(route('admin.login'));
    $this->actingAs(Admin::factory()->create(['is_owner' => false, 'role_id' => null]), 'admin')->get($url)->assertForbidden();
    $this->actingAs(Admin::factory()->create(['is_owner' => true]), 'admin')->get($url)->assertSuccessful()->assertStreamedContent('photo-content');
    Storage::disk('local')->delete($photo->path);
    $this->get($url)->assertNotFound();
    Storage::disk('local')->put($photo->path, 'photo-content');
    $photo->cancellation->order->delete();
    $this->get($url)->assertNotFound();
});

test('failed photo storage rolls back cancellation and removes earlier uploads', function () {
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('putFileAs')->twice()->andReturn('order-cancellations/stored.jpg', false);
    $disk->shouldReceive('delete')->once()->with(['order-cancellations/stored.jpg'])->andReturn(true);
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);
    $order = Order::factory()->create(['status' => 'menunggu']);
    $this->actingAs(Admin::factory()->create(['is_owner' => true]), 'admin')
        ->post(route('admin.orders.status.update', $order), [
            '_method' => 'patch', 'status' => 'batal', 'reason' => 'Alasan',
            'photos' => [UploadedFile::fake()->image('satu.jpg'), UploadedFile::fake()->image('dua.jpg')],
        ])->assertSessionHasErrors('photos');
    expect($order->refresh()->status)->toBe('menunggu');
    expect(OrderCancellation::count())->toBe(0);
    expect(OrderCancellationPhoto::count())->toBe(0);
});

test('database failures remove uploaded photos and preserve the order', function () {
    Storage::fake('local');
    $order = Order::factory()->create(['status' => 'menunggu']);
    OrderCancellationPhoto::creating(function (): void {
        throw new RuntimeException('Metadata failed');
    });

    try {
        app(CancelOrder::class)->handle(
            $order, Admin::factory()->create(), 'Alasan', [UploadedFile::fake()->image('bukti.jpg')],
        );
        test()->fail('Expected metadata failure.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Metadata failed');
    } finally {
        OrderCancellationPhoto::flushEventListeners();
    }

    expect(Storage::disk('local')->allFiles())->toBe([]);
    expect($order->refresh()->status)->toBe('menunggu');
    expect(OrderCancellation::count())->toBe(0);
});

test('legacy cancelled orders have empty history and can be reopened', function () {
    $order = Order::factory()->create(['status' => 'batal']);
    $this->actingAs(Admin::factory()->create(['is_owner' => true]), 'admin')
        ->get(route('admin.orders.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('orders.0.cancellations', []));
    $this->patch(route('admin.orders.status.update', $order), ['status' => 'menunggu'])->assertSessionHasNoErrors();
    expect($order->refresh()->status)->toBe('menunggu');
    expect(OrderCancellation::count())->toBe(0);
});

test('row and detail cancellation wait for confirmation and preserve demo history', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const assert = require('node:assert/strict');
const { ref, computed, reactive } = require('vue');
const source = fs.readFileSync('resources/js/pages/admin/Orders.vue', 'utf8');
const ast = ts.createSourceFile('Orders.ts', source.split('<script setup lang="ts">')[1].split('</script>')[0], ts.ScriptTarget.Latest, true);
const names = ['changeRowStatus', 'saveStatus', 'setStatus', 'canEditStatus', 'openCancellation', 'closeCancellation', 'clearCancellationPhotos', 'addCancellationPhotos', 'removeCancellationPhoto', 'submitCancellation'];
const code = ts.transpile(ast.statements.filter(node => ts.isFunctionDeclaration(node) && names.includes(node.name.text)).map(node => node.getText(ast)).join('\n'), { target: ts.ScriptTarget.ES2020 });
const props = { mode: 'demo', capabilities: { update: true }, filters: { today: '2026-09-08', timezone: 'Asia/Jakarta' }, persona: { name: 'Petugas' } };
const cancellingOrder = ref(null), cancellationPreviews = ref([]), demoCancellationProcessing = ref(false), detailOrder = ref(null), statusDraft = ref('');
const cancellationForm = reactive({
    reason: '', photos: [], errors: {}, processing: false,
    setError(key, value) { this.errors[key] = value; },
    clearErrors() { this.errors = {}; },
    reset() { this.reason = ''; this.photos = []; },
    resetAndClearErrors() { this.reset(); this.clearErrors(); },
    post(url, options) { this.processing = true; this.lastRequest = { url, options }; },
});
const cancellationProcessing = computed(() => cancellationForm.processing || demoCancellationProcessing.value);
const updateOrderStatus = { url: id => `/orders/${id}/status` };
const revoked = [];
const URL = { createObjectURL: file => `blob:${file.name}`, revokeObjectURL: url => revoked.push(url) };
const readCancellationPhoto = async photo => `data:image/jpeg;base64,${photo.name}`;
eval(code + `
(async () => {
    const order = { id: 1, status: 'menunggu', source: 'walk-in', cancellations: [] };
    const picker = { value: 'batal' };
    changeRowStatus(order, { target: picker });
    assert.equal(order.status, 'menunggu');
    assert.equal(picker.value, 'menunggu');
    assert.equal(cancellingOrder.value.id, 1);
    closeCancellation();
    assert.equal(cancellingOrder.value, null);
    assert.equal(order.cancellations.length, 0);
    detailOrder.value = order;
    statusDraft.value = 'batal';
    saveStatus();
    assert.equal(cancellingOrder.value.id, 1);
    assert.equal(statusDraft.value, 'menunggu');
    await submitCancellation();
    assert.ok(cancellationForm.errors.reason);
    assert.equal(order.status, 'menunggu');
    const image = { name: 'bukti.jpg', type: 'image/jpeg', size: 100 };
    addCancellationPhotos({ target: { files: [{ ...image, size: 20 * 1024 * 1024 + 1 }] } });
    assert.ok(cancellationForm.errors.photos);
    assert.equal(cancellationForm.photos.length, 0);
    addCancellationPhotos({ target: { files: [{ ...image, size: 20 * 1024 * 1024 }] } });
    assert.equal(cancellationForm.photos.length, 1);
    clearCancellationPhotos();
    revoked.length = 0;
    addCancellationPhotos({ target: { files: [image] } });
    assert.equal(cancellationPreviews.value.length, 1);
    removeCancellationPhoto(0);
    assert.deepEqual(revoked, ['blob:bukti.jpg']);
    assert.equal(cancellationForm.photos.length, 0);
    addCancellationPhotos({ target: { files: [{ ...image, type: 'application/pdf' }] } });
    assert.ok(cancellationForm.errors.photos);
    assert.equal(cancellationForm.photos.length, 0);
    addCancellationPhotos({ target: { files: Array(11).fill(image) } });
    assert.ok(cancellationForm.errors.photos);
    addCancellationPhotos({ target: { files: [image] } });
    cancellationForm.reason = '  Pelanggan batal  ';
    await submitCancellation();
    assert.equal(detailOrder.value.status, 'batal');
    assert.equal(detailOrder.value.cancellations[0].reason, 'Pelanggan batal');
    assert.equal(detailOrder.value.cancellations[0].photos[0].url, 'data:image/jpeg;base64,bukti.jpg');
    setStatus(detailOrder.value, 'proses');
    openCancellation(detailOrder.value);
    cancellationForm.reason = 'Batal lagi';
    await submitCancellation();
    assert.equal(detailOrder.value.cancellations.length, 2);
    assert.equal(detailOrder.value.cancellations[0].previousStatus, 'proses');
    props.mode = 'live';
    openCancellation({ id: 2, status: 'menunggu' });
    cancellationForm.reason = 'Alasan';
    await submitCancellation();
    assert.equal(cancellationForm.lastRequest.url, '/orders/2/status');
    assert.equal(cancellationForm.lastRequest.options.forceFormData, true);
    closeCancellation();
    assert.equal(cancellingOrder.value.id, 2);
    await submitCancellation();
    cancellationForm.processing = false;
    cancellationForm.setError('photos', 'Gagal upload');
    assert.equal(cancellingOrder.value.id, 2);
    cancellationForm.lastRequest.options.onSuccess();
    assert.equal(cancellingOrder.value, null);
})().catch(error => { console.error(error); process.exitCode = 1; });
`);
JS;

    $result = Process::path(base_path())->run(['node', '-e', $script]);
    expect($result->successful())->toBeTrue($result->errorOutput());
});
