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
    /*
    |--------------------------------------------------------------------------
    | Allow Private Hosts
    |--------------------------------------------------------------------------
    |
    | When true, the SSRF guard allows requests to private/local IP ranges
    | (127.x.x.x, 192.168.x.x, .test domains, etc.).
    | Safe to enable locally — the package is already restricted to local env.
    |
    */
    'allow_private_hosts' => env('LARAFIED_ALLOW_PRIVATE_HOSTS', true),

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
    | Route Filters
    |--------------------------------------------------------------------------
    |
    | Restrict which routes appear in the scanner.
    |
    | only_middleware: show only routes that have at least one of these middleware.
    |   Example: ['api'] — hides all web/HTML routes, shows only API routes.
    |
    | only_prefix: show only routes whose URI starts with one of these prefixes.
    |   Example: ['api', 'v1'] — shows /api/* and /v1/* routes only.
    |
    | Leave both arrays empty to show all routes (default behaviour).
    |
    */
    'route_filters' => [
        'only_middleware' => [],
        'only_prefix'    => [],
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

    /*
    |--------------------------------------------------------------------------
    | Network Monitor
    |--------------------------------------------------------------------------
    |
    | When enabled, the NetworkMonitorMiddleware captures every incoming HTTP
    | request and response so you can inspect them live in the Larafied UI.
    |
    | To enable, set LARAFIED_NETWORK_MONITOR=true in your .env, then add
    | \Larafied\Http\Middleware\NetworkMonitorMiddleware::class to your
    | application middleware stack (bootstrap/app.php or Http/Kernel.php).
    |
    | max_body_size: Maximum bytes to store per request/response body (default 4 KB).
    | max_events:   Maximum events kept in the database (oldest are pruned).
    |
    */
    'network_monitor' => [
        'enabled'       => env('LARAFIED_NETWORK_MONITOR', false),
        'max_body_size' => (int) env('LARAFIED_NETWORK_BODY_SIZE', 4096),
        'max_events'    => (int) env('LARAFIED_NETWORK_MAX_EVENTS', 500),
    ],

];
