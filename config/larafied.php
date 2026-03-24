<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed Environments
    |--------------------------------------------------------------------------
    |
    | Larafied will only be accessible in these environments.
    | Add 'staging' here if you want to use it on a staging server.
    | Never add 'production'.
    |
    */
    'allowed_environments' => ['local'],

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | The URL prefix for all Larafied routes.
    | Default: /larafied
    |
    */
    'prefix' => env('LARAFIED_PREFIX', 'larafied'),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to all Larafied routes.
    | The RestrictToAllowedEnvironments middleware is always applied automatically.
    |
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Password Protection
    |--------------------------------------------------------------------------
    |
    | Optionally protect the workspace UI with a password.
    | Set LARAFIED_PASSWORD in your .env file to enable.
    | Note: enforcement is planned for M2.
    |
    */
    'password' => env('LARAFIED_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | License Key
    |--------------------------------------------------------------------------
    |
    | Your Larafied Pro/Team license key.
    | Set LARAFIED_LICENSE_KEY in your .env file after purchasing.
    |
    */
    'license_key' => env('LARAFIED_LICENSE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Cloud URL
    |--------------------------------------------------------------------------
    |
    | The URL of the Larafied cloud service for license validation and sync.
    |
    */
    'cloud_url' => env('LARAFIED_CLOUD_URL', 'https://api.larafied.com'),

    /*
    |--------------------------------------------------------------------------
    | Offline Grace Period
    |--------------------------------------------------------------------------
    |
    | Number of days the package operates normally when the cloud is unreachable.
    | After this period, Pro/Team features revert to Free tier.
    |
    */
    'grace_period_days' => 7,

    /*
    |--------------------------------------------------------------------------
    | Excluded Routes
    |--------------------------------------------------------------------------
    |
    | Route name patterns to exclude from the route scanner.
    | Supports fnmatch() wildcards.
    |
    */
    'exclude_routes' => [
        'larafied.*',
        'debugbar.*',
        'telescope.*',
        'horizon.*',
        'livewire.*',
        'ignition.*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Title
    |--------------------------------------------------------------------------
    |
    | The title shown in the browser tab and UI header.
    |
    */
    'title' => env('LARAFIED_TITLE', 'Larafied'),

];
