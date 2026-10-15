@php
    /** @var array<string, mixed> $app */
    $app = config("pwa.apps.{$appKey}");
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        {{-- viewport-fit=cover is what makes the safe-area padding real on iOS,
             where the status bar overlays the page. --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        {{-- One light theme everywhere: this keeps the browser's own chrome
             (URL bar, form controls, scrollbars) light on a dark-mode phone. --}}
        <meta name="color-scheme" content="light">
        <meta name="theme-color" content="{{ $app['theme_color'] }}">

        <title>{{ $app['title'] }}</title>
        <meta name="description" content="{{ $app['description'] }}">

        <link rel="manifest" href="{{ $app['manifest_url'] }}">
        <link rel="icon" type="image/png" href="/images/anteraja-logo.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/icons/{{ $appKey }}-apple-180.png">
        {{-- iOS ignores most of the manifest, so the install metadata is
             repeated here: it is what names the home-screen icon. --}}
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="{{ $app['short_name'] }}">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="mobile-web-app-capable" content="yes">

        <!-- Fonts: Inter for the Frozen screens, Material Symbols for icons -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">

        @viteReactRefresh
        @vite('resources/js/pwa/main.tsx')
    </head>
    <body class="bg-surface text-on-surface antialiased" data-app="{{ $appKey }}">
        <div id="pwa-root" class="min-h-dvh"></div>
        <noscript>
            <div class="flex min-h-dvh items-center justify-center p-margin text-center font-body-md text-body-md text-secondary">
                Aplikasi ini membutuhkan JavaScript. Aktifkan JavaScript lalu muat ulang halaman.
            </div>
        </noscript>
    </body>
</html>
