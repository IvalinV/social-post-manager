<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    |
    | Single-user tool: the email that is allowed to access the Filament panel.
    | When null, any authenticated user may access (useful for local/testing).
    |
    */

    'admin_email' => env('ADMIN_EMAIL') ?: null,

    /*
    |--------------------------------------------------------------------------
    | Scraping
    |--------------------------------------------------------------------------
    |
    | Settings for the ad-hoc URL scraper. A real User-Agent avoids trivial
    | blocks; the timeout stops the queue worker hanging on dead sites.
    |
    */

    'scraping' => [
        'user_agent' => env('SCRAPER_USER_AGENT', 'SocialPostManager/1.0 (+personal scraper)'),
        'timeout' => (int) env('SCRAPER_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform limits
    |--------------------------------------------------------------------------
    |
    | Character ceilings used when rendering templates. X counts every URL as
    | a fixed 23 characters via t.co regardless of the real length.
    |
    */

    'limits' => [
        'x' => [
            'max_length' => 280,
            'url_length' => 23,
        ],
        'linkedin' => [
            'max_length' => 3000,
            'url_length' => null,
        ],
    ],

];
