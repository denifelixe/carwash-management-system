<?php

use App\Models\Admin;
use App\Models\AdminShift;
use App\Models\CashEntry;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Support\Admin\TransactionShiftResolver;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia;

test('login popup persists across visits until acknowledged', function (string $time, string $mode, bool $assigned, string $label) {
    $this->travelTo('2026-09-08 '.$time);
    $admin = Admin::factory()->create([
        'is_owner' => true, 'shift_mode' => $mode,
        'shift_id' => $assigned ? AdminShift::query()->where('key', 'morning')->value('id') : null,
    ]);
    $this->withSession(['url.intended' => route('admin.profile.edit')])
        ->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('admin.profile.edit'));
    foreach (['admin.profile.edit', 'admin.dashboard', 'admin.profile.edit'] as $route) {
        $this->get(route($route))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('loginShift.pending', true)->where('loginShift.requires_selection', false)
            ->where('loginShift.label', $label));
    }
    $this->post(route('admin.login-shift.confirm'))->assertSessionHasNoErrors();
    foreach (['admin.dashboard', 'admin.profile.edit'] as $route) {
        $this->get(route($route))->assertInertia(fn (AssertableInertia $page) => $page->where('loginShift.pending', false));
    }
    $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
    $this->assertGuest('admin');
    $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password']);
    $this->get(route('admin.dashboard'))->assertInertia(fn (AssertableInertia $page) => $page->where('loginShift.pending', true));
})->with([
    'one scheduled shift' => ['09:00:00', 'schedule', false, 'Shift Pagi'],
    'outside schedule' => ['04:00:00', 'schedule', false, 'Tanpa Shift'],
    'fixed shift' => ['04:00:00', 'fixed', true, 'Shift Pagi'],
    'fixed without assignment' => ['09:00:00', 'fixed', false, 'Tanpa Shift'],
]);

test('overlapping login choice is validated and locked for POS and Finance across midnight', function () {
    AdminShift::query()->update(['is_active' => false]);
    $night = AdminShift::query()->create(['key' => 'night', 'name' => 'Malam', 'starts_at' => '22:00', 'ends_at' => '06:00', 'is_active' => true]);
    $other = AdminShift::query()->create(['key' => 'late', 'name' => 'Larut', 'starts_at' => '23:00', 'ends_at' => '07:00', 'is_active' => true]);
    $this->travelTo('2026-09-08 23:30:00');
    $admin = Admin::factory()->create(['is_owner' => true, 'shift_mode' => 'schedule', 'shift_id' => null]);
    $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password']);
    $this->get(route('admin.dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('loginShift.requires_selection', true)->has('loginShift.shifts', 2));
    $this->post(route('admin.login-shift.confirm'))->assertSessionHasErrors('shift_id');
    $this->post(route('admin.login-shift.confirm'), ['shift_id' => 999999])->assertSessionHasErrors('shift_id');
    $this->post(route('admin.login-shift.confirm'), ['shift_id' => 'invalid'])->assertSessionHasErrors('shift_id');
    $order = Order::factory()->create(['status' => 'pelunasan', 'total' => 50000]);
    $payment = ['intent' => 'settlement', 'discount' => 0, 'amount' => 50000, 'channels' => [['method' => 'Tunai', 'amount' => 50000]], 'transaction_shift_id' => $other->id];
    $this->post(route('admin.pos.payments.store', $order), $payment)->assertSessionHasErrors('transaction_shift_id');
    expect(OrderTransaction::query()->count())->toBe(0);
    $this->post(route('admin.login-shift.confirm'), ['shift_id' => $night->id])->assertSessionHasNoErrors();
    $this->post(route('admin.login-shift.confirm'), ['shift_id' => $other->id])->assertSessionHasNoErrors();
    $this->travelTo('2026-09-09 09:00:00');
    $night->update(['name' => 'Nama baru', 'is_active' => false]);
    $this->post(route('admin.pos.payments.store', $order), $payment)->assertSessionHasNoErrors();
    $this->post(route('admin.finance.store'), [
        'direction' => 'in', 'category' => 'Penjualan Produk', 'description' => 'Parfum',
        'amount' => 50000, 'method' => 'Tunai', 'entry_date' => '2026-09-09', 'entry_time' => '09:00',
        'transaction_shift_id' => $other->id,
    ])->assertSessionHasNoErrors();
    expect(OrderTransaction::query()->sole()->shift_name)->toBe('Malam')
        ->and(CashEntry::query()->sole()->shift_name)->toBe('Malam');
    $this->get(route('admin.pos.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('transactionShift.label', 'Malam')->where('transactionShift.caption', 'Malam')
        ->where('loginShift.pending', false));
});

test('confirmation requires admin authentication and never reuses another admins snapshot', function () {
    $this->post(route('admin.login-shift.confirm'))->assertRedirect(route('admin.login'));
    $this->travelTo('2026-09-08 09:00:00');
    $first = Admin::factory()->create(['shift_mode' => 'schedule']);
    app(TransactionShiftResolver::class)->captureLogin($first);
    $second = Admin::factory()->create(['is_owner' => true, 'shift_mode' => 'schedule']);
    $this->travelTo('2026-09-08 18:00:00');
    $this->actingAs($second, 'admin')->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('loginShift.label', 'Shift Sore'));
    expect(session('transaction_shift.admin_id'))->toBe($second->id);
});

test('admin popup renders shift choices and only logs out after confirmation', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const assert = require('node:assert/strict');
const ts = require('typescript');
const { parse, compileScript } = require('@vue/compiler-sfc');
const vue = require('vue');
const logoutState = { adminLogoutRequested: vue.ref(false) };
logoutState.requestAdminLogout = () => { logoutState.adminLogoutRequested.value = true; };
const page = vue.reactive({ props: { auth: { admin: { id: 1 } }, mode: 'live', loginShift: {
    pending: true, requires_selection: true, label: 'Pilih shift login',
    shifts: [{ id: 1, name: 'Pagi', time: '08:00 - 15:00' }, { id: 2, name: 'Siang', time: '14:00 - 20:00' }],
} } });
const requests = [];
let form;
const inertia = {
    usePage: () => page,
    useForm: (data) => form = vue.reactive({ ...data, processing: false, errors: {},
        resetAndClearErrors() { this.shift_id = null; this.errors = {}; },
        post(url) { requests.push({ url, shift_id: this.shift_id }); },
    }),
    router: { post(url, data, options) { requests.push({ url, options }); }, flushAll() {} },
};
const { descriptor } = parse(fs.readFileSync('resources/js/components/admin/AdminSessionDialogs.vue', 'utf8'));
const compiled = compileScript(descriptor, { id: 'session-dialog-test', inlineTemplate: true });
const code = ts.transpileModule(compiled.content, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText;
const moduleObject = { exports: {} };
const requireMock = name => {
    if (name === 'vue') return vue;
    if (name === '@inertiajs/vue3') return inertia;
    if (name.endsWith('ModalDialog.vue')) return { default: { name: 'ModalDialog' } };
    if (name.endsWith('useAdminLogout')) return logoutState;
    if (name.endsWith('/login-shift')) return { confirm: { url: () => '/login-shift/confirm' } };
    if (name === '@/routes/admin') return { logout: { url: () => '/logout' } };
    throw new Error(name);
};
new Function('require', 'module', 'exports', code)(requireMock, moduleObject, moduleObject.exports);
// Rendering the actual compiled component keeps assertions tied to its event bindings.
const render = moduleObject.exports.default.setup({}, { expose() {} });
function nodes(node, result = []) {
    if (!node || typeof node !== 'object') return result;
    if (Array.isArray(node)) { node.forEach(child => nodes(child, result)); return result; }
    result.push(node);
    if (Array.isArray(node.children)) nodes(node.children, result);
    else if (node.children && typeof node.children === 'object') {
        for (const [key, slot] of Object.entries(node.children)) {
            if (key !== '_' && typeof slot === 'function') nodes(slot(), result);
        }
    }
    return result;
}
function all() { return nodes(render({}, [])); }
function text(node) {
    if (typeof node === 'string') return node;
    if (Array.isArray(node)) return node.map(text).join('');
    return node ? text(node.children) : '';
}
function button(label) { return all().find(node => node.type === 'button' && text(node).trim() === label); }
function modal(title) { return all().find(node => node.props?.title === title); }
const warning = console.warn;
console.warn = () => {};
(async () => {
    assert.equal(modal('Login berhasil').props.open, true);
    assert.equal(modal('Login berhasil').props.dismissible, false);
    assert.equal(button('Gunakan Shift').props.disabled, true);
    const radio = all().find(node => node.type === 'input' && node.props.value === 2);
    radio.props['onUpdate:modelValue'](2);
    assert.equal(button('Gunakan Shift').props.disabled, false);
    all().find(node => node.type === 'form').props.onSubmit({ preventDefault() {} });
    assert.deepEqual(requests.pop(), { url: '/login-shift/confirm', shift_id: 2 });
    form.errors.shift_id = 'Pilih shift yang valid';
    assert.ok(all().some(node => node.props?.role === 'alert' && text(node).includes('Pilih shift yang valid')));
    page.props.loginShift.pending = false;
    await vue.nextTick();
    assert.equal(modal('Login berhasil').props.open, false);
    logoutState.requestAdminLogout();
    assert.equal(requests.length, 0);
    assert.equal(modal('Yakin ingin keluar?').props.open, true);
    button('Batal').props.onClick();
    assert.equal(logoutState.adminLogoutRequested.value, false);
    assert.equal(requests.length, 0);
    logoutState.requestAdminLogout();
    button('Ya, Keluar').props.onClick();
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, '/logout');
    assert.equal(button('Keluar…').props.disabled, true);
    requests[0].options.onSuccess();
    requests[0].options.onFinish();
    assert.equal(logoutState.adminLogoutRequested.value, false);
    page.props.loginShift = { pending: true, requires_selection: false, label: 'Tanpa Shift', shifts: [] };
    assert.ok(all().some(node => node.type === 'p' && text(node) === 'Tanpa Shift'));
    assert.equal(button('Lanjutkan').props.disabled, false);
    page.props.mode = 'demo';
    assert.equal(all().some(node => node.props?.title === 'Login berhasil'), false);
    console.warn = warning;
})().catch(error => { console.error(error); process.exitCode = 1; });
JS;

    $result = Process::path(base_path())->timeout(30)->run(['node', '-e', $script]);
    expect($result->successful())->toBeTrue($result->errorOutput().$result->output());
});
