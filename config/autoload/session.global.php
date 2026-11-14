<?php

/**
 * Session cookie settings, for mezzio-session-ext.
 *
 * Browsers refuse to store a secure cookie sent over plain HTTP, and the CSRF tokens are stored in the session, so the
 * contact form fails over HTTP. Use HTTPS everywhere, including locally (https://localhost:1443 with Docker).
 */

declare(strict_types=1);

return [
    'session' => [
        'cookie_httponly' => true, // JavaScript cannot read the cookie, so XSS cannot steal the session
        'cookie_secure'   => true, // The cookie is only sent over HTTPS
        'cookie_samesite' => 'Lax', // The cookie is left off cross-site POST requests
    ],
];
