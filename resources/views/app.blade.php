<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">

        {{-- Search engine & crawler metadata --}}
        <meta name="title" content="{{ $meta['title'] }}">
        <meta name="description" content="{{ $meta['description'] }}">
        <meta name="keywords" content="{{ $meta['keywords'] }}">
        <meta name="author" content="{{ config('app.name') }}">
        <meta name="robots" content="index, follow">
        <meta name="theme-color" content="{{ $meta['themeColor'] }}">
        <link rel="canonical" href="{{ url()->current() }}">

        {{-- Open Graph (Facebook, WhatsApp, LinkedIn) --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $meta['title'] }}">
        <meta property="og:description" content="{{ $meta['description'] }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:locale" content="{{ $meta['locale'] }}">
        <meta property="og:image" content="{{ url($meta['ogImage']) }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $meta['title'] }}">

        {{-- Twitter / X --}}
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:url" content="{{ url()->current() }}">
        <meta property="twitter:title" content="{{ $meta['title'] }}">
        <meta property="twitter:description" content="{{ $meta['description'] }}">
        <meta property="twitter:image" content="{{ url($meta['ogImage']) }}">

        {{-- Installable web app --}}
        <meta name="application-name" content="{{ config('app.name') }}">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="mobile-web-app-capable" content="yes">

        <link rel="icon" href="{{ $meta['favicon'] }}" sizes="any">
        @if ($meta['favicon16'] !== null)
            <link rel="icon" type="image/png" sizes="16x16" href="{{ $meta['favicon16'] }}">
        @endif
        @if ($meta['favicon32'] !== null)
            <link rel="icon" type="image/png" sizes="32x32" href="{{ $meta['favicon32'] }}">
        @endif
        <link rel="apple-touch-icon" sizes="180x180" href="{{ $meta['appleTouchIcon'] }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ $meta['androidChrome192'] }}">
        <link rel="icon" type="image/png" sizes="512x512" href="{{ $meta['androidChrome512'] }}">
        <link rel="manifest" href="{{ $meta['siteWebmanifest'] }}">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ $meta['title'] }}</title>
        </x-inertia::head>
    </head>
    <body @class([
        'font-sans antialiased',
        'app-staging' => config('app.type') === 'STAGING',
    ])>
        @if (config('app.type') === 'STAGING')
            <div
                role="status"
                aria-label="Staging environment"
                class="staging-banner fixed inset-x-0 top-0 z-[100] flex h-12 items-center justify-center gap-3 px-4 text-center sm:h-14 sm:gap-4"
            >
                <span class="staging-banner__dot" aria-hidden="true"></span>
                <span class="staging-banner__label text-sm font-bold tracking-[0.3em] text-amber-100 uppercase sm:text-lg sm:tracking-[0.45em]">
                    STAGING ENVIRONMENT
                </span>
                <span class="hidden text-xs font-medium tracking-[0.1em] text-amber-200/50 sm:inline" aria-hidden="true">
                    Data uji coba &middot; bukan data real
                </span>
            </div>
        @endif

        <x-inertia::app />
    </body>
</html>
