#!/usr/bin/env bash
#
# Pin the generated Android project to the light appearance.
#
#     scripts/force-light-appearance.sh
#
# `php artisan native:install android` regenerates nativephp/android from the
# vendor template every build, so this runs right after it and never edits a
# committed file. Three levers make a device or emulator set to dark render the
# app light anyway:
#
#   1. app/src/main/res/values{,-night}/themes.xml — the vendor theme parents on
#      Theme.MaterialComponents.DayNight.DarkActionBar, whose DayNight parent
#      switches the system chrome (dialogs, pickers, window background) with the
#      OS. Reparented to the Light variant, and values-night is reparented too so
#      a dark device cannot win.
#
#   2. ui/MainActivity.kt — the first composition reads isSystemInDarkTheme() and
#      hands it to the Material3 color scheme; pinned to false.
#
#   3. ui/MainActivity.kt — every nativephp/mobile-ui renderer also reads
#      isSystemInDarkTheme() itself to pick its light vs dark tokens, so pinning
#      only the Material3 scheme leaves the screen bodies dark. An
#      attachBaseContext override pins the Activity's Configuration to
#      night-mode NO, which is the one read all of them share.
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

MAIN_ACTIVITY="$ANDROID_ROOT/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt"
[ -f "$MAIN_ACTIVITY" ] || fail "missing $MAIN_ACTIVITY"

# Pin the Compose color scheme. Idempotent: a re-run finds the pinned literal.
if grep -q 'val isDark = isSystemInDarkTheme()' "$MAIN_ACTIVITY"; then
    perl -pi -e 's/val isDark = isSystemInDarkTheme\(\)/val isDark = false/' "$MAIN_ACTIVITY"
elif ! grep -q 'val isDark = false' "$MAIN_ACTIVITY"; then
    fail "MainActivity.kt has no isDark assignment to pin; update this script for the new NativePHP template"
fi

# Pin the Activity's own Configuration to night-mode NO. `isSystemInDarkTheme()`
# reads the composition-local Configuration, so this is the one lever that flips
# it to false for EVERY consumer at once: the Material3 scheme above AND each
# nativephp/mobile-ui renderer, which chooses light vs dark tokens by reading it
# itself (TextRenderer, ContainerRenderers, Modal, BottomSheet, ...). Without
# it a device set to dark paints the screen bodies dark around the light chrome.
# The renderer sources can't carry the patch — native:package wipes and rebuilds
# app/src/nativephp/kotlin every build — but MainActivity.kt lives under
# app/src/main/java and survives.
if grep -q 'override fun attachBaseContext(newBase: Context)' "$MAIN_ACTIVITY"; then
    : # already pinned (idempotent re-run)
elif grep -q '    override fun onCreate(savedInstanceState: Bundle?) {' "$MAIN_ACTIVITY"; then
    perl -0pi -e '
        my $anchor = "    override fun onCreate(savedInstanceState: Bundle?) {";
        my $insert = "    // Pinned light (scripts/force-light-appearance.sh): hand the Activity a\n"
            . "    // Configuration pinned to night-mode NO so every isSystemInDarkTheme()\n"
            . "    // read -- the Material3 scheme and each plugin renderer -- resolves\n"
            . "    // light, no matter what the device asks for.\n"
            . "    override fun attachBaseContext(newBase: Context) {\n"
            . "        val config = Configuration(newBase.resources.configuration)\n"
            . "        config.uiMode =\n"
            . "            (config.uiMode and Configuration.UI_MODE_NIGHT_MASK.inv()) or Configuration.UI_MODE_NIGHT_NO\n"
            . "        super.attachBaseContext(newBase.createConfigurationContext(config))\n"
            . "    }\n\n";
        s/\Q$anchor\E/$insert$anchor/ or die "onCreate anchor not found\n";
    ' "$MAIN_ACTIVITY"
else
    fail "MainActivity.kt has no onCreate to pin the Activity configuration; update this script for the new NativePHP template"
fi

echo "==> Forced light appearance (themes + Compose scheme + Activity configuration)"
