#!/usr/bin/env bash
#
# Pin the generated Android project to the light appearance.
#
#     scripts/force-light-appearance.sh
#
# `php artisan native:install android` regenerates nativephp/android from the
# vendor template every build, so this runs right after it and never edits a
# committed file. Two levers make a device or emulator set to dark render the
# app light anyway:
#
#   1. app/src/main/res/values{,-night}/themes.xml — the vendor theme parents on
#      Theme.MaterialComponents.DayNight.DarkActionBar, whose DayNight parent
#      switches the system chrome (dialogs, pickers, window background) with the
#      OS. Reparented to the Light variant, and values-night is reparented too so
#      a dark device cannot win.
#
#   2. ui/MainActivity.kt — the first composition reads isSystemInDarkTheme() and
#      hands it to the Material3 color scheme, so Compose surfaces paint dark.
#      Pinned to false.
#
# NATIVEPHP_APPEARANCE=light only covers iOS (Info.plist UIUserInterfaceStyle),
# and status_bar_style='dark' only covers the bar icons — this is the Android
# surface. The patches fail the build loudly when a pattern is missing rather
# than ship an app that quietly honours dark mode.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ANDROID_ROOT="$ROOT/nativephp/android"

if [ ! -d "$ANDROID_ROOT" ]; then
    echo "force-light-appearance: $ANDROID_ROOT not found — run 'php artisan native:install android' first." >&2
    exit 1
fi

fail() {
    echo "force-light-appearance: $1" >&2
    exit 1
}

# Reparent a themes.xml to the Light theme. Accepts either the DayNight parent
# (fresh install) or an already-Light one (idempotent re-run).
reparent_theme() {
    local file="$1"
    [ -f "$file" ] || fail "missing $file"

    if grep -q 'parent="Theme.MaterialComponents.DayNight.DarkActionBar"' "$file"; then
        perl -pi -e 's/parent="Theme\.MaterialComponents\.DayNight\.DarkActionBar"/parent="Theme.MaterialComponents.Light.DarkActionBar"/' "$file"
    elif ! grep -q 'parent="Theme.MaterialComponents.Light.DarkActionBar"' "$file"; then
        fail "$file has an unexpected theme parent; update this script for the new NativePHP template"
    fi
}

reparent_theme "$ANDROID_ROOT/app/src/main/res/values/themes.xml"
reparent_theme "$ANDROID_ROOT/app/src/main/res/values-night/themes.xml"

# Pin the Compose color scheme. Idempotent: a re-run finds the pinned literal.
MAIN_ACTIVITY="$ANDROID_ROOT/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt"
[ -f "$MAIN_ACTIVITY" ] || fail "missing $MAIN_ACTIVITY"

if grep -q 'val isDark = isSystemInDarkTheme()' "$MAIN_ACTIVITY"; then
    perl -pi -e 's/val isDark = isSystemInDarkTheme\(\)/val isDark = false/' "$MAIN_ACTIVITY"
elif ! grep -q 'val isDark = false' "$MAIN_ACTIVITY"; then
    fail "MainActivity.kt has no isDark assignment to pin; update this script for the new NativePHP template"
fi

echo "==> Forced light appearance (themes + Compose scheme)"
