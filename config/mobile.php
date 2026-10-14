<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Backend API
    |--------------------------------------------------------------------------
    |
    | Where the native shells send their requests. The apps talk to the same
    | Laravel backend the web console does, over HTTPS, with a Sanctum bearer
    | token. Stamp a different host per build with MOBILE_API_BASE_URL; the
    | default is the hackathon deployment.
    |
    */

    'api_base_url' => rtrim((string) env('MOBILE_API_BASE_URL', 'https://hackathon.kagchi.my.id/api'), '/'),

    'timeout' => (int) env('MOBILE_API_TIMEOUT', 20),
];
