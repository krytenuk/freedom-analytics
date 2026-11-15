# Freedom Analytics

[![Tests](https://github.com/krytenuk/freedom-analytics/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/krytenuk/freedom-analytics/actions/workflows/tests.yml?query=branch%3Amain)

Self-hosted web analytics, built with [Mezzio](https://docs.mezzio.dev/) and Doctrine ORM. It covers the core of
[Open Web Analytics](https://github.com/Open-Web-Analytics/Open-Web-Analytics): a first party JavaScript tracker,
visits, visitors, page views and events, many sites in one install, and a reporting dashboard for several users. The
interface uses the same NiceAdmin template as the Freedom Web Services admin area, and logins come from
`krytenuk/doctrine-auth`, with optional authenticator app two factor authentication.

## What it records

| Open Web Analytics | Here | Table |
|---|---|---|
| Site | A tracked website, found by its public site key | `sites` |
| Visitor | A browser, by a random id in a first party cookie | `visitors` |
| Session | A visit: hits with no gap longer than 30 minutes | `visits` |
| Document | A page, by its address without fragment or campaign parameters | `pages` |
| Request | A page view | `pageviews` |
| Action | An event, from `fa('event', category, action, label, value)` | `events` |

Each visit stores where the visitor came from (direct, search engine, referring site, social network or `utm_`
campaign), their browser, operating system, device, screen, language and country, so most reports read only the
`visits` table.

**Privacy:** IP addresses are anonymised before they are stored (configurable), robots are left out, sites can refuse
visitors who send Do Not Track or Global Privacy Control, and IP addresses can be excluded. Visitors whose browsers
block the cookie are counted by a hash that changes daily. The tracking endpoint never sets a cookie.

## Reports

- **Dashboard:** headline numbers against the previous period, the trend, visitors active now, traffic sources,
  devices, top pages, referrers, countries, events and the latest visits
- **Live:** who is on the site now, updated every 10 seconds: visitors active in the last 5 minutes, the pages they
  are on, page views each minute, the visits of the last 30 minutes and the latest page views and events
- **Visitors:** overview, latest visits with each visit's click stream, locations, languages, browsers, operating
  systems, devices, screen resolutions, loyalty, recency and visit times
- **Traffic:** sources, all sources, referring sites, search engines, search terms, social networks and campaigns
- **Content:** pages (each with its own report, including where visitors came from and went next), page titles,
  entry pages and exit pages
- **Actions:** event categories, events and event labels

Every report has a site menu and a period (presets or a custom range), which are remembered as you move between them.
Dimension reports are sortable and paged; the reports are defined in `src/Admin/src/Report/Reports.php`, and their
SQL in `src/Admin/src/Model/ReportModel.php`.

Administrators manage sites (with the tracking code) and users. Viewers see only the sites they are given.

## Getting started with Docker

1. **Set the database credentials.** Copy `.env.dist` to `.env` and set `MARIADB_DATABASE`, `MARIADB_USER`,
   `MARIADB_PASSWORD` and `MARIADB_ROOT_PASSWORD`:

   ```bash
   cp .env.dist .env
   ```

2. **Point the application at the same database.** Copy `local.php.dist`, then set `dbname`, `user` and
   `password` to the `MARIADB_DATABASE`, `MARIADB_USER` and `MARIADB_PASSWORD` from `.env`. Also replace each
   `your_secret` with a random value, such as from `php -r 'echo bin2hex(random_bytes(32));'`:

   ```bash
   cp config/autoload/local.php.dist config/autoload/local.php
   ```

   Leave `host` and `port` as they are (see [Database host](#database-host) below).

3. **Start the containers, install, and create the tables and the first administrator:**

   ```bash
   cp config/development.config.php.dist config/development.config.php   # development mode, optional
   docker compose up -d --build
   composer install
   bin/doctrine migrations:migrate
   vendor/bin/laminas analytics:user --email=you@example.com --name="Your Name" --role=admin
   ```

Then open https://localhost:1453 (the certificate is self-signed), log in, and add a site. The session cookie is
secure only, so use HTTPS locally too.

### Database host

The database is reached at a different address depending on where PHP runs, so `local.php` reads it from the
`DB_HOST` and `DB_PORT` environment variables:

```php
'host' => getenv('DB_HOST') ?: '127.0.0.1',
'port' => (int) (getenv('DB_PORT') ?: 13307),
```

| Where PHP runs | `DB_HOST` / `DB_PORT` | Reaches the database at |
|---|---|---|
| The `app` container (the website) | `db` / `3306`, set in `docker-compose.yml` | The `db` service on the Docker network |
| The host (`bin/doctrine`, `vendor/bin/laminas`, `composer test`) | Not set | `127.0.0.1:13307`, the port `docker-compose.yml` publishes |

So the same `local.php` works in both places with no changes. Commands can also be run inside the container, where
`DB_HOST` is already set:

```bash
docker exec freedom-analytics bin/doctrine migrations:status
```

Outside Docker, such as on a live server, set `host` and `port` in `local.php` directly, or set `DB_HOST` and
`DB_PORT` in the web server's environment.

### Changing the database credentials later

MariaDB only reads the `MARIADB_*` settings in `.env` when its data volume is first created. Changing them afterwards
does nothing to the existing database, and the application gets "Access denied" once `local.php` is updated to match.
Either change the database to match, logged in as root with the root password the volume was created with. For a new
password:

```bash
docker exec -it freedom-analytics-db mariadb -uroot -p \
    -e "ALTER USER 'your_db_user'@'%' IDENTIFIED BY 'the-new-password';"
```

or, if the data does not matter, delete the volume so it is created again from `.env`. **This erases all the
analytics data:**

```bash
docker compose down -v && docker compose up -d
bin/doctrine migrations:migrate
```

## Tracking a site

Put the tracking code from the site's page just before `</head>`:

```html
<script async src="https://analytics.example.com/fa.js" data-site="SITE_KEY"></script>
```

Optional attributes: `data-cookieless`, `data-spa` (single page apps), `data-outbound` (outbound link clicks),
`data-downloads` (file downloads) and `data-manual` (no automatic page view). Events:

```html
<script>
  window.fa = window.fa || function () { (fa.q = fa.q || []).push(arguments); };
  fa('event', 'Forms', 'Submit', 'Contact form');
</script>
```

The tracker sends hits to `/collect` with `navigator.sendBeacon()`, or an image request in older browsers. The
`X-Analytics` response header says why a hit was left out (`wrong-domain`, `robot`, `do-not-track`,
`excluded-ip`, ...), for debugging.

## Commands

```bash
vendor/bin/laminas analytics:user          # add a user, or set an existing user's password
vendor/bin/laminas analytics:demo-data KEY # fill a site with made-up visits, to try the reports
vendor/bin/laminas analytics:prune         # delete data older than retention_days (run from cron)
bin/doctrine migrations:migrate            # update the database
```

## Configuration

`config/autoload/analytics.global.php` holds the settings, overridden in `local.php`: the reporting timezone, the
visit timeout, IP anonymisation, the country header (Cloudflare's `CF-IPCountry` by default; without a CDN that sets
one, countries are not recorded), a trusted proxy header and data retention.

## Development

```bash
composer test       # PHPUnit
composer cs-check   # Laminas coding standard
```

## Licence

Copyright (C) 2026 Freedom Web Services

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the [GNU General Public License](LICENSE) for
more details.

### Third-party files

These are included under their own licences, not the GPL:

- The admin interface's design, `public/assets/admin/css/style.css` and `public/assets/admin/js/main.js` are from the
  [NiceAdmin template](https://bootstrapmade.com/nice-admin-bootstrap-admin-html-template/) by BootstrapMade, under the
  [BootstrapMade licence](https://bootstrapmade.com/license/), which requires the "Designed by BootstrapMade" credit
  in the footer to stay.
- [Bootstrap](https://getbootstrap.com/), [Bootstrap Icons](https://icons.getbootstrap.com/) and
  [ApexCharts](https://apexcharts.com/), in `public/assets/admin/vendor`, under the MIT licence.
- The PHP packages installed by Composer, each under the licence in its `composer.json`.
