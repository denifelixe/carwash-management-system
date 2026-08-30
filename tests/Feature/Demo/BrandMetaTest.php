<?php

use App\Support\Demo\Brand;

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
        ->assertSee('<meta name="keywords" content="'.e($meta['keywords']).'">', false)
        ->assertSee('<meta name="theme-color" content="'.$meta['themeColor'].'">', false)
        ->assertSee('<meta property="og:title" content="'.e($meta['title']).'">', false)
        ->assertSee('<meta property="og:description" content="'.e($meta['description']).'">', false)
        ->assertSee('<meta property="og:image" content="'.url($meta['ogImage']).'">', false)
        ->assertSee('<meta property="og:locale" content="'.$meta['locale'].'">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
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
