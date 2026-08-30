<?php

use App\Support\AppSettings;
use App\Support\Demo\Brand;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * The root template must ship the brand's document metadata and the full
 * favicon set on every page, so crawlers and link unfurlers see the brand.
 */
test('the brand identity carries the ZenWash naming', function () {
    $identity = Brand::identity();

    expect($identity['name'])->toBe('ZenWash Auto Care')
        ->and($identity['system'])->toBe('ZenWash Auto Care Management System')
        ->and(Brand::meta()['title'])->toBe($identity['system']);
});

test('the entry page separates the brand name from the smaller system label', function () {
    $entryPage = file_get_contents(
        resource_path('js/pages/demo/auth/Entry.vue'),
    );

    expect($entryPage)
        ->toContain('{{ brand.name }}')
        ->toContain("{{ brand.system.replace(brand.name, '').trim() }}")
        ->toContain('text-base font-medium text-slate-300 sm:text-lg');
});

test('the root template renders the document metadata', function () {
    $meta = Brand::meta();

    $this->get(route('demo.home'))
        ->assertOk()
        ->assertSee('<meta name="description" content="'.e($meta['description']).'">', false)
        ->assertSee('<meta name="title" content="'.e($meta['title']).'">', false)
        ->assertSee('<meta name="keywords" content="'.e($meta['keywords']).'">', false)
        ->assertSee('<meta name="theme-color" content="'.$meta['themeColor'].'">', false)
        ->assertSee('<meta property="og:title" content="'.e($meta['title']).'">', false)
        ->assertSee('<meta property="og:description" content="'.e($meta['description']).'">', false)
        ->assertSee('<meta property="og:image" content="'.url($meta['ogImage']).'">', false)
        ->assertSee('<meta property="og:locale" content="'.$meta['locale'].'">', false)
        ->assertSee('<meta property="twitter:card" content="summary_large_image">', false)
        ->assertSee('<meta property="twitter:url" content="'.route('demo.home').'">', false)
        ->assertSee('<link rel="canonical" href="'.route('demo.home').'">', false);
});

test('the root template links the whole favicon set', function () {
    $this->get(route('demo.home'))
        ->assertOk()
        ->assertSee('<link rel="icon" href="/favicon.ico" sizes="any">', false)
        ->assertSee('<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">', false)
        ->assertSee('<link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png">', false)
        ->assertSee('<link rel="icon" type="image/png" sizes="512x512" href="/icon-512.png">', false)
        ->assertSee('<link rel="manifest" href="/site.webmanifest">', false);
});

test('the title carries the brand name', function () {
    expect(config('app.name'))->toBe(Brand::identity()['name']);

    $html = $this->get(route('demo.home'))->assertOk()->getContent();

    // Server-side rendering may append a page suffix, so match the tag loosely.
    expect($html)->toMatch('/<title[^>]*>[^<]*'.preg_quote(e(Brand::identity()['name']), '/').'[^<]*<\/title>/');
});

dataset('favicon assets', [
    'favicon.ico' => ['favicon.ico'],
    'favicon.svg' => ['favicon.svg'],
    'apple-touch-icon' => ['apple-touch-icon.png'],
    'icon 192' => ['icon-192.png'],
    'icon 512' => ['icon-512.png'],
    'open graph image' => ['og-image.png'],
    'web manifest' => ['site.webmanifest'],
]);

test('every referenced brand asset exists', function (string $asset) {
    expect(public_path($asset))->toBeFile();
})->with('favicon assets');

/*
 * The demo and the live console share one installation, so the outlet's saved
 * branding would otherwise leak onto the demo through the settings table. The
 * demo always wears the shipped brand; only the live domains follow the
 * settings an owner saved in Master > App Settings.
 */
test('the demo keeps the shipped brand even after the live console saves its own', function () {
    Storage::fake('public');

    AppSettings::put(AppSettings::APP_NAME, 'Showtime Autocare');
    AppSettings::put(AppSettings::APP_PHOTO, 'app-branding/app-photo.png');
    AppSettings::put(AppSettings::FAVICON, 'app-branding/favicon.ico');
    AppSettings::put(AppSettings::FAVICON_16, 'app-branding/favicon-16x16.png');
    AppSettings::put(AppSettings::META_TITLE, 'Showtime Autocare');
    AppSettings::put(AppSettings::META_IMAGE, 'app-branding/social.png');
    AppSettings::put(AppSettings::WHATSAPP, '6289900001111');
    AppSettings::put(AppSettings::INSTAGRAM, 'showtime.autocare');

    $this->get(route('demo.home'))
        ->assertOk()
        ->assertSee('<meta name="title" content="ZenWash Auto Care Management System">', false)
        ->assertSee('<link rel="icon" href="/favicon.ico" sizes="any">', false)
        ->assertSee('<meta property="og:image" content="'.url('/og-image.png').'">', false)
        ->assertDontSee('favicon-16x16.png')
        ->assertDontSee('Showtime')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('brand.name', 'ZenWash Auto Care')
            ->where('brand.photo', null)
            ->where('brand.whatsapp', '6281800090009')
            ->where('brand.instagram', 'zenwash.id'));
});

test('the live domains still wear the branding the owner saved', function () {
    Storage::fake('public');

    AppSettings::put(AppSettings::APP_NAME, 'Showtime Autocare');
    AppSettings::put(AppSettings::APP_PHOTO, 'app-branding/app-photo.png');
    AppSettings::put(AppSettings::INSTAGRAM, 'showtime.autocare');

    $this->get('https://'.config('domains.member').'/')
        ->assertServiceUnavailable()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('member/UnderConstruction')
            ->where('brand.name', 'Showtime Autocare')
            ->where('brand.photo', Storage::disk('public')->url('app-branding/app-photo.png'))
            ->where('brand.instagram', 'showtime.autocare'));
});
