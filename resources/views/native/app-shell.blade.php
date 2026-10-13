{{--
    The whole app is this one web view.

    JavaScript and DOM storage are opt-ins: the web view ships locked down
    (JS off, no DOM storage, no new windows), and a remote page needs both to
    run the Inertia app inside it. `fullscreen` makes it fill the screen and
    extend behind the safe areas.

    A build stamped without MOBILE_WEB_BASE_URL has nothing to load, so it says
    so instead of opening a blank web view.
--}}
@php
    $url = (string) config('mobile.start_url');
    $appName = (string) config('mobile.apps.' . config('mobile.app') . '.name', config('app.name'));
@endphp

@if ($url === '')
    <native:column class="w-full h-full items-center justify-center gap-3 p-6">
        <native:text class="text-lg font-bold text-center">{{ $appName }}</native:text>
        <native:text class="text-sm text-center">This build has no site URL configured. Rebuild the APK with MOBILE_WEB_BASE_URL set.</native:text>
    </native:column>
@else
    <native:webview src="{{ $url }}" javascript dom-storage fullscreen />
@endif
