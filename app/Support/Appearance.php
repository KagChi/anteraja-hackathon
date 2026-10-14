<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Native\Mobile\Edge\TailwindParser;
use Native\Mobile\UI\Theme;

/**
 * The theme switcher's preference, and the one place that turns it into the
 * palette both apps draw.
 *
 * `system` (the default) leaves the native-ui theme exactly as authored in
 * `config/native-ui.php`: every `*-theme-*` class emits a light and a dark
 * companion, and the platform picks between them from the OS colour scheme.
 *
 * `light` and `dark` pin that choice inside the app. The platform still
 * decides per element from the OS scheme, so pinning cannot work by choosing
 * a block — it works by making BOTH blocks hold the pinned palette. Then
 * whichever side reads the theme agrees: the `theme()` helper that colours
 * layout chrome, and the renderers that pick `dark_*` props from the system
 * appearance.
 *
 * The preference is kept in the cache store, which the mobile shells build
 * with the file driver (see .env.mobile.example), so it survives a launch.
 * Effective theme tokens are per-process state, so the preference is
 * re-applied on every boot from AppServiceProvider.
 *
 * Known limitation: this styles everything the app itself draws. The OS
 * chrome around it — status bar, keyboards, the platform's own sheets —
 * follows the device unless the build pins it, so a pinned light app on a
 * dark device still gets dark system chrome.
 */
final class Appearance {
    public const SYSTEM = 'system';

    public const LIGHT = 'light';

    public const DARK = 'dark';

    private const CACHE_KEY = 'native.appearance';

    /**
     * The authored light / dark palettes, captured once per process.
     *
     * Theme::merge() is the only way to pin, and it rewrites the effective
     * tokens in place, so the authored pair has to be read before the first
     * pin — afterwards Theme::all() returns whatever was pinned last, and the
     * "follow the device" option would have nothing to restore.
     *
     * @var array{light: array<string, mixed>, dark: array<string, mixed>}|null
     */
    private static ?array $authored = null;

    /** Whether this process has pinned a palette away from the authored one. */
    private static bool $pinned = false;

    /** The modes the switcher offers, in display order. */
    public static function modes(): array {
        return [self::SYSTEM, self::LIGHT, self::DARK];
    }

    /** The switcher's option labels, in modes() order. */
    public static function labels(): array {
        return ['Sistem', 'Terang', 'Gelap'];
    }

    /** The stored mode, or `system` when nothing valid has been stored. */
    public static function get(): string {
        // Read on the boot path, where an unreachable store has to degrade to
        // the default rather than take the whole app down with it.
        $mode = rescue(fn () => Cache::get(self::CACHE_KEY), null, report: false);

        return is_string($mode) && in_array($mode, self::modes(), true) ? $mode : self::SYSTEM;
    }

    /** The stored mode as an index into modes() — the switcher's bound value. */
    public static function index(): int {
        $index = array_search(self::get(), self::modes(), true);

        return $index === false ? 0 : $index;
    }

    /**
     * Store and apply the mode at $index — what the switcher calls. An
     * unknown index falls back to `system` rather than leaving the app on a
     * palette the preference store cannot describe.
     */
    public static function select(int $index): string {
        $mode = self::modes()[$index] ?? self::SYSTEM;
        rescue(fn () => Cache::forever(self::CACHE_KEY, $mode), report: false);
        self::apply($mode);

        return $mode;
    }

    /** Push the $mode palette (or the stored one) to the native theme store. */
    public static function apply(?string $mode = null): void {
        $mode ??= self::get();

        if (! in_array($mode, self::modes(), true)) {
            return;
        }

        // Nothing to undo while the process still holds the authored palette.
        if ($mode === self::SYSTEM && ! self::$pinned) {
            return;
        }

        $authored = self::authored();

        if ($authored['light'] === [] && $authored['dark'] === []) {
            return;
        }

        if ($mode === self::SYSTEM) {
            self::$pinned = false;
            Theme::merge(['light' => $authored['light'], 'dark' => $authored['dark']]);
        } else {
            $palette = $mode === self::DARK ? $authored['dark'] : $authored['light'];

            self::$pinned = true;
            Theme::merge(['light' => $palette, 'dark' => $palette]);
        }

        // The parser memoizes each class string's resolved colours, so a
        // palette that changed after the first frame would keep drawing the
        // old one until the process restarted.
        TailwindParser::clearCache();
    }

    /**
     * The palettes as authored, with the dark block already derived.
     *
     * Theme::all() derives dark from light, so reading it after native-ui's
     * own boot yields both blocks in wire-format hex. An empty read means
     * native-ui has not loaded yet; returning empty without caching it lets
     * the caller retry once it has, instead of pinning an empty palette.
     *
     * @return array{light: array<string, mixed>, dark: array<string, mixed>}
     */
    private static function authored(): array {
        if (self::$authored !== null) {
            return self::$authored;
        }

        $all = Theme::all();
        $light = is_array($all['light'] ?? null) ? $all['light'] : [];
        $dark = is_array($all['dark'] ?? null) ? $all['dark'] : [];

        if ($light === [] && $dark === []) {
            return ['light' => [], 'dark' => []];
        }

        return self::$authored = ['light' => $light, 'dark' => $dark];
    }
}
