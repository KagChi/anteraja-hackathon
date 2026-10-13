{{--
    The whole app is this one web view.

    JavaScript and DOM storage are opt-ins: the web view ships locked down
    (JS off, no DOM storage, no new windows), and a remote page needs both to
    run the Inertia app inside it. `fullscreen` makes it fill the screen and
    extend behind the safe areas.

    A build stamped without a real MOBILE_WEB_BASE_URL — absent, or the
    reserved placeholder CI uses before the site is deployed — has nothing to
    load, so it says so instead of showing the web view's error page.
--}}
@php
    $url = (string) config('mobile.start_url');
    $appName = (string) config('mobile.apps.' . config('mobile.app') . '.name', config('app.name'));
@endphp

@if ($url === '')
    <native:column class="w-full h-full items-center justify-center gap-3 p-6 bg-white dark:bg-zinc-900">
        <native:text class="text-lg font-bold text-center text-zinc-900 dark:text-zinc-100">{{ $appName }}</native:text>
        <native:text class="text-sm text-center text-zinc-900 dark:text-zinc-100">This build has no site to show yet. Rebuild the APK with MOBILE_WEB_BASE_URL pointing at the deployed site.</native:text>
    </native:column>
@else
    <native:webview src="{{ $url }}" javascript dom-storage fullscreen />
@endif
