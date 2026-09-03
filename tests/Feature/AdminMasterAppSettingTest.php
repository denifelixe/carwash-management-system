<?php

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\AppSetting;
use App\Support\AppSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Cache::flush();
    config(['app.name' => 'ZenWash Auto Care']);
});

function fakeFavicon(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'favicon.ico',
        file_get_contents(public_path('favicon.ico')),
    );
}

/**
 * @return array{meta_title: string, meta_description: string}
 */
function appMetadataPayload(): array
{
    return [
        'meta_title' => 'Kilap Auto Spa — Carwash & Detailing',
        'meta_description' => 'Layanan cuci mobil, detailing, dan perawatan kendaraan profesional.',
    ];
}

/**
 * @param  array<string, bool>  $abilities
 */
function appSettingStaff(array $abilities): Admin
{
    $role = AdminRole::query()->create([
        'key' => 'app_setting_'.uniqid(),
        'name' => 'App Setting Staff',
        'description' => 'Role uji akses app setting.',
        'is_active' => true,
    ]);

    $role->modules()->attach(
        AdminModule::query()->where('key', 'master_app_settings')->firstOrFail(),
        [
            'can_create' => $abilities['create'] ?? false,
            'can_read' => $abilities['read'] ?? false,
            'can_update' => $abilities['update'] ?? false,
            'can_delete' => $abilities['delete'] ?? false,
        ],
    );

    return Admin::factory()->create(['role_id' => $role->id]);
}

test('guests cannot open app setting', function () {
    $this->get(route('admin.master.app-settings.index'))
        ->assertRedirect(route('admin.login'));
});

test('an owner sees app setting in the master sidebar', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.master.app-settings.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/master/AppSettings')
                ->where('settings.appName', 'ZenWash Auto Care')
                ->where('settings.whatsapp', '6281800090009')
                ->where('settings.instagram', 'zenwash.id')
                ->where('settings.metaTitle', 'ZenWash Auto Care Management System')
                ->where('settings.metaDescription', 'Aplikasi manajemen carwash ZenWash Auto Care: kasir POS, order & antrean, booking, stok, keuangan, serta kartu stempel digital untuk member.')
                ->where('settings.metaImageUrl', '/og-image.png')
                ->where('settings.hasMetaImage', false)
                ->where('settings.appPhotoUrl', null)
                ->where('settings.hasAppPhoto', false)
                ->where('settings.faviconUrl', null)
                ->where('settings.favicon16Url', null)
                ->where('settings.favicon32Url', null)
                ->where('settings.appleTouchIconUrl', null)
                ->where('settings.androidChrome192Url', null)
                ->where('settings.androidChrome512Url', null)
                ->where('settings.siteWebmanifestUrl', null)
                ->where('capabilities.update', true)
                ->where('modules.11.key', 'master')
                ->where('modules.11.active', true)
                ->where('modules.11.children.0.key', 'master_app_settings')
                ->where('modules.11.children.0.active', true)
                ->where(
                    'modules.11.children.0.href',
                    route('admin.master.app-settings.index', absolute: false),
                ),
        );
});

test('an owner can update the name photo and favicon', function () {
    Storage::fake('public');
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.app-settings.update'), [
            'app_name' => 'Kilap Auto Spa',
            'whatsapp' => '0812-3456-7890',
            'instagram' => '@Kilap.AutoSpa',
            ...appMetadataPayload(),
            'meta_image' => UploadedFile::fake()->image('social-preview.png', 600, 315),
            'app_photo' => UploadedFile::fake()->image('app-photo.png', 400, 400),
            'favicon' => fakeFavicon(),
            'favicon_16' => UploadedFile::fake()->image('favicon-16x16.png', 16, 16),
            'favicon_32' => UploadedFile::fake()->image('favicon-32x32.png', 32, 32),
            'apple_touch_icon' => UploadedFile::fake()->image('apple-touch-icon.png', 180, 180),
            'android_chrome_192' => UploadedFile::fake()->image('android-chrome-192x192.png', 192, 192),
            'android_chrome_512' => UploadedFile::fake()->image('android-chrome-512x512.png', 512, 512),
            'site_webmanifest' => UploadedFile::fake()->createWithContent(
                'site.webmanifest',
                json_encode(['name' => 'Kilap Auto Spa'], JSON_THROW_ON_ERROR),
            ),
        ])
        ->assertRedirect(route('admin.master.app-settings.index'))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Setting aplikasi berhasil diperbarui.');

    $settings = AppSetting::query()
        ->whereIn('key', [
            AppSettings::APP_NAME,
            AppSettings::WHATSAPP,
            AppSettings::INSTAGRAM,
            AppSettings::META_TITLE,
            AppSettings::META_DESCRIPTION,
            AppSettings::META_IMAGE,
            AppSettings::APP_PHOTO,
            AppSettings::FAVICON,
            AppSettings::FAVICON_16,
            AppSettings::FAVICON_32,
            AppSettings::APPLE_TOUCH_ICON,
            AppSettings::ANDROID_CHROME_192,
            AppSettings::ANDROID_CHROME_512,
            AppSettings::SITE_WEBMANIFEST,
        ])
        ->pluck('value', 'key');

    expect($settings[AppSettings::APP_NAME])->toBe('Kilap Auto Spa')
        ->and($settings[AppSettings::WHATSAPP])->toBe('6281234567890')
        ->and($settings[AppSettings::INSTAGRAM])->toBe('kilap.autospa')
        ->and($settings[AppSettings::META_TITLE])->toBe(appMetadataPayload()['meta_title'])
        ->and($settings[AppSettings::META_DESCRIPTION])->toBe(appMetadataPayload()['meta_description'])
        ->and(config('app.name'))->toBe('Kilap Auto Spa');

    Storage::disk('public')->assertExists($settings[AppSettings::META_IMAGE]);
    Storage::disk('public')->assertExists($settings[AppSettings::APP_PHOTO]);
    Storage::disk('public')->assertExists($settings[AppSettings::FAVICON]);
    Storage::disk('public')->assertExists($settings[AppSettings::FAVICON_16]);
    Storage::disk('public')->assertExists($settings[AppSettings::FAVICON_32]);
    Storage::disk('public')->assertExists($settings[AppSettings::APPLE_TOUCH_ICON]);
    Storage::disk('public')->assertExists($settings[AppSettings::ANDROID_CHROME_192]);
    Storage::disk('public')->assertExists($settings[AppSettings::ANDROID_CHROME_512]);
    Storage::disk('public')->assertExists($settings[AppSettings::SITE_WEBMANIFEST]);

    $photoUrl = Storage::disk('public')->url($settings[AppSettings::APP_PHOTO]);
    $metaImageUrl = Storage::disk('public')->url($settings[AppSettings::META_IMAGE]);
    $faviconUrl = Storage::disk('public')->url($settings[AppSettings::FAVICON]);
    $favicon16Url = Storage::disk('public')->url($settings[AppSettings::FAVICON_16]);
    $favicon32Url = Storage::disk('public')->url($settings[AppSettings::FAVICON_32]);
    $appleTouchIconUrl = Storage::disk('public')->url($settings[AppSettings::APPLE_TOUCH_ICON]);
    $androidChrome192Url = Storage::disk('public')->url($settings[AppSettings::ANDROID_CHROME_192]);
    $androidChrome512Url = Storage::disk('public')->url($settings[AppSettings::ANDROID_CHROME_512]);
    $siteWebmanifestUrl = Storage::disk('public')->url($settings[AppSettings::SITE_WEBMANIFEST]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.master.app-settings.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('brand.name', 'Kilap Auto Spa')
                ->where('brand.photo', $photoUrl)
                ->where('brand.whatsapp', '6281234567890')
                ->where('brand.instagram', 'kilap.autospa'),
        );

    $this->actingAs($owner, 'admin')
        ->get(route('admin.master.app-settings.index'))
        ->assertSee('<meta name="title" content="'.e(appMetadataPayload()['meta_title']).'">', false)
        ->assertSee('<meta name="description" content="'.e(appMetadataPayload()['meta_description']).'">', false)
        ->assertSee('<meta property="og:image" content="'.url($metaImageUrl).'">', false)
        ->assertSee('<meta property="twitter:image" content="'.url($metaImageUrl).'">', false)
        ->assertSee('<link rel="icon" href="'.$faviconUrl.'" sizes="any">', false)
        ->assertSee('<link rel="icon" type="image/png" sizes="16x16" href="'.$favicon16Url.'">', false)
        ->assertSee('<link rel="icon" type="image/png" sizes="32x32" href="'.$favicon32Url.'">', false)
        ->assertSee('<link rel="apple-touch-icon" sizes="180x180" href="'.$appleTouchIconUrl.'">', false)
        ->assertSee('<link rel="icon" type="image/png" sizes="192x192" href="'.$androidChrome192Url.'">', false)
        ->assertSee('<link rel="icon" type="image/png" sizes="512x512" href="'.$androidChrome512Url.'">', false)
        ->assertSee('<link rel="manifest" href="'.$siteWebmanifestUrl.'">', false);
});

test('replacing an app photo deletes the previous file', function () {
    Storage::fake('public');
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')->post(route('admin.master.app-settings.update'), [
        'app_name' => 'Kilap Auto Spa',
        'whatsapp' => '6281234567890',
        'instagram' => 'kilap.autospa',
        ...appMetadataPayload(),
        'favicon' => fakeFavicon(),
        'app_photo' => UploadedFile::fake()->image('first.png'),
    ]);

    $oldPath = AppSettings::get(AppSettings::APP_PHOTO);

    $this->actingAs($owner, 'admin')->post(route('admin.master.app-settings.update'), [
        'app_name' => 'Kilap Auto Spa',
        'whatsapp' => '6281234567890',
        'instagram' => 'kilap.autospa',
        ...appMetadataPayload(),
        'app_photo' => UploadedFile::fake()->image('second.png'),
    ]);

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists(AppSettings::get(AppSettings::APP_PHOTO));
});

test('an owner can remove app and metadata photos to restore defaults', function () {
    Storage::fake('public');
    $owner = Admin::factory()->create(['is_owner' => true]);
    $appPhotoPath = 'app-branding/app-photo.png';
    $metaImagePath = 'app-branding/meta-image.png';

    Storage::disk('public')->put($appPhotoPath, 'app-photo');
    Storage::disk('public')->put($metaImagePath, 'meta-image');
    AppSettings::put(AppSettings::APP_PHOTO, $appPhotoPath, $owner->id);
    AppSettings::put(AppSettings::META_IMAGE, $metaImagePath, $owner->id);
    AppSettings::put(AppSettings::FAVICON, 'app-branding/favicon.ico', $owner->id);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.app-settings.update'), [
            'app_name' => 'Kilap Auto Spa',
            'whatsapp' => '6281234567890',
            'instagram' => 'kilap.autospa',
            ...appMetadataPayload(),
            'remove_app_photo' => true,
            'remove_meta_image' => true,
        ])
        ->assertRedirect(route('admin.master.app-settings.index'))
        ->assertSessionHasNoErrors();

    expect(AppSettings::get(AppSettings::APP_PHOTO))->toBeNull()
        ->and(AppSettings::get(AppSettings::META_IMAGE))->toBeNull();
    Storage::disk('public')->assertMissing($appPhotoPath);
    Storage::disk('public')->assertMissing($metaImagePath);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.master.app-settings.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('settings.appPhotoUrl', null)
                ->where('settings.hasAppPhoto', false)
                ->where('settings.metaImageUrl', '/og-image.png')
                ->where('settings.hasMetaImage', false),
        );

    $this->actingAs($owner, 'admin')
        ->get(route('admin.master.app-settings.index'))
        ->assertSee('<meta property="og:image" content="'.url('/og-image.png').'">', false)
        ->assertSee('<meta property="twitter:image" content="'.url('/og-image.png').'">', false);
});

test('favicon is optional and uses the default when none has been uploaded', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.app-settings.update'), [
            'app_name' => 'Kilap Auto Spa',
            'whatsapp' => '6281234567890',
            'instagram' => 'kilap.autospa',
            ...appMetadataPayload(),
        ])
        ->assertRedirect(route('admin.master.app-settings.index'))
        ->assertSessionHasNoErrors();

    expect(AppSettings::get(AppSettings::FAVICON))->toBeNull();

    $this->actingAs($owner, 'admin')
        ->get(route('admin.master.app-settings.index'))
        ->assertSee('<link rel="icon" href="/favicon.ico" sizes="any">', false);
});

test('app setting rejects invalid names and files', function () {
    Storage::fake('public');
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->from(route('admin.master.app-settings.index'))
        ->post(route('admin.master.app-settings.update'), [
            'app_name' => '',
            'whatsapp' => 'nomor-wa',
            'instagram' => 'instagram tidak valid',
            'meta_title' => '',
            'meta_description' => '',
            'meta_image' => UploadedFile::fake()->create('social-preview.pdf', 10, 'application/pdf'),
            'app_photo' => UploadedFile::fake()->create('app.pdf', 10, 'application/pdf'),
            'favicon' => UploadedFile::fake()->create('favicon.svg', 10, 'image/svg+xml'),
            'favicon_16' => UploadedFile::fake()->image('favicon-16x16.png', 32, 32),
            'favicon_32' => UploadedFile::fake()->image('favicon-32x32.png', 16, 16),
            'apple_touch_icon' => UploadedFile::fake()->image('apple-touch-icon.png', 100, 100),
            'android_chrome_192' => UploadedFile::fake()->image('android-chrome-192x192.png', 200, 200),
            'android_chrome_512' => UploadedFile::fake()->image('android-chrome-512x512.png', 500, 500),
            'site_webmanifest' => UploadedFile::fake()->createWithContent('site.webmanifest', '{tidak-valid'),
        ])
        ->assertRedirect(route('admin.master.app-settings.index'))
        ->assertSessionHasErrors([
            'app_name',
            'whatsapp',
            'instagram',
            'meta_title',
            'meta_description',
            'meta_image',
            'app_photo',
            'favicon',
            'favicon_16',
            'favicon_32',
            'apple_touch_icon',
            'android_chrome_192',
            'android_chrome_512',
            'site_webmanifest',
        ])
        ->assertSessionHasErrors([
            'meta_image' => 'Social image harus berupa gambar yang valid.',
            'favicon' => 'Favicon utama harus berupa file ICO.',
            'favicon_16' => 'Dimensi favicon 16x16 harus tepat 16x16 piksel.',
        ]);
});

test('app setting page scrolls to the first validation error', function () {
    $source = file_get_contents(resource_path('js/pages/admin/master/AppSettings.vue'));

    expect($source)
        ->toContain('onError: scrollToFirstError')
        ->toContain("scrollIntoView({ behavior: 'smooth', block: 'center' })")
        ->toContain('`label[for="${fieldName}"]`');
});

test('staff access follows app setting capabilities', function () {
    $this->actingAs(appSettingStaff(['read' => false]), 'admin')
        ->get(route('admin.master.app-settings.index'))
        ->assertForbidden();

    $readOnlyStaff = appSettingStaff(['read' => true]);

    $this->actingAs($readOnlyStaff, 'admin')
        ->get(route('admin.master.app-settings.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page->where('capabilities.update', false),
        );

    $this->actingAs($readOnlyStaff, 'admin')
        ->post(route('admin.master.app-settings.update'), ['app_name' => 'Tidak Boleh'])
        ->assertForbidden();
});
