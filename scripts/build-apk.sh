#!/usr/bin/env bash
#
# Build a signed release APK for one of the two NativePHP Android shells.
#
#     scripts/build-apk.sh kurir
#     scripts/build-apk.sh client
#
# The two apps share this codebase, so the package id, display name, launcher
# icon and start path are stamped into .env and public/icon.png before each
# build, and your development .env is put back afterwards. Identity comes from
# mobile/apps.php; the site URL comes from the environment.
#
# Required environment:
#   MOBILE_WEB_BASE_URL         origin of the deployed site, e.g. https://anteraja.example.com
#   ANDROID_KEYSTORE_FILE       keystore (.jks) that signs the release build
#   ANDROID_KEYSTORE_PASSWORD   its password
#   ANDROID_KEY_ALIAS           the key's alias
#   ANDROID_KEY_PASSWORD        the key's password
#
# Optional:
#   APP_KEY                     defaults to the key in .env, then .env.example
#   NATIVEPHP_APP_VERSION       defaults to 0.1.<GITHUB_RUN_NUMBER>
#   NATIVEPHP_APP_VERSION_CODE  defaults to <GITHUB_RUN_NUMBER>
#
# Output: dist/anteraja-<app>.apk

set -euo pipefail

APP="${1:-}"
case "$APP" in
    kurir|client) ;;
    *)
        echo "usage: scripts/build-apk.sh <kurir|client>" >&2
        exit 64
        ;;
esac

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

missing=()
for name in MOBILE_WEB_BASE_URL ANDROID_KEYSTORE_FILE ANDROID_KEYSTORE_PASSWORD ANDROID_KEY_ALIAS ANDROID_KEY_PASSWORD; do
    [ -n "${!name:-}" ] || missing+=("$name")
done

if [ ${#missing[@]} -gt 0 ]; then
    echo "Missing required environment:" >&2
    printf '  %s\n' "${missing[@]}" >&2

    if [[ " ${missing[*]} " == *" MOBILE_WEB_BASE_URL "* ]]; then
        cat >&2 <<'NOTE'

MOBILE_WEB_BASE_URL is the origin of the deployed site, e.g.
https://anteraja.example.com. In CI it comes from the repository variable of
the same name (Settings → Secrets and variables → Actions → Variables).

NOTE
    fi

    for name in ANDROID_KEYSTORE_FILE ANDROID_KEYSTORE_PASSWORD ANDROID_KEY_ALIAS ANDROID_KEY_PASSWORD; do
        if [[ " ${missing[*]} " == *" $name "* ]]; then
            cat >&2 <<'NOTE'
Create a release keystore once, then export the four ANDROID_* values:

  mkdir -p mobile/keystore
  keytool -genkeypair -keystore mobile/keystore/anteraja.jks -alias anteraja \
    -keyalg RSA -keysize 2048 -validity 10000

NOTE
            break
        fi
    done

    exit 64
fi

if [ ! -f "$ANDROID_KEYSTORE_FILE" ]; then
    echo "Keystore not found: $ANDROID_KEYSTORE_FILE" >&2
    exit 66
fi

BASE_URL="${MOBILE_WEB_BASE_URL%/}"

# The shared app registry is plain data, so a bare require reads it without
# booting the framework.
IFS='|' read -r APP_ID APP_NAME APP_PATH <<< "$(php -r '
    $apps = require "mobile/apps.php";
    $app = $apps[$argv[1]] ?? null;
    if ($app === null) {
        fwrite(STDERR, "unknown app: {$argv[1]}\n");
        exit(1);
    }
    echo $app["app_id"], "|", $app["name"], "|", $app["path"];
' "$APP")"

APP_KEY="${APP_KEY:-$(grep -m1 '^APP_KEY=' .env 2>/dev/null | cut -d= -f2- || true)}"
APP_KEY="${APP_KEY:-$(grep -m1 '^APP_KEY=' .env.example 2>/dev/null | cut -d= -f2- || true)}"
if [ -z "$APP_KEY" ]; then
    echo "No APP_KEY available: set APP_KEY, or add one to .env." >&2
    exit 64
fi

APP_VERSION="${NATIVEPHP_APP_VERSION:-0.1.${GITHUB_RUN_NUMBER:-0}}"
APP_VERSION_CODE="${NATIVEPHP_APP_VERSION_CODE:-${GITHUB_RUN_NUMBER:-1}}"

# Stamp this app's .env from the committed template.
ENV_FILE=".env.$APP"
sed \
    -e "s|__MOBILE_APP__|$APP|g" \
    -e "s|__APP_ID__|$APP_ID|g" \
    -e "s|__APP_NAME__|$APP_NAME|g" \
    -e "s|__WEB_BASE_URL__|$BASE_URL|g" \
    -e "s|__APP_KEY__|$APP_KEY|g" \
    -e "s|__APP_VERSION__|$APP_VERSION|g" \
    -e "s|__APP_VERSION_CODE__|$APP_VERSION_CODE|g" \
    .env.mobile.example > "$ENV_FILE"

if grep -qE '__[A-Z_]+__' "$ENV_FILE"; then
    echo "Unsubstituted placeholder left in $ENV_FILE:" >&2
    grep -nE '__[A-Z_]+__' "$ENV_FILE" >&2
    exit 70
fi

restore_dev_env() {
    if [ -f .env.dev.backup ]; then
        mv .env.dev.backup .env
    fi
    rm -f public/icon.png
    php artisan config:clear --no-interaction >/dev/null 2>&1 || true
}
trap restore_dev_env EXIT

if [ -f .env ] && [ ! -f .env.dev.backup ]; then
    cp .env .env.dev.backup
fi
cp "$ENV_FILE" .env

cp "mobile/icons/$APP.png" public/icon.png

php artisan config:clear --no-interaction >/dev/null

echo "==> $APP_NAME ($APP_ID)"
echo "    web view: $BASE_URL$APP_PATH"
echo "    version:  $APP_VERSION ($APP_VERSION_CODE)"

bun run build -- --mode=android

php artisan native:install android --no-interaction

# The packager runs Gradle with a hard 10-minute ceiling, and a cold run spends
# a chunk of that downloading the wrapper and resolving the AGP/Kotlin plugin
# classpath. Pay that here instead; if this fails the real build reports why.
if [ -x nativephp/android/gradlew ]; then
    echo "==> Warming Gradle"
    (cd nativephp/android && ./gradlew help) || true
fi

mkdir -p dist
php artisan native:package android --no-tty --output="$ROOT/dist"

mv "$ROOT/dist/app-release.apk" "$ROOT/dist/anteraja-$APP.apk"

echo "==> dist/anteraja-$APP.apk"
