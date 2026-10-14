# Mobile shells

The Android app is built from this codebase with
[NativePHP for Mobile](https://nativephp.com/docs/mobile/4/getting-started/installation).

Its screens are **native UI**: `NativeComponent` classes rendering EDGE
elements, compiled to Jetpack Compose on Android and SwiftUI on iOS. There is
no web view and no Blade page: every screen reads and writes through the
backend API over HTTPS with a Sanctum bearer token, so a build can never open
on "no site to show yet".

## The apps

`mobile/apps.php` is the source of truth for identity and entry route.

| App | Package id | Opens on | Status |
| --- | --- | --- | --- |
| Anteraja Kurir | `com.anteraja.kurir` | `/courier/start` | Built |
| Anteraja Frozen | `com.anteraja.frozen` | `/client/start` | Built |

`/courier/start` and `/client/start` are entry gates. The courier gate forwards
a signed-in courier to the task board and everyone else to the login screen.
The customer gate forwards a signed-in customer to the Pesanan tab and everyone
else to the storefront landing screen, where the catalog can be browsed before
an account exists.

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
| `/courier/start` | `Courier\Start` | `native/start.blade.php` |
| `/courier/login` | `Courier\Login` | `native/courier/login.blade.php` |
| `/client/start` | `Client\Start` | `native/start.blade.php` |
| `/client/login` | `Client\Login` | `native/client/login.blade.php` |
| `/client/landing` | `Client\Landing` | `native/client/landing.blade.php` |
| `/client/catalog` | `Client\Catalog` | `native/client/catalog.blade.php` |
| `/client/catalog/{slug}` | `Client\ProductDetail` | `native/client/product-detail.blade.php` |
| `/client/register` | `Client\Register` | `native/client/register.blade.php` |
| `/client/checkout` | `Client\Checkout` | `native/client/checkout.blade.php` |
| `/client/order-success` | `Client\OrderSuccess` | `native/client/order-success.blade.php` |

Each app's tab destinations share a layout — `Layouts\CourierTabsLayout` or
`Layouts\ClientTabsLayout` (a `NativeLayout`) for their bottom bar; the
customer bar carries Beranda, Pesanan, Riwayat and Profil, while the
pushed flow screens sit outside those groups and carry their own
back-navigable top bar. The three customer flow screens share one partial,
`native/client/partials/missing-order.blade.php`, for the "resi not on
this account" card.

## Storefront

The customer app is a shop as well as a tracker. Beranda and the catalog read
`GET /api/client/catalog`, which is public, so a visitor can look around before
signing up; the app asks for an account at checkout. Registering calls
`POST /api/client/auth/register` and stores the bearer token, and checkout posts
`POST /api/client/orders`: the order is created with source CLIENT_APP and shows
up in the hub queue tagged APP.

The basket lives in `App\Support\ClientCart` (product ids and quantities in the
cache store, like `MobileToken` and `Appearance`), because native screens are
separate components with no shared parent. Names, prices and weights are always
re-read from the catalog at checkout, so a stale basket can never quote a price
the server would not charge. `App\Support\ClientReceipt` carries the created
order to the success screen, and only shows it when the resi matches.

Screen content comes from the backend API. `App\Support\MobileApi` is the one
door to it: it carries the realm's bearer token, prefixes
`config('mobile.api_base_url')`, and folds transport failures into an
`ApiResult`. `App\Support\MobileToken` keeps that token in the platform
keychain (falling back to the cache store off-device), `ClientPayload` and
`CourierPayload` map the JSON onto the shapes the blades read, and
`App\Support\Concerns\LoadsFromApi` gives every screen the shared
`$error` / `retry()` pair. `ClientData` still owns the pill colours both
customer order lists share, so the Pesanan and Riwayat tabs cannot drift apart.
The customer API lives under `/api/client/...`, the courier API under
`/api/courier/...`; both are signed in to from a native login screen.

Styling is Tailwind utility classes over the theme tokens in
`config/native-ui.php`, which mirror the Material roles in
`resources/css/app.css`. Icons come from the generated enums in `app/Icons/`
(`php artisan native-ui:generate-icons`); use `:ios` / `:android` so each
platform gets a real symbol rather than a Material name iOS cannot resolve.

## Location validation

The courier app ships the device's last fix window with depart, arrive and proof
of delivery. The Delivery screen keeps a bounded window (60 fixes) from
`Geolocation::watchPosition(fineAccuracy: true)` — coordinates, accuracy and the
device clock — and shows what the server made of it: accuracy, distance to the
destination, the tolerated radius and the reason codes.

The server is authoritative. `App\Services\Gps\FakeGpsDetector` checks the
window for a simulated location (sub-metre accuracy, a frozen feed, impossible
travel) and `App\Services\Gps\GeofenceEvaluator` measures the fix against the
destination with the haversine helper, using the radius in `geofence_policies`
(Reguler 100 m, Instant Frozen 30 m by default; editable in Radius GPS). A strong
signal, or a hand-over outside the radius, is refused with a 422 carrying the
reasons; the attempt is recorded in `location_checks` and a review request opens
for the hub. Validasi GPS is where the hub approves or rejects it, and an
approval is what lets the courier's retry through while the incident stays on
record.

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
`mobile/icons/`, then restores your development `.env`. The API host is
stamped from `MOBILE_API_BASE_URL` and defaults to
`https://hackathon.kagchi.my.id/api`; export it to point a build at another
backend.

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
- For the same reason the catalog draws a themed tile per product instead of
  `image_url`; the SVG art in `public/images/catalog/` is used by the landing
  page, which renders in a browser.
- `php artisan native:validate` reports `Unknown native element type` for
  plugin-provided elements such as `pressable` and `list-item`, and `Class
  does not extend NativeComponent` for the tab layouts, which extend
  `NativeLayout` instead. Its element and component lists are core-only.
