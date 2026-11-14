# Freedom Analytics

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

```bash
cp .env.dist .env                                    # set the passwords
cp config/autoload/local.php.dist config/autoload/local.php   # the same database password, and the secrets
cp config/development.config.php.dist config/development.config.php   # development mode, optional
docker compose up -d --build
composer install
bin/doctrine migrations:migrate
vendor/bin/laminas analytics:user --email=you@example.com --name="Your Name" --role=admin
```

Then open https://localhost:1453 (the certificate is self-signed), log in, and add a site. The session cookie is
secure only, so use HTTPS locally too.

Commands run from the host reach the database on port 13307. Inside the container, `docker compose` sets `DB_HOST`
and `DB_PORT` for `local.php`.

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
