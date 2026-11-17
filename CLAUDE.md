# CLAUDE.md

Self-hosted web analytics (Mezzio + Doctrine ORM 2.20, PHP 8.2+, MariaDB), modelled on Open Web Analytics
(`/home/open-web-analytics/Open-Web-Analytics`, a guide only, not copied). The admin UI deliberately follows the
patterns and NiceAdmin template of `/home/freedomw-mezzio/src/Admin`; look there first for how something is done.

## Commands

```bash
vendor/bin/phpunit                 # tests (unit only, no DB needed)
vendor/bin/phpcs                   # coding standard; CI fails on warnings too, so keep it at zero
bin/doctrine migrations:diff       # after changing an entity; then migrations:migrate
bin/doctrine orm:validate-schema
vendor/bin/laminas analytics:user --email=... --role=admin   # create/reset a user (prompts for password)
vendor/bin/laminas analytics:demo-data <site key or id> --days=90
vendor/bin/laminas analytics:prune
vendor/bin/laminas analytics:geoip-update     # country database (DB-IP, or MaxMind with credentials)
docker compose up -d               # app https://localhost:1453 (http 8090), db 127.0.0.1:13307
```

`vendor/bin/phpcbf` often silently fixes nothing here; make fixes by hand. The standard rejects
`/** @var T */ return ...;` — assign to a typed variable, then return it.

## Layout

- `src/App` — tracking side: entities (`Entity/`), `/collect` endpoint and hit pipeline (`Tracking/`), console
  commands, roles provider, error templates, migrations.
- `src/Admin` — reporting UI: handlers, middleware, forms, `Model/ReportModel.php` (all report SQL),
  `Report/Reports.php` (report definitions + sidebar), `View/` (Format, ChartData), templates.
- `public/fa.js` — the embeddable tracker. `public/assets/js/analytics.js` — charts (ApexCharts) and report UI.
- Routes live in each module's `ConfigProvider::getRoutes()`; `config/routes.php` is empty. Services are wired with
  `ConfigAbstractFactory` (list constructor dependencies in `getConfigAbstractFactoryConfig()`).

## How it works

- Hit flow: `CollectHandler` (filters: site key, domain, DNT/GPC, robots, excluded IPs) → `HitParser` →
  `HitRecorder`. `/collect` must never touch the session (no cookie for tracked sites' visitors), and always returns
  the same response; `X-Analytics` header says why a hit was dropped.
- Country: the `country_header` (Cloudflare's `CF-IPCountry`), else `CountryLookup` on the full IP in the MaxMind DB
  file `geoip_database` (`data/geoip/`, gitignored, written by `analytics:geoip-update`; missing file = no country).
  Private IPs, such as Docker's, never resolve. Staging/production are not behind Cloudflare, so they rely on it.
- `HitRecorder` serialises hits per visitor with MariaDB `GET_LOCK` (name hashed, 64-char limit). Without it, a page
  view and an event sent together create two visits and lose counts. Visitors/pages are inserted with plain DBAL
  inserts that ignore duplicate-key errors, so the EntityManager is never closed by a race.
- A visit (OWA "session") ends after 30 minutes idle. Source/browser/device are stored on `visits`, so most reports
  read only that table. `visit_date` is the local date (reporting timezone), so SQL never uses date functions.
- `ReportModel` builds SQL only from its constants (`DIMENSIONS`, `AGGREGATES`); never put request input into SQL.
  Add a report = a `DIMENSIONS` entry + a `Reports::ALL` entry.
- Routes for reports: `AuthenticationMiddleware → AdminLayoutMiddleware → ReportContextMiddleware → handler`.
  `AdminLayoutMiddleware` puts the `User` entity on the request; `ReportContextMiddleware` puts `ReportContext`
  (site + period, remembered in the session). Admin-only pages use `AdminRoleMiddleware` instead.
- Layouts: `layout::admin` (logged in, set by `AdminLayoutMiddleware`) and `layout::auth` (login, errors). Mezzio's
  error handler has its own `layout` setting in `config/autoload/mezzio.global.php`.
- Live page (`LiveHandler`, routes `live` and `live-data`): `analytics.js` polls `live-data` every 10s while the tab is
  visible and swaps in `partial/live-panel` rendered server-side (`layout => false`), so HTML is built only in PHP. A
  non-JSON reply means the session ended (redirect to login) and polling stops. `ReportModel::live()` starts every
  query from visits with recent `last_activity_at` (`idx_visits_site_activity`).
- The Locations report (`'map' => true` in `Reports::ALL`) adds a jsVectorMap world map (`partial/world-map`, scripts
  added by the partial). The handler fetches every country for it, and the donut uses the same rows, so each country
  shares its slice's colour (`ChartData::map()` mirrors `donut()`'s slicing).
- Charts follow the dataviz rules: validated palette in `analytics.js` (light and dark steps), one y-axis only,
  donuts capped at 5 slices + "Other", every chart paired with a table.

## Gotchas

- `config/autoload/local.php` reads `DB_HOST`/`DB_PORT` (set to `db:3306` in the app container; unset on the host →
  `127.0.0.1:13307`). The user's DB is `freedomw_analytics` (user of the same name), not `analytics`.
- MariaDB applies `MARIADB_*` from `.env` only when the volume is first created; changing `.env` later causes
  "Access denied". Fix by `ALTER USER`, or `docker compose down -v` (erases data — ask first).
- The session cookie is Secure-only, so test logged-in pages over HTTPS (`curl -k`), not `php -S`.
- Headless Chrome's User-Agent is filtered as a robot; set a normal UA when testing `fa.js` with Playwright
  (available at `/home/open-web-analytics/Open-Web-Analytics/node_modules/playwright`, with `channel: 'chrome'`).
- NiceAdmin's `main.js` was patched to guard `tinymce.init`; unused NiceAdmin vendor libraries were removed.
- Never commit `.env`, `config/autoload/*local.php` or `config/development.config.php` (gitignored, hold secrets).

## Working with this user

- British English in UI text, docs and comments ("licence", "anonymise").
- Commit or push only when asked; they usually ask straight after a change ("commit and push").
- Repo: `git@github.com:krytenuk/freedom-analytics.git` (public), branch `main`. `gh` is not installed; check CI via
  `https://api.github.com/repos/krytenuk/freedom-analytics/actions/runs`. Licence: GPL-3.0-or-later, © Freedom Web
  Services; NiceAdmin files stay under the BootstrapMade licence and its footer credit must stay.
