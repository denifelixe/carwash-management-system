<?php

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Support\AppSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Cache::flush();
    config(['app.name' => 'ZenWash Auto Care']);
});

/**
 * @param  array<string, bool>  $abilities
 */
function receiptStaff(array $abilities): Admin
{
    $role = AdminRole::query()->create([
        'key' => 'receipt_'.uniqid(),
        'name' => 'Receipt Staff',
        'description' => 'Role uji akses pengaturan struk.',
        'is_active' => true,
    ]);

    $role->modules()->attach(
        AdminModule::query()->where('key', 'master_receipt')->firstOrFail(),
        [
            'can_create' => $abilities['create'] ?? false,
            'can_read' => $abilities['read'] ?? false,
            'can_update' => $abilities['update'] ?? false,
            'can_delete' => $abilities['delete'] ?? false,
        ],
    );

    return Admin::factory()->create(['role_id' => $role->id]);
}

test('guests cannot open the receipt module', function () {
    $this->get(route('admin.master.receipt.index'))
        ->assertRedirect(route('admin.login'));
});

test('an owner sees the receipt module in the master sidebar', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.master.receipt.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/master/Receipt')
                ->where('mode', 'live')
                ->where('settings.receiptBusinessName', 'ZenWash Auto Care')
                ->where('settings.receiptFooterNote', 'Struk ini adalah bukti pembayaran yang sah.')
                ->where('settings.receiptShowLogo', true)
                ->where('settings.receiptShowQr', false)
                ->where('settings.appPhotoUrl', null)
                ->where('settings.receiptPhotoUrl', null)
                ->where('settings.hasOwnReceiptPhoto', false)
                ->where('settings.receiptLogoWidth', 15)
                ->where('settings.receiptLogoWidthMin', 8)
                ->where('settings.receiptLogoWidthMax', 72)
                ->where('capabilities.update', true)
                ->where('modules.11.key', 'master')
                ->where('modules.11.active', true)
                ->where('modules.11.children.4.key', 'master_receipt')
                ->where('modules.11.children.4.label', 'Struk')
                ->where('modules.11.children.4.active', true)
                ->where(
                    'modules.11.children.4.href',
                    route('admin.master.receipt.index', absolute: false),
                ),
        );
});

test('receipt settings are saved and handed to the slip', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    AppSettings::put(AppSettings::APP_NAME, 'Kilap Auto Spa', $owner->id);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.receipt.update'), [
            'receipt_business_name' => '  CV   Kilap Mandiri  ',
            'receipt_footer_note' => 'Barang yang sudah dicuci tidak dapat ditukar.',
            'receipt_show_logo' => false,
            'receipt_show_qr' => true,
        ])
        ->assertRedirect(route('admin.master.receipt.index'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Pengaturan struk berhasil diperbarui.');

    expect(AppSettings::receiptBusinessName())->toBe('CV Kilap Mandiri')
        ->and(AppSettings::receiptFooterNote())->toBe('Barang yang sudah dicuci tidak dapat ditukar.')
        ->and(AppSettings::receiptShowsLogo())->toBeFalse()
        ->and(AppSettings::receiptShowsQr())->toBeTrue();

    $this->actingAs($owner, 'admin')
        ->get(route('admin.master.receipt.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                // The console keeps the app name; only the slip is renamed.
                ->where('brand.name', 'Kilap Auto Spa')
                ->where('brand.receipt.name', 'CV Kilap Mandiri')
                ->where('brand.receipt.footerNote', 'Barang yang sudah dicuci tidak dapat ditukar.')
                ->where('brand.receipt.showLogo', false)
                ->where('brand.receipt.showQr', true)
                ->where('settings.receiptBusinessName', 'CV Kilap Mandiri')
                ->where('settings.receiptShowLogo', false)
                ->where('settings.receiptShowQr', true),
        );
});

test('an unset receipt name follows the app name', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    AppSettings::put(AppSettings::APP_NAME, 'Kilap Auto Spa', $owner->id);

    expect(AppSettings::receiptBusinessName())->toBe('Kilap Auto Spa');
});

/* An outlet that wants no fine print saves it blank, and blank must stick. */
test('a blank footer note is kept rather than falling back to the default', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.receipt.update'), [
            'receipt_business_name' => 'Kilap Auto Spa',
            'receipt_footer_note' => '',
        ])
        ->assertSessionHasNoErrors();

    expect(AppSettings::receiptFooterNote())->toBe('');
});

test('receipt settings reject a blank name and an overlong footer note', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.receipt.update'), [
            'receipt_business_name' => '   ',
            'receipt_footer_note' => str_repeat('a', 121),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['receipt_business_name', 'receipt_footer_note']);
});

test('the slip takes its own logo, apart from the app photo', function () {
    Storage::fake('public');
    $owner = Admin::factory()->create(['is_owner' => true]);

    AppSettings::put(AppSettings::APP_PHOTO, 'app-branding/app-photo.png', $owner->id);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.receipt.update'), [
            'receipt_business_name' => 'Kilap Auto Spa',
            'receipt_footer_note' => '',
            'receipt_photo' => UploadedFile::fake()->image('receipt-logo.png', 400, 400),
        ])
        ->assertRedirect(route('admin.master.receipt.index'))
        ->assertSessionHasNoErrors();

    $receiptPhotoPath = AppSettings::get(AppSettings::RECEIPT_PHOTO);

    expect($receiptPhotoPath)->not->toBeNull()
        ->and($receiptPhotoPath)->not->toBe('app-branding/app-photo.png')
        ->and(AppSettings::hasOwnReceiptPhoto())->toBeTrue();
    Storage::disk('public')->assertExists($receiptPhotoPath);

    $receiptPhotoUrl = Storage::disk('public')->url($receiptPhotoPath);
    $appPhotoUrl = Storage::disk('public')->url('app-branding/app-photo.png');

    $this->actingAs($owner, 'admin')
        ->get(route('admin.master.receipt.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                // The console keeps the app photo; only the roll is re-marked.
                ->where('brand.photo', $appPhotoUrl)
                ->where('brand.receipt.photo', $receiptPhotoUrl)
                ->where('settings.appPhotoUrl', $appPhotoUrl)
                ->where('settings.receiptPhotoUrl', $receiptPhotoUrl)
                ->where('settings.hasOwnReceiptPhoto', true),
        );
});

/* Nothing on paper may change just because the setting now exists. */
test('a slip without its own logo borrows the app photo', function () {
    Storage::fake('public');
    $owner = Admin::factory()->create(['is_owner' => true]);

    AppSettings::put(AppSettings::APP_PHOTO, 'app-branding/app-photo.png', $owner->id);

    expect(AppSettings::hasOwnReceiptPhoto())->toBeFalse()
        ->and(AppSettings::receiptPhotoUrl())
        ->toBe(Storage::disk('public')->url('app-branding/app-photo.png'));
});

test('replacing the receipt logo deletes the previous file', function () {
    Storage::fake('public');
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')->post(route('admin.master.receipt.update'), [
        'receipt_business_name' => 'Kilap Auto Spa',
        'receipt_footer_note' => '',
        'receipt_photo' => UploadedFile::fake()->image('first.png'),
    ]);

    $oldPath = AppSettings::get(AppSettings::RECEIPT_PHOTO);

    $this->actingAs($owner, 'admin')->post(route('admin.master.receipt.update'), [
        'receipt_business_name' => 'Kilap Auto Spa',
        'receipt_footer_note' => '',
        'receipt_photo' => UploadedFile::fake()->image('second.png'),
    ]);

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists(AppSettings::get(AppSettings::RECEIPT_PHOTO));
});

test('removing the receipt logo hands the slip back to the app photo', function () {
    Storage::fake('public');
    $owner = Admin::factory()->create(['is_owner' => true]);
    $appPhotoPath = 'app-branding/app-photo.png';

    Storage::disk('public')->put($appPhotoPath, 'app-photo');
    AppSettings::put(AppSettings::APP_PHOTO, $appPhotoPath, $owner->id);

    $this->actingAs($owner, 'admin')->post(route('admin.master.receipt.update'), [
        'receipt_business_name' => 'Kilap Auto Spa',
        'receipt_footer_note' => '',
        'receipt_photo' => UploadedFile::fake()->image('receipt-logo.png'),
    ]);

    $receiptPhotoPath = AppSettings::get(AppSettings::RECEIPT_PHOTO);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.receipt.update'), [
            'receipt_business_name' => 'Kilap Auto Spa',
            'receipt_footer_note' => '',
            'remove_receipt_photo' => true,
        ])
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing($receiptPhotoPath);
    Storage::disk('public')->assertExists($appPhotoPath);

    expect(AppSettings::hasOwnReceiptPhoto())->toBeFalse()
        ->and(AppSettings::receiptPhotoUrl())
        ->toBe(Storage::disk('public')->url($appPhotoPath));
});

test('receipt settings reject a logo that is not an image', function () {
    Storage::fake('public');
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.receipt.update'), [
            'receipt_business_name' => 'Kilap Auto Spa',
            'receipt_footer_note' => '',
            'receipt_photo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors([
            'receipt_photo' => 'Logo struk harus berupa gambar yang valid.',
        ]);
});

test('the logo can fill the printable width and is handed to both slip layouts', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.receipt.update'), [
            'receipt_business_name' => 'Kilap Auto Spa',
            'receipt_footer_note' => '',
            'receipt_logo_width' => 72,
        ])
        ->assertSessionHasNoErrors();

    expect(AppSettings::receiptLogoWidth())->toBe(72);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.master.receipt.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('brand.receipt.logoWidth', 72)
                ->where('settings.receiptLogoWidth', 72),
        );
});

/* An omitted size must leave the printed mark exactly as it is. */
test('an omitted logo width keeps the size already saved', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    AppSettings::put(AppSettings::RECEIPT_LOGO_WIDTH, '32', $owner->id);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.receipt.update'), [
            'receipt_business_name' => 'Kilap Auto Spa',
            'receipt_footer_note' => '',
        ])
        ->assertSessionHasNoErrors();

    expect(AppSettings::receiptLogoWidth())->toBe(32);
});

test('a logo width outside the roll is rejected', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.receipt.update'), [
            'receipt_business_name' => 'Kilap Auto Spa',
            'receipt_footer_note' => '',
            'receipt_logo_width' => 96,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors([
            'receipt_logo_width' => 'Lebar logo struk harus antara 8 dan 72 mm.',
        ]);
});

/* A width left over from an older range must still print inside the roll. */
test('a stored logo width is clamped on the way out', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    AppSettings::put(AppSettings::RECEIPT_LOGO_WIDTH, '900', $owner->id);
    expect(AppSettings::receiptLogoWidth())->toBe(72);

    AppSettings::put(AppSettings::RECEIPT_LOGO_WIDTH, 'lebar', $owner->id);
    expect(AppSettings::receiptLogoWidth())->toBe(15);
});

test('the receipt page scrolls to the first validation error', function () {
    $source = file_get_contents(resource_path('js/pages/admin/master/Receipt.vue'));

    expect($source)
        ->toContain('onError: scrollToFirstError')
        ->toContain("scrollIntoView({ behavior: 'smooth', block: 'center' })")
        // The logo input is sr-only, so its label is the only visible target.
        ->toContain('`label[for="${fieldName}"]`');
});

test('staff access follows receipt capabilities', function () {
    $this->actingAs(receiptStaff(['read' => false]), 'admin')
        ->get(route('admin.master.receipt.index'))
        ->assertForbidden();

    $readOnly = receiptStaff(['read' => true]);

    $this->actingAs($readOnly, 'admin')
        ->get(route('admin.master.receipt.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('capabilities.update', false));

    $this->actingAs($readOnly, 'admin')
        ->post(route('admin.master.receipt.update'), [
            'receipt_business_name' => 'Kilap Auto Spa',
            'receipt_footer_note' => '',
        ])
        ->assertForbidden();
});
