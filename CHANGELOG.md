# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased] — v1.0.0

### Added
- Auth helpers tab in request builder — Bearer token, Basic auth, API key (header or query param); variables resolved from active environment (Pro)
- Postman Collection v2.1 import — file picker in Collections sidebar; creates collection, folders, and requests in one step (Pro)
- Postman Collection v2.1 export — "Export as Postman" option in collection context menu; downloads as `.postman_collection.json` (Pro)
- Pre-request scripts — JavaScript snippet that runs before every request; Postman-compatible `pm` object with `pm.request` and `pm.environment`; script errors surface in the response panel rather than silently failing (Pro)
- History search — filter input in History sidebar by URL, method, or status code
- XML and HTML response pretty-printing with Prism syntax highlighting
- Copy button on response body — appears on hover, shows "Copied" feedback
- HTTP status text alongside status code in response meta bar (200 OK, 404 Not Found, etc.)
- "Copy as cURL" option in route context menu
- Keyboard shortcut Cmd/Ctrl+S — expands the Collections sidebar so the current request can be saved
- `postman.ts` — pure import/export utilities with full type coverage
- `preRequest.ts` — sandboxed script runner (`new Function` scope, no DOM/window access)
- Request history — last 50 requests stored in SQLite, one click to re-load
- Environments — create named variable sets with key/value pairs; use `{{VARIABLE}}` syntax in URLs and headers
- Variable interpolation — active environment variables resolved before sending; unresolved variables shown as amber chips in the URL preview
- URL variable preview — resolved variables shown as green chips inline below the URL bar
- GraphQL body type — syntax-highlighted editor with optional variables pane (Pro)
- SQL Console — run read-only `SELECT` queries against any configured database connection (Pro)
- Query log — per-request SQL query count and timing via `QueryLogMiddleware` (Pro)
- Password protection — optional session-based lock via `LARAFIED_PASSWORD` env variable
- `FeatureFlags::features()` method — returns authoritative feature list for current tier, independent of cloud cache staleness
- Free tier limit prompt — upgrade nudge shown when 5-collection limit is reached
- Response viewer tabs — Body / Headers / Query Log
- Grace warning banner — shown in UI when cloud validation has been offline for 5–7 days
- CI matrix — GitHub Actions testing PHP 8.1/8.2/8.3 × Laravel 10/11/12
- HMAC signature on `storage/larafied/license.json` — tamper detection

### Changed
- Sidebar redesigned as stacked collapsible sections (Routes / Collections / Environments / History) replacing tab navigation
- Environments UI rewritten as Postman-style inline expansion with variable grid (Key / Value / Secret)
- Drag-and-drop now activates on 200ms hold instead of requiring explicit drag handles
- Icons added to sidebar section headers and request builder tabs
- `LicenseController::show()` now returns features from `FeatureFlags::features()` (tier-authoritative) instead of the raw cloud cache array — fixes Pro/Team users missing features added after their last activation
- Cloud `FEATURES` map updated to include `graphql`, `sql_console`, `query_log`, `request_history` for Pro, Team, and Agency tiers
- `EnvironmentController` — update, destroy, and activate endpoints now enforce the `environments` feature gate (consistent with index and store)
- GraphQL requests always sent as `POST` regardless of method selector state
- Variable interpolation extracted to a standalone pure function (`interpolate.ts`) for testability

### Fixed
- Team license users unable to access Pro features (graphql, sql_console, query_log, request_history) due to stale cloud cache
- `EnvironmentController::update/destroy/activate` missing feature gate — free-tier users with leftover data could manipulate environments

---

## [0.1.0] — M1 Alpha

Initial working package.

### Added
- Route scanner — automatically lists all Laravel routes, grouped by prefix
- HTTP proxy — send requests with custom method, URL, headers, and body
- JSON body with syntax highlighting
- Collections — save, organise into folders, drag-and-drop reorder
- SSRF guard — blocks private IP ranges, loopback, non-HTTP schemes, AWS metadata endpoint
- Dedicated SQLite workspace (`storage/larafied/workspace.db`) — never touches host app database
- `RestrictToAllowedEnvironments` middleware — blocks access outside `local` by default
- `php artisan larafied:install` command
- Pre-compiled Vue 3 SPA assets — no Vite/Mix required in host application
- PHP test suite (Pest) — 64 tests covering SSRF, storage, proxy, collections, environments
- JS test suite (Vitest) — 20 tests covering composables
