<?php

/**
 * Settings for collecting and reporting analytics. Override any of them in local.php.
 */

declare(strict_types=1);

return [
    'analytics' => [
        // Dates are stored and reported in this timezone, so "today" means the same thing in every report
        'timezone' => 'Europe/London',
        // A visit ends after this many minutes without a page view or event, as in Open Web Analytics
        'visit_timeout_minutes' => 30,
        // Zero the last part of visitors' IP addresses before they are stored (the last octet of IPv4, the last 80
        // bits of IPv6)
        'anonymize_ip' => true,
        // The request header holding the visitor's two letter country code, set by a CDN or proxy in front of the
        // site, such as Cloudflare's CF-IPCountry. Null to not record countries.
        'country_header' => 'CF-IPCountry',
        // Raw page views, events and visits older than this many days are deleted by "analytics:prune". 0 keeps them.
        'retention_days' => 0,
    ],
];
