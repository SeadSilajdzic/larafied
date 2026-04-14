# Larafied

A browser-based API testing tool for Laravel — no external accounts, no setup, no Postman. Installed as a Composer package and available at `/larafied` in your local environment.

## Features

- **Route scanner** — automatically lists all your Laravel routes
- **HTTP client** — send requests with custom headers, JSON body, GraphQL, or raw
- **Auth helpers** — Bearer token, Basic auth, and API key authentication tabs (Pro)
- **Collections** — save and organise requests into folders; import/export Postman v2.1 (Pro)
- **Environments** — define variable sets (e.g. `{{BASE_URL}}`, `{{TOKEN}}`) and switch between them (Pro)
- **Pre-request scripts** — run JavaScript before each request to set headers, modify the URL, or read environment variables (Pro)
- **SQL Console** — run `SELECT` queries against your app's database connections (Pro)
- **Request history** — last 50 sent requests, searchable, one click to re-load (Pro)
- **Query log** — see which SQL queries each request triggered (Pro)
- **Password protection** — optional session-based lock for shared machines
- **Pro/Team tiers** — unlock additional features via license key

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- `ext-pdo`, `ext-pdo_sqlite`, `ext-json`

## Installation

```bash
composer require --dev larafied/larafied
php artisan larafied:install
```

The install command creates `storage/larafied/` and publishes the compiled assets.

Open your browser at `http://your-app.test/larafied`.

> Larafied is restricted to `local` environment by default. It will return `403` in production.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=larafied-config
```

`config/larafied.php`:

```php
return [
    // URL prefix — change if /larafied conflicts with your routes
    'prefix' => env('LARAFIED_PREFIX', 'larafied'),

    // Environments where Larafied is accessible
    'allowed_environments' => ['local'],

    // Optional: restrict to specific route name patterns in the scanner
    'exclude_routes' => [
        'larafied.*',
        'debugbar.*',
        'horizon.*',
        'telescope.*',
    ],

    // Optional: password-protect the UI (session-based)
    'password' => env('LARAFIED_PASSWORD'),

    // License key for Pro/Team features
    'license_key' => env('LARAFIED_LICENSE_KEY'),

    // Days to keep features active when cloud validation is unreachable
    'grace_period_days' => 7,
];
```

## Environments and Variables

Create environments with key/value pairs in the sidebar. Use `{{VARIABLE_NAME}}` syntax in URLs and header values — Larafied resolves them from the active environment before sending.

Example:
- Environment: `local` with `BASE_URL = http://api.test`
- URL: `{{BASE_URL}}/api/users` → sends to `http://api.test/api/users`

Secret variables (marked with the eye icon) are masked in the UI and not logged to history.

## Password Protection

Set `LARAFIED_PASSWORD` in your `.env` to require a password before accessing the UI. The session is remembered for the browser session.

```env
LARAFIED_PASSWORD=your-secret
```

## Query Log

The query log middleware is registered automatically by the package. Enable the **Query Log** toggle in the request builder URL bar (Pro feature), then check the **Queries** tab in the response viewer to see which SQL queries each request triggered — including the SQL, bindings, and per-query timing.

## Free vs Pro

| Feature | Free | Pro |
|---|---|---|
| Route scanner | ✓ | ✓ |
| HTTP client (JSON / Raw) | ✓ | ✓ |
| Collections | up to 5 | unlimited |
| Auth helpers (Bearer / Basic / API Key) | — | ✓ |
| Postman import / export | — | ✓ |
| Pre-request scripts | — | ✓ |
| Request history (searchable) | — | ✓ |
| Environments + variable interpolation | — | ✓ |
| GraphQL body type | — | ✓ |
| SQL Console | — | ✓ |
| Query log | — | ✓ |

Activate a Pro license key from the gear icon in the top-right corner of the UI, or via the API:

```bash
curl -X POST https://your-app.test/larafied/api/license/activate \
  -H "Content-Type: application/json" \
  -d '{"key": "YOUR-LICENSE-KEY", "domain": "your-app.test"}'
```

## Security

- Access is blocked in non-`local` environments by default
- All outbound requests go through an SSRF guard that blocks private IP ranges, loopback addresses, AWS metadata endpoints, and non-HTTP schemes
- Larafied uses its own SQLite database (`storage/larafied/workspace.db`) and never reads or writes to your application's database (except via the SQL Console, which is read-only SELECT only)
- The license cache is HMAC-signed to detect tampering

## Development

Assets are pre-compiled and committed to `public/`. No Vite or Mix configuration is needed in your host application.

To rebuild after modifying source files in this package:

```bash
npm install
npm run build
```

Run tests:

```bash
./vendor/bin/pest   # PHP
npm test            # JavaScript
```

## License

Larafied is source-available. The package code is free to use in local development environments. Commercial use of Pro/Team features requires a valid license key.
