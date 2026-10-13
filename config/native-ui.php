<?php

/**
 * Native UI — theme tokens for the Anteraja Kurir app.
 *
 * Published via `php artisan vendor:publish --tag=native-ui-config`; the token
 * names mirror the Material roles the web console already uses in
 * resources/css/app.css, so a colour lives in exactly one place per platform.
 *
 * The map is open-ended: add a role to both blocks and `bg-theme-<role>`
 * resolves immediately. `on-X` is the colour of content placed ON X.
 * Dark mode is auto-derived from `light` because the design system is
 * light-first and clinical; override a token in `dark` only where the
 * derived value reads badly.
 */

return [
    'theme' => [
        'light' => [
            // Brand — Anteraja magenta. Filled buttons, active states, accents.
            'primary' => '#B6005B',
            'on-primary' => '#FFFFFF',
            'primary-container' => '#E01274',
            'on-primary-container' => '#FFFBFF',
            'primary-fixed' => '#FFD9E1',
            'inverse-primary' => '#FFB1C6',

            // Muted action / informational.
            'secondary' => '#515F74',
            'on-secondary' => '#FFFFFF',
            'secondary-container' => '#D5E3FD',
            'on-secondary-container' => '#57657B',
            'secondary-fixed' => '#D5E3FD',
            'on-secondary-fixed' => '#0D1C2F',

            // Nominal cold-chain green.
            'tertiary' => '#006B1C',
            'on-tertiary' => '#FFFFFF',
            'tertiary-container' => '#008725',
            'on-tertiary-container' => '#F7FFF1',
            'tertiary-fixed' => '#86FC86',

            // Surfaces — depth comes from tone, not shadow.
            'surface' => '#FFFFFF',
            'on-surface' => '#111C2D',
            'background' => '#F9F9FF',
            'on-background' => '#111C2D',
            'surface-variant' => '#D8E3FB',
            'on-surface-variant' => '#5A3F46',
            'surface-container-lowest' => '#FFFFFF',
            'surface-container-low' => '#F0F3FF',
            'surface-container' => '#E7EEFF',
            'surface-container-high' => '#DEE8FF',
            'surface-container-highest' => '#D8E3FB',
            'surface-dim' => '#CFDAF2',
            'inverse-surface' => '#263143',
            'on-inverse-surface' => '#ECF1FF',

            // Structure — hairline seams rather than borders.
            'outline' => '#8E6F76',
            'outline-variant' => '#E2BDC5',

            // Exception / breach.
            'error' => '#BA1A1A',
            'on-error' => '#FFFFFF',
            'error-container' => '#FFDAD6',
            'on-error-container' => '#93000A',
            'destructive' => '#BA1A1A',
            'on-destructive' => '#FFFFFF',

            // Confirmations, verified badges, "safe to proceed".
            'success' => '#006B1C',
            'on-success' => '#FFFFFF',
            'accent' => '#E01274',
            'on-accent' => '#FFFFFF',
        ],

        // Light-first design; invert automatically rather than hand-authoring.
        'dark' => [],

        // Corner radii (dp) — cards use md, inner blocks sm, pills full.
        'radius-sm' => 8,
        'radius-md' => 12,
        'radius-lg' => 16,
        'radius-full' => 9999,

        // Font scale (sp).
        'font-sm' => 13,
        'font-md' => 15,
        'font-lg' => 18,
        'font-xl' => 22,
    ],

    'fonts' => [
        // Inter has no bundled file yet; run `php artisan native:font Inter`
        // and point this at 'Inter-Regular' to match the web console.
        'default' => 'System',
    ],
];
