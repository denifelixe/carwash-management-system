<?php

use App\Actions\Admin\DeleteOrderWithEvidence;
use App\Actions\Admin\RecalculateDailyBalances;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderCancellation;
use App\Models\OrderDeletion;
use App\Models\OrderDeletionAttachment;
use App\Models\OrderTransaction;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;

test('order deletion records its reason and optional private photos with the original status', function (int $photoCount, string $source) {
    Storage::fake('local');
    $admin = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['status' => 'selesai', 'source' => $source, 'paid_amount' => 20000]);
    $payment = OrderTransaction::factory()->withDailyBalance()->create([
        'order_id' => $order->id, 'amount' => 20000,
        'channel_breakdown' => [['label' => 'Tunai', 'amount' => 20000]],
    ]);
    $photos = collect(range(1, 10))->take($photoCount)
        ->map(fn (int $index) => UploadedFile::fake()->image("bukti-$index.jpg"))->all();
    $this->freezeTime();

    $this->actingAs($admin, 'admin')->post(route('admin.orders.destroy', $order), [
        '_method' => 'DELETE', 'reason' => '  Order duplikat  ', 'photos' => $photos,
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.orders.index', ['date' => $order->service_date->toDateString()]));

    $this->assertSoftDeleted($order);
    $this->assertSoftDeleted($payment);
    $this->assertDatabaseCount('daily_balance', 0);
    $deletion = $order->deletions()->sole();
    expect($deletion)->reason->toBe('Order duplikat')->previous_status->toBe('selesai')
        ->deleted_by_admin_id->toBe($admin->id)->deleted_by_name->toBe($admin->name);
    expect($deletion->deleted_at->toDateTimeString())->toBe(now()->toDateTimeString());
    expect($deletion->order->trashed())->toBeTrue();
    expect($deletion->deletedBy->is($admin))->toBeTrue();
    expect($deletion->attachments)->toHaveCount($photoCount);
    foreach ($deletion->attachments as $index => $attachment) {
        expect($attachment->disk)->toBe('local');
        expect($attachment->original_name)->toBe($photos[$index]->getClientOriginalName());
        expect($attachment->size)->toBe($photos[$index]->getSize());
        expect($attachment->deletion->is($deletion))->toBeTrue();
        Storage::disk('local')->assertExists($attachment->path);
    }
    expect(OrderCancellation::count())->toBe(0);

    $this->delete(route('admin.orders.destroy', $order), ['reason' => 'Ulang'])->assertNotFound();
    expect(OrderDeletion::count())->toBe(1);
})->with([0, 2, 10])->with(['walk-in', 'booking']);

test('invalid deletion evidence leaves orders payments and storage untouched', function (string $case, string $error) {
    Storage::fake('local');
    $order = Order::factory()->create();
    $payment = OrderTransaction::factory()->for($order)->withDailyBalance()->create();
    $balances = DB::table('daily_balance')->get()->toArray();
    $data = ['reason' => 'Duplikat'];
    $data = match ($case) {
        'missing' => [],
        'blank' => ['reason' => '   '],
        'long' => ['reason' => str_repeat('a', 2001)],
        'array' => ['reason' => ['invalid']],
        'pdf' => [...$data, 'photos' => [UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf')]],
        'fake-image' => [...$data, 'photos' => [UploadedFile::fake()->create('bukti.jpg', 10, 'text/plain')]],
        'large' => [...$data, 'photos' => [UploadedFile::fake()->image('bukti.jpg')->size(20481)]],
        'many' => [...$data, 'photos' => array_map(fn () => UploadedFile::fake()->image('bukti.png'), range(1, 11))],
    };
    $this->actingAs(Admin::factory()->create(['is_owner' => true]), 'admin')
        ->post(route('admin.orders.destroy', $order), ['_method' => 'delete', ...$data])
        ->assertSessionHasErrors($error);

    $this->assertNotSoftDeleted($order);
    $this->assertNotSoftDeleted($payment);
    expect(DB::table('daily_balance')->get()->toArray())->toEqual($balances);
    expect(OrderDeletion::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    ['missing', 'reason'], ['blank', 'reason'], ['long', 'reason'], ['array', 'reason'],
    ['pdf', 'photos.0'], ['fake-image', 'photos.0'], ['large', 'photos.0'], ['many', 'photos'],
]);

test('deletion evidence accepts maximum reason and photo sizes', function () {
    Storage::fake('local');
    $order = Order::factory()->create(['service_date' => today()->subDays(30)]);
    $this->actingAs(Admin::factory()->create(['is_owner' => true]), 'admin')
        ->post(route('admin.orders.destroy', $order), [
            '_method' => 'delete', 'reason' => str_repeat('a', 2000),
            'photos' => [UploadedFile::fake()->image('bukti.png')->size(20480)],
        ])->assertSessionHasNoErrors();
    $this->assertSoftDeleted($order);
    expect(OrderDeletionAttachment::count())->toBe(1);
});

test('deletion evidence respects permissions and operational dates before storing photos', function (string $case) {
    Storage::fake('local');
    $order = Order::factory()->create(['service_date' => $case === 'old-order' ? today()->subDays(31) : today()]);
    $payment = OrderTransaction::factory()->for($order)->create([
        'paid_at' => $case === 'old-payment' ? now()->subDays(31) : now(),
    ]);
    $response = $this->actingAs(Admin::factory()->create(['is_owner' => $case !== 'forbidden', 'role_id' => null]), 'admin')
        ->post(route('admin.orders.destroy', $order), [
            '_method' => 'delete', 'reason' => 'Duplikat', 'photos' => [UploadedFile::fake()->image('bukti.jpg')],
        ]);
    if ($case === 'forbidden') {
        $response->assertForbidden();
    } else {
        $response->assertUnprocessable();
    }
    $this->assertNotSoftDeleted($order);
    $this->assertNotSoftDeleted($payment);
    expect(OrderDeletion::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with(['forbidden', 'old-order', 'old-payment']);

test('failed deletion photo storage cleans previous uploads', function (bool $throws) {
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('putFileAs')->once()->ordered()->andReturn('order-deletions/stored.jpg');
    $failure = $disk->shouldReceive('putFileAs')->once()->ordered();
    if ($throws) {
        $failure->andThrow(new RuntimeException('Storage unavailable'));
    } else {
        $failure->andReturn(false);
    }
    $disk->shouldReceive('delete')->once()->with(['order-deletions/stored.jpg'])->andReturn(true);
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);
    $order = Order::factory()->create();
    $payment = OrderTransaction::factory()->for($order)->create();
    $this->actingAs(Admin::factory()->create(['is_owner' => true]), 'admin')
        ->post(route('admin.orders.destroy', $order), [
            '_method' => 'delete', 'reason' => 'Duplikat',
            'photos' => [UploadedFile::fake()->image('satu.jpg'), UploadedFile::fake()->image('dua.jpg')],
        ])->assertSessionHasErrors('photos');
    $this->assertNotSoftDeleted($order);
    $this->assertNotSoftDeleted($payment);
    expect(OrderDeletion::count())->toBe(0);
    expect(OrderDeletionAttachment::count())->toBe(0);
})->with([false, true]);

test('database failures roll back deletion evidence payments and balances and remove files', function (string $stage) {
    Storage::fake('local');
    $order = Order::factory()->create();
    $payment = OrderTransaction::factory()->for($order)->withDailyBalance()->create();
    $balances = DB::table('daily_balance')->get()->toArray();
    if ($stage === 'metadata') {
        OrderDeletionAttachment::creating(function (): void {
            throw new RuntimeException('Metadata failed');
        });
    } else {
        $this->mock(RecalculateDailyBalances::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->once()->andReturnUsing(function (): void {
                DB::table('daily_balance')->delete();
                throw new RuntimeException('Balance failed');
            });
        });
    }
    try {
        app(DeleteOrderWithEvidence::class)->handle($order, Admin::factory()->create(), 'Duplikat', [UploadedFile::fake()->image('bukti.jpg')]);
        test()->fail('Expected deletion failure.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe($stage === 'metadata' ? 'Metadata failed' : 'Balance failed');
    } finally {
        OrderDeletionAttachment::flushEventListeners();
    }
    $this->assertNotSoftDeleted($order);
    $this->assertNotSoftDeleted($payment);
    expect($order->refresh()->deleted_by_admin_id)->toBeNull();
    expect($payment->refresh()->deleted_by_admin_id)->toBeNull();
    expect(DB::table('daily_balance')->get()->toArray())->toEqual($balances);
    expect(OrderDeletion::count())->toBe(0);
    expect(OrderDeletionAttachment::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with(['metadata', 'balance']);

test('booking deletion still works without evidence', function () {
    $order = Order::factory()->create(['source' => 'booking', 'status' => 'booking']);
    $this->actingAs(Admin::factory()->create(['is_owner' => true]), 'admin')
        ->delete(route('admin.bookings.destroy', $order))->assertSessionHasNoErrors();
    $this->assertSoftDeleted($order);
    expect(OrderDeletion::count())->toBe(0);
});

test('delete popup validates evidence locks processing and resets after success or dismissal', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const assert = require('node:assert/strict');
const { ref, reactive } = require('vue');
const source = fs.readFileSync('resources/js/pages/admin/Orders.vue', 'utf8');
const ast = ts.createSourceFile('Orders.ts', source.split('<script setup lang="ts">')[1].split('</script>')[0], ts.ScriptTarget.Latest, true);
const names = ['openDeleteOrder', 'closeDeleteOrder', 'confirmDeleteOrder', 'clearDeletionPhotos', 'addDeletionPhotos', 'removeDeletionPhoto'];
const code = ts.transpile(ast.statements.filter(node => ts.isFunctionDeclaration(node) && names.includes(node.name.text)).map(node => node.getText(ast)).join('\n'), { target: ts.ScriptTarget.ES2020 });
const props = { mode: 'live', capabilities: { delete: true } };
const deletingOrder = ref(null), deletionPreviews = ref([]), selectedDeletionPhoto = ref(null), detailOrderId = ref(1);
let requests = 0;
const deleteForm = reactive({
    _method: 'delete', reason: '', photos: [], errors: {}, processing: false,
    setError(key, value) { this.errors[key] = value; },
    clearErrors() { this.errors = {}; },
    resetAndClearErrors() { this.reason = ''; this.photos = []; this.clearErrors(); },
    post(url, options) { requests++; this.processing = true; this.lastRequest = { url, options }; },
});
const destroyOrder = { url: id => `/orders/${id}` };
const revoked = [];
const URL = { createObjectURL: file => `blob:${file.name}`, revokeObjectURL: url => revoked.push(url) };
eval(code + `
    const order = { id: 1, isDeletable: true };
    props.mode = 'demo';
    openDeleteOrder(order);
    assert.equal(deletingOrder.value, null);
    props.mode = 'live';
    props.capabilities.delete = false;
    openDeleteOrder(order);
    assert.equal(deletingOrder.value, null);
    props.capabilities.delete = true;
    openDeleteOrder({ ...order, isDeletable: false });
    assert.equal(deletingOrder.value, null);
    openDeleteOrder(order);
    assert.equal(requests, 0);
    deleteForm.reason = '   ';
    confirmDeleteOrder();
    assert.ok(deleteForm.errors.reason);
    deleteForm.reason = 'x'.repeat(2001);
    confirmDeleteOrder();
    assert.ok(deleteForm.errors.reason);
    assert.equal(requests, 0);
    const photo = { name: 'bukti.jpg', type: 'image/jpeg', size: 100 };
    addDeletionPhotos({ target: { files: [{ ...photo, type: 'application/pdf' }] } });
    assert.ok(deleteForm.errors.photos);
    addDeletionPhotos({ target: { files: [{ ...photo, size: 20 * 1024 * 1024 + 1 }] } });
    assert.ok(deleteForm.errors.photos);
    addDeletionPhotos({ target: { files: Array(11).fill(photo) } });
    assert.ok(deleteForm.errors.photos);
    assert.equal(deleteForm.photos.length, 0);
    addDeletionPhotos({ target: { files: [photo] } });
    assert.equal(deletionPreviews.value.length, 1);
    removeDeletionPhoto(0);
    assert.deepEqual(revoked, ['blob:bukti.jpg']);
    assert.equal(deleteForm.photos.length, 0);
    addDeletionPhotos({ target: { files: [photo] } });
    deleteForm.reason = '  Salah input  ';
    confirmDeleteOrder();
    assert.equal(deleteForm.reason, 'Salah input');
    assert.equal(deleteForm._method, 'delete');
    assert.equal(deleteForm.lastRequest.url, '/orders/1');
    assert.equal(deleteForm.lastRequest.options.forceFormData, true);
    closeDeleteOrder();
    openDeleteOrder({ id: 2, isDeletable: true });
    removeDeletionPhoto(0);
    addDeletionPhotos({ target: { files: [photo] } });
    confirmDeleteOrder();
    assert.equal(requests, 1);
    assert.equal(deletingOrder.value.id, 1);
    assert.equal(deleteForm.photos.length, 1);
    deleteForm.processing = false;
    deleteForm.setError('photos.0', 'Upload gagal');
    assert.equal(deletingOrder.value.id, 1);
    assert.equal(deleteForm.reason, 'Salah input');
    assert.equal(deleteForm.photos.length, 1);
    confirmDeleteOrder();
    deleteForm.lastRequest.options.onSuccess();
    assert.equal(deletingOrder.value, null);
    assert.equal(detailOrderId.value, null);
    assert.equal(deleteForm.reason, '');
    assert.equal(deletionPreviews.value.length, 0);
    deleteForm.processing = false;
    openDeleteOrder(order);
    addDeletionPhotos({ target: { files: [photo] } });
    deleteForm.reason = 'Batal hapus';
    selectedDeletionPhoto.value = { url: 'blob:bukti.jpg', name: 'bukti.jpg' };
    closeDeleteOrder();
    assert.equal(deletingOrder.value, null);
    assert.equal(selectedDeletionPhoto.value, null);
    assert.equal(deleteForm.reason, '');
    assert.equal(deletionPreviews.value.length, 0);
    assert.equal(requests, 2);
`);
JS;

    $result = Process::path(base_path())->run(['node', '-e', $script]);
    expect($result->successful())->toBeTrue($result->errorOutput());
});
