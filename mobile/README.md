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
| Anteraja Frozen | `com.anteraja.frozen` | — | Not built: the customer journey has no native screens yet |

An app whose `start` is `null` is refused by `scripts/build-apk.sh` rather than
packaged, so a build can never open the wrong flow. Port the customer screens
(`orders`, `tracking`, `schedule`, `issue`) to `app/NativeComponents/Client/`,
register them in `routes/mobile.php`, and set `start` to switch it on.

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

The three tab destinations share `Layouts\CourierTabsLayout` (a `NativeLayout`)
for their bottom bar; the pushed flow screens sit outside that group and carry
their own back-navigable top bar.

Screen content comes from `App\Support\CourierData`, a one-for-one port of the
fixtures the web screens use (`resources/js/data/courier.ts`). It is the single
seam to replace when the courier flow is wired to the API — nothing else reads
those values.

Styling is Tailwind utility classes over the theme tokens in
`config/native-ui.php`, which mirror the Material roles in
`resources/css/app.css`. Icons come from the generated enums in `app/Icons/`
(`php artisan native-ui:generate-icons`); use `:ios` / `:android` so each
platform gets a real symbol rather than a Material name iOS cannot resolve.

## Building an APK

`.github/workflows/android-apk.yaml` builds the app on every push to `main`, on
release creation, and on demand from the Actions tab. It needs these repository
settings:

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
keytool -genkeypair -keystore mobile/keystore/anteraja.jks -alias anteraja \\
  -keyalg RSA -keysize 2048 -validity 10000
base64 -i mobile/keystore/anteraja.jks | pbcopy   # → ANDROID_KEYSTORE_BASE64
```

To build locally you need Android Studio's SDK on the machine, then:

```sh
export ANDROID_KEYSTORE_FILE=$PWD/mobile/keystore/anteraja.jks
export ANDROID_KEYSTORE_PASSWORD=... ANDROID_KEY_ALIAS=anteraja ANDROID_KEY_PASSWORD=...

php artisan native:run android      # compile and launch on an emulator or device
scripts/build-apk.sh kurir          # signed release APK → dist/anteraja-kurir.apk
```

The script stamps `.env` and `public/icon.png` from `mobile/apps.php` and
`mobile/icons/`, then restores your development `.env`.

## Testing

Component tests render the element tree in-process — no device, no simulator:

```sh
vendor/bin/phpunit --filter CourierNativeScreensTest
```

`Native::test(Tasks::class)` mounts a screen by class and `Native::visit(...)`
mounts it by route, with `tap()`, `set()`, `call()` and the `assert*` methods
driving and inspecting it.

## Known limitations

- The customer app is not ported; see the table above.
- There is no native map element, so the delivery screen opens the route in the
  system maps app instead of drawing it inline.
- `php artisan native:validate` reports `Unknown native element type` for
  plugin-provided elements such as `list-item`. Its element list is core-only.
