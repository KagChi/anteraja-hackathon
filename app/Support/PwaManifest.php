<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Turns one config/pwa.php entry into the manifest a browser installs from.
 *
 * The registry stores what the app is; this is the wire shape the Web App
 * Manifest spec expects, so the registry never has to repeat spec keys.
 */
final class PwaManifest {
    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    public static function for(array $entry): array {
        return [
            'id' => $entry['start_url'],
            'name' => $entry['name'],
            'short_name' => $entry['short_name'],
            'description' => $entry['description'],
            'start_url' => $entry['start_url'],
            'scope' => $entry['scope'],
            'display' => 'standalone',
            'orientation' => 'portrait',
            'lang' => 'id',
            'dir' => 'ltr',
            'theme_color' => $entry['theme_color'],
            'background_color' => $entry['background_color'],
            'icons' => $entry['icons'],
        ];
    }
}
