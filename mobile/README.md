# Mobile shells

Two Android apps are built from this codebase with
[NativePHP for Mobile](https://nativephp.com/docs/mobile/4/getting-started/installation),
one per audience:

| App | Package id | Opens |
| --- | --- | --- |
| Anteraja Kurir | `com.anteraja.kurir` | `/courier/tasks` |
| Anteraja Frozen | `com.anteraja.frozen` | `/client/orders` |

Neither app has native screens. Each is a single full-screen web view onto the
deployed site (`resources/views/native/app-shell.blade.php`), which means the
React/Inertia UI keeps shipping through the normal web deploy and the APKs only
need rebuilding when the shell itself changes. The backend API lands in the
deployed app, so it needs no new APK either.

`mobile/apps.php` is the source of truth for the two identities;
`config/mobile.php` turns it into the web view URL at runtime.

## Building an APK

`.github/workflows/android-apk.yaml` builds both apps on every push to `main`,
on release creation, and on demand from the Actions tab. It needs these
repository settings:

| Kind | Name | Value |
| --- | --- | --- |
| Variable | `MOBILE_WEB_BASE_URL` | Origin of the deployed site, e.g. `https://anteraja.example.com` |
| Secret | `ANDROID_KEYSTORE_BASE64` | A release keystore, base64-encoded |
| Secret | `ANDROID_KEYSTORE_PASSWORD` | Its password |
| Secret | `ANDROID_KEY_ALIAS` | The key alias |
| Secret | `ANDROID_KEY_PASSWORD` | The key password |

Without the keystore secrets the workflow still succeeds, signing with a
throwaway debug key — fine for sideloading, but such builds can never be updated
by a build signed with the real key. Create the real one with:

```sh
mkdir -p mobile/keystore
keytool -genkeypair -keystore mobile/keystore/anteraja.jks -alias anteraja \
  -keyalg RSA -keysize 2048 -validity 10000
base64 -i mobile/keystore/anteraja.jks | pbcopy   # → ANDROID_KEYSTORE_BASE64
```

To build locally you need Android Studio's SDK on the machine, then:

```sh
export MOBILE_WEB_BASE_URL=https://anteraja.example.com
export ANDROID_KEYSTORE_FILE=$PWD/mobile/keystore/anteraja.jks
export ANDROID_KEYSTORE_PASSWORD=... ANDROID_KEY_ALIAS=anteraja ANDROID_KEY_PASSWORD=...

php artisan native:run android      # compile and launch on an emulator or device
scripts/build-apk.sh kurir          # signed release APK → dist/anteraja-kurir.apk
```

The script stamps `.env` and `public/icon.png` from `mobile/apps.php` and
`mobile/icons/`, then restores your development `.env`.

## Known limitations of the shell

- The web view ships locked down by default, so its navigation handler cancels
  `tel:` and other external-scheme taps. The courier's "call the recipient"
  action does nothing in the APK; the Google Maps link loads inside the web view
  instead of opening the Maps app. Fixing either means routing those taps
  through the native `Browser`/`Dialog` plugins from the UI.
- `php artisan native:validate` reports `Unknown native element type: 'webview'`
  for the shell. Its element list is core-only and does not know about the
  plugin-provided elements; the tag is declared by `nativephp/mobile-ui`.
