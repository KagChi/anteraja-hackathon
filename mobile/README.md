# Mobile shells

The Android app is built from this codebase with
[NativePHP for Mobile](https://nativephp.com/docs/mobile/4/getting-started/installation).

Its screens are **native UI**: `NativeComponent` classes rendering EDGE
elements, compiled to Jetpack Compose on Android and SwiftUI on iOS. There is
no web view, nothing is fetched at runtime, and no site URL has to be
configured — which is why a build can never open on "no site to show yet".

## The apps

`mobile/apps.php` is the source of truth for identity and entry route.

| App | Package id | Opens on | Status |
| --- | --- | --- | --- |
| Anteraja Kurir | `com.anteraja.kurir` | `/courier/tasks` | Built |
| Anteraja Frozen | `com.anteraja.frozen` | `/client/orders` | Built |

An app whose `start` is `null` is refused by `scripts/build-apk.sh` rather than
packaged, so a build can never open the wrong flow. Both apps are packaged
today; the guard stays because any shell added later has to port its screens
before it can be built.

## Screens

Each screen is a class in `app/NativeComponents/` plus a Blade view in
`resources/views/native/`, registered in `routes/mobile.php`:

| Route | Component | View |
| --- | --- | --- |
| `/courier/tasks` | `Courier\Tasks` | `native/courier/tasks.blade.php` |
| `/courier/history` | `Courier\History` | `native/courier/history.blade.php` |
| `/courier/profile` | `Courier\Profile` | `native/courier/profile.blade.php` |
| `/courier/delivery/{resi}` | `Courier\Delivery` | `native/courier/delivery.blade.php` |
| `/courier/proof/{resi}` | `Courier\Proof` | `native/courier/proof.blade.php` |
| `/courier/issue/{resi}` | `Courier\Issue` | `native/courier/issue.blade.php` |
| `/client/orders` | `Client\Orders` | `native/client/orders.blade.php` |
| `/client/history` | `Client\History` | `native/client/history.blade.php` |
| `/client/profile` | `Client\Profile` | `native/client/profile.blade.php` |
| `/client/tracking/{resi}` | `Client\Tracking` | `native/client/tracking.blade.php` |
| `/client/schedule/{resi}` | `Client\Schedule` | `native/client/schedule.blade.php` |
| `/client/issue/{resi}` | `Client\Issue` | `native/client/issue.blade.php` |

Each app's three tab destinations share a layout — `Layouts\CourierTabsLayout`
or `Layouts\ClientTabsLayout` (a `NativeLayout`) for their bottom bar; the
pushed flow screens sit outside those groups and carry their own
back-navigable top bar. The three customer flow screens share one partial,
`native/client/partials/missing-order.blade.php`, for the "resi not on
this account" card.

Screen content comes from `App\Support\CourierData` and
`App\Support\ClientData`, one-for-one ports of the fixtures the web screens
use (`resources/js/data/courier.ts`, `client.ts`). They are the single seam to
replace when a flow is wired to the API — nothing else reads those values.
`ClientData` also owns the pill colours both customer order lists share, so the
Pesanan and Riwayat tabs cannot drift apart.

Styling is Tailwind utility classes over the theme tokens in
`config/native-ui.php`, which mirror the Material roles in
`resources/css/app.css`. Icons come from the generated enums in `app/Icons/`
(`php artisan native-ui:generate-icons`); use `:ios` / `:android` so each
platform gets a real symbol rather than a Material name iOS cannot resolve.

## Theme switcher

Both apps end the Profil tab with a `Tema` card — the shared
`native/partials/appearance.blade.php` — offering **Sistem** (follow the
device), **Terang** and **Gelap**. The choice is kept in the cache store, which
the shells build with the file driver, so it survives a launch;
`AppServiceProvider` re-applies it once every provider has booted, so a pinned
app opens in its palette instead of the device's.

Pinning cannot work by selecting a theme block: every `*-theme-*` class emits a
light and a dark companion, and the platform picks one per element from the OS
colour scheme. `App\Support\Appearance` therefore writes the chosen palette
into **both** blocks, which keeps the `theme()` helper that colours the tab bar
in step with what the renderers draw. The OS chrome around the app — status
bar, keyboards, the platform's own sheets — still follows the device, which is
pinned only at build time (`NATIVEPHP_APPEARANCE`).

## Building an APK

`.github/workflows/android-apk.yaml` builds both apps on every push to `main`,
on release creation, and on demand from the Actions tab. It needs these
repository settings:

| Kind | Name | Value |
| --- | --- | --- |
| Secret | `ANDROID_KEYSTORE_BASE64` | A release keystore, base64-encoded |
| Secret | `ANDROID_KEYSTORE_PASSWORD` | Its password |
| Secret | `ANDROID_KEY_ALIAS` | The key alias |
| Secret | `ANDROID_KEY_PASSWORD` | The key password |

Without the keystore secrets the workflow still succeeds, signing with a
generated throwaway key — fine for sideloading, but **each such build signs with
a different key**, so a new APK will not install over an older one. Uninstall the
old app first, or set the secrets to get updateable builds. Create the real
keystore with:

```sh
mkdir -p mobile/keystore
keytool -genkeypair -keystore mobile/keystore/anteraja.jks -alias anteraja \
  -keyalg RSA -keysize 2048 -validity 10000
base64 -i mobile/keystore/anteraja.jks | pbcopy   # → ANDROID_KEYSTORE_BASE64
```

To build locally you need Android Studio's SDK on the machine, then:

```sh
export ANDROID_KEYSTORE_FILE=$PWD/mobile/keystore/anteraja.jks
export ANDROID_KEYSTORE_PASSWORD=... ANDROID_KEY_ALIAS=anteraja ANDROID_KEY_PASSWORD=...

php artisan native:run android      # compile and launch on an emulator or device
scripts/build-apk.sh kurir          # signed release APK → dist/anteraja-kurir.apk
scripts/build-apk.sh client         # signed release APK → dist/anteraja-client.apk
```

The script stamps `.env` and `public/icon.png` from `mobile/apps.php` and
`mobile/icons/`, then restores your development `.env`.

## Testing

Component tests render the element tree in-process — no device, no simulator:

```sh
vendor/bin/phpunit --filter 'CourierNativeScreensTest|ClientNativeScreensTest'
```

`Native::test(Tasks::class)` mounts a screen by class and `Native::visit(...)`
mounts it by route, with `tap()`, `set()`, `call()` and the `assert*` methods
driving and inspecting it. The customer tests cover the same ground from the
other side: every tab and pushed route resolves, each flow screen renders the
order its resi names, and no screen contains a web view.

## Known limitations

- There is no native map element, so the courier delivery screen opens the
  route in the system maps app instead of drawing it inline.
- There is no clipboard bridge in core, so the customer screens mark the resi
  `select-text` and let the OS long-press copy handle it, instead of the web's
  copy button.
- The native image stack has no SVG decoder, so the customer schedule screen
  draws a themed cold-chain tile where the web dropped in
  `public/images/product-placeholder.svg`.
- `php artisan native:validate` reports `Unknown native element type` for
  plugin-provided elements such as `pressable` and `list-item`, and `Class
  does not extend NativeComponent` for the tab layouts, which extend
  `NativeLayout` instead. Its element and component lists are core-only.
