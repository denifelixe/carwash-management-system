<?php

use App\Support\Carwash\Brand;

/**
 * The root template must ship the brand's document metadata and the full
 * favicon set on every page, so crawlers and link unfurlers see the brand.
 */
test('the root template renders the document metadata', function () {
    $meta = Brand::meta();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<meta name="description" content="'.e($meta['description']).'">', false)
        ->assertSee('<meta name="keywords" content="'.e($meta['keywords']).'">', false)
        ->assertSee('<meta name="theme-color" content="'.$meta['themeColor'].'">', false)
        ->assertSee('<meta property="og:title" content="'.e($meta['title']).'">', false)
        ->assertSee('<meta property="og:description" content="'.e($meta['description']).'">', false)
        ->assertSee('<meta property="og:image" content="'.url($meta['ogImage']).'">', false)
        ->assertSee('<meta property="og:locale" content="'.$meta['locale'].'">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('<link rel="canonical" href="'.route('home').'">', false);
});

test('the root template links the whole favicon set', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<link rel="icon" href="/favicon.ico" sizes="any">', false)
        ->assertSee('<link rel="icon" href="/favicon.svg" type="image/svg+xml">', false)
        ->assertSee('<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">', false)
        ->assertSee('<link rel="manifest" href="/site.webmanifest">', false);
});

test('the title carries the brand name', function () {
    expect(config('app.name'))->toBe(Brand::identity()['name']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<title>'.e(Brand::identity()['name']).'</title>', false);
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
