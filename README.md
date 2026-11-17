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
- **Visitors:** overview, latest visits with each visit's click stream, locations (with a world map coloured like
  its chart), languages, browsers, operating
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

Put the tracking code from the site's page just before `</head>` on every page:

```html
<script async src="https://analytics.example.com/fa.js" data-site="SITE_KEY"></script>
```

`SITE_KEY` is the site's public key, shown with the tracking code on the site's page in the admin area. The tracker
sends hits to `collect` next to wherever `fa.js` was loaded from, so it needs no other setting. Including the script
twice on a page does no harm: only the first copy runs.

### Options

Options are attributes of the script tag. An attribute is on when it is present, with or without a value, and off
when it is missing or set to `"false"`:

```html
<script async src="https://analytics.example.com/fa.js" data-site="SITE_KEY" data-spa data-outbound data-downloads></script>
```

| Attribute | What it does |
|---|---|
| `data-site` | The site key. Required: without it nothing is sent. |
| `data-cookieless` | Keeps no visitor id in a cookie. Visitors are counted by a hash that changes daily instead. |
| `data-spa` | Records a page view each time a single page app changes the address. |
| `data-outbound` | Records clicks on links to other sites as events. |
| `data-downloads` | Records clicks on links to files as events. |
| `data-manual` | Records no page view when the page loads; the page calls `fa('pageview')` itself. |

**`data-cookieless`.** By default the tracker keeps a random visitor id in a first party cookie, `_fa_vid`, on the
tracked site's own domain. It lasts a year from the latest visit, and is `SameSite=Lax` and, on HTTPS pages,
`Secure`. With `data-cookieless`, no cookie is set, and the server identifies visitors by a hash of the site, the day,
their IP address and their browser, which it never stores. A visitor is then a new visitor each day, so the loyalty
and recency reports, and the split between new and returning visitors, are less accurate. Browsers that send Do Not
Track or Global Privacy Control never get the cookie, whatever this setting.

**`data-spa`.** Single page apps change the address with the History API instead of loading a new page. With
`data-spa`, the tracker records a page view after each `history.pushState()` and `history.replaceState()`, and when
the visitor goes back or forward. The previous address is sent as the referrer, so the content reports show how
visitors moved between the app's pages. A change of fragment alone, such as a jump to `#section`, is not a new page
view. Load the script once, in the app's shell, not with each view.

**`data-outbound`.** Records a click on a link to another host, over `http` or `https`, as an event with the category
`Outbound Links`, the action `Click` and the link's address as the label. Subdomains count as other hosts, so on
`www.example.com` a link to `shop.example.com` is outbound.

**`data-downloads`.** Records a click on a link to a file as an event with the category `Downloads`, the action
`Download` and the file's address as the label. A file is a link whose path ends in one of: `pdf`, `zip`, `rar`, `7z`,
`gz`, `doc`, `docx`, `xls`, `xlsx`, `ppt`, `pptx`, `csv`, `txt`, `rtf`, `odt`, `ods`, `mp3`, `mp4`, `mov`, `avi`,
`dmg`, `exe`, `msi`, `apk` and `epub`. A download from another site counts as a download, not an outbound click.

Both appear in the Actions reports. Clicks are caught on the whole document, so links added after the page loads are
included too.

**`data-manual`.** For pages that need to choose what is recorded, such as when the address shown to the visitor is
not the one to report, or the page should only count once some condition is met. Nothing is recorded until the page
calls `fa('pageview')`; events can still be sent.

### Recording page views and events from the page

Copy this line once, before any `fa(...)` calls. It lets the page call `fa()` before `fa.js` has loaded: the calls
are queued, and sent in order when it has.

```html
<script>
  window.fa = window.fa || function () { (fa.q = fa.q || []).push(arguments); };
</script>
```

**Page views:**

```js
fa('pageview');                          // the current address and title
fa('pageview', {
    url: 'https://example.com/basket',   // the address to record, which must be a full http or https address
    title: 'Basket',                     // the page title
    referrer: 'https://example.com/',    // where the visitor came from
    force: true                          // record it even if it is the same page as the last page view
});
```

Every option can be left out. Without `force`, a page view of the same address as the one before it (ignoring the
fragment) is skipped, so a page is not counted twice.

**Events:**

```js
fa('event', category, action, label, value);

fa('event', 'Forms', 'Submit', 'Contact form');
fa('event', 'Video', 'Play', 'Product tour', 30);
document.querySelector('#signup').addEventListener('click', function () {
    fa('event', 'Buttons', 'Click', 'Sign up');
});
```

| Argument | Required | Notes |
|---|---|---|
| `category` | No | Groups events, such as `Forms` or `Video`. `General` if left out. Up to 100 characters. |
| `action` | Yes | What happened, such as `Submit` or `Play`. An event without one is not sent. Up to 100 characters. |
| `label` | No | Which one, such as the form's or video's name. Up to 255 characters. |
| `value` | No | A number, such as a duration or an amount. Anything that is not a number is ignored. |

Longer text is cut to fit. Each event is linked to the visit and to the page it happened on.

### What is recorded

Each page view sends the page's address and title, the referrer, the screen resolution and the browser's language.
The server adds the browser, operating system and device from the User-Agent header, the country (see
[Countries](#countries)), and the IP address, anonymised unless that is turned off.

Pages are reported by their address without the fragment, and without campaign and click id parameters (`utm_*`,
`gclid`, `fbclid`, `msclkid`, `dclid`, `yclid`, `mc_cid`, `mc_eid` and `_ga`), so one page is not split into many.
The `utm_` parameters on a visit's first page are read before they are removed, for the campaign reports. Other query
parameters are kept, sorted by name.

### Countries

The country comes from the country header that a CDN or proxy in front of the analytics server adds, such as
Cloudflare's `CF-IPCountry`. Without one, it is looked up from the visitor's full address, before it is anonymised, in
a MaxMind DB file at `data/geoip/GeoLite2-Country.mmdb` (the `geoip_database` setting). While there is no file, hits
are still recorded, without a country. Private addresses, such as a development machine's, have no country.

`vendor/bin/laminas analytics:geoip-update` downloads it. By default it fetches DB-IP's free IP to Country Lite, which
needs no account. To use MaxMind's GeoLite2 Country instead, create a free MaxMind account and set
`maxmind_account_id` and `maxmind_licence_key` in the `analytics` section of `local.php`; the command then uses
MaxMind (or pass `--source=maxmind` or `--source=dbip`). The new file replaces the old one only once it has been
checked, so a failed download leaves the old one working. It needs PHP's curl and zlib extensions.

Run it from cron, such as every Monday morning, as both databases are updated regularly (DB-IP monthly, MaxMind twice
a week) and the GeoLite2 licence requires keeping it up to date:

```cron
30 4 * * 1  cd /path/to/freedom-analytics && vendor/bin/laminas analytics:geoip-update -q
```

DB-IP's database is licensed under CC BY 4.0, which requires crediting DB-IP; the admin area's footer does, with
"IP Geolocation by DB-IP". MaxMind's GeoLite2 licence has its own attribution terms. A file is read on each hit without a country header,
so a new one takes effect straight away.

### Site settings that affect tracking

These are set on the site's edit page in the admin area:

- **Paused:** a site that is not collecting records nothing.
- **Other domains:** by default, hits are refused unless the page is on the site's domain or one of its subdomains. A
  leading `www.` on the domain is ignored, so `www.example.com` also accepts `example.com` and `shop.example.com`.
  Turn the check off to track the same site on other domains, such as a staging copy.
- **Do Not Track:** when respected (the default), visitors whose browsers send Do Not Track or Global Privacy Control
  are not recorded at all.
- **Excluded IPs:** addresses whose visits are not recorded, such as your office's, separated by spaces, commas or
  new lines. An IPv4 range can end in `*`, such as `192.168.1.*`. Behind a proxy or load balancer, set the trusted
  proxy header in the configuration, or every visitor appears to come from the proxy's address.

### Content Security Policy

On a site with a Content Security Policy, allow the analytics server in `script-src` (for `fa.js`), `connect-src`
(for `sendBeacon()`) and `img-src` (for the image fallback):

```
Content-Security-Policy: script-src 'self' https://analytics.example.com; connect-src 'self' https://analytics.example.com; img-src 'self' https://analytics.example.com
```

If the page's own `fa(...)` calls are in an inline script, they need the policy's nonce or hash as usual.

### Checking it works

The tracker sends hits to `/collect` with `navigator.sendBeacon()`, or an image request in older browsers.
`/collect` gives the same answer whether a hit is recorded or not, so it does not reveal which site keys exist, but
its `X-Analytics` response header says what happened. Look for the `collect` request in the browser's developer
tools (Network tab):

| `X-Analytics` | Meaning |
|---|---|
| `ok` | Recorded. |
| `invalid` | A required parameter is missing or malformed, such as the site key, the page address, or an event's action. |
| `unknown-site` | No site has that key, or the site is paused. |
| `wrong-domain` | The page is not on the site's domain, and other domains are refused. |
| `do-not-track` | The browser sent Do Not Track or Global Privacy Control, and the site respects it. |
| `robot` | The User-Agent is a known robot, crawler or headless browser. |
| `excluded-ip` | The visitor's IP address is in the site's excluded IPs. |
| `error` | The hit could not be saved; the reason is in the application's log. |

The Live page shows recorded page views within seconds. If nothing appears, check that the browser is not blocking
the script (some content blockers do), and that you are not testing from an excluded address or with Do Not Track on.

## Commands

```bash
vendor/bin/laminas analytics:user          # add a user, or set an existing user's password
vendor/bin/laminas analytics:demo-data KEY # fill a site with made-up visits, to try the reports
vendor/bin/laminas analytics:prune         # delete data older than retention_days (run from cron)
bin/doctrine migrations:migrate            # update the database
```

## Configuration

`config/autoload/analytics.global.php` holds the settings, overridden in `local.php`: the reporting timezone, the
visit timeout, IP anonymisation, the country header (Cloudflare's `CF-IPCountry` by default), the GeoIP database used when there is no
country header (see [Countries](#countries)), a trusted proxy header and data retention.

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
- [Bootstrap](https://getbootstrap.com/), [Bootstrap Icons](https://icons.getbootstrap.com/),
  [ApexCharts](https://apexcharts.com/) and [jsVectorMap](https://github.com/themustafaomar/jsvectormap) 1.7.0 (with
  its world map), in `public/assets/admin/vendor`, under the MIT licence.
- The PHP packages installed by Composer, each under the licence in its `composer.json`.
