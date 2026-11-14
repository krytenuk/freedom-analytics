<?php

declare(strict_types=1);

namespace App\Tracking;

use function filter_var;
use function in_array;
use function is_numeric;
use function is_string;
use function mb_check_encoding;
use function mb_substr;
use function preg_match;
use function preg_replace;
use function strtolower;
use function trim;

use const FILTER_VALIDATE_URL;

/**
 * Reads a tracking request's parameters, which the tracker sends in the query string or the body:
 *
 *   s   site key (required)            t   "pageview" (default) or "event"
 *   u   page address (required)        ti  page title
 *   r   referrer                       v   visitor id from the tracker's cookie
 *   sr  screen resolution, 1920x1080   la  browser language
 *   ec  event category                 ea  event action (required for events)
 *   el  event label                    ev  event value
 *
 * Returns null for a request that cannot be recorded.
 */
final class HitParser
{
    /**
     * @param array<string, mixed> $params
     */
    public function parse(array $params): ?Hit
    {
        $type    = $this->string($params, 't', 10) ?? Hit::TYPE_PAGEVIEW;
        $siteKey = $this->string($params, 's', 32);
        $url     = $this->string($params, 'u', 2048);

        if (
            ! in_array($type, [Hit::TYPE_PAGEVIEW, Hit::TYPE_EVENT], true)
            || $siteKey === null || preg_match('~^[a-f0-9]{8,32}$~', $siteKey) !== 1
            || $url === null || filter_var($url, FILTER_VALIDATE_URL) === false
            || preg_match('~^https?://~i', $url) !== 1
        ) {
            return null;
        }

        $visitorKey = $this->string($params, 'v', 64);
        if ($visitorKey !== null && preg_match('~^[A-Za-z0-9_-]{8,64}$~', $visitorKey) !== 1) {
            $visitorKey = null;
        }

        $screen = $this->string($params, 'sr', 20);
        if ($screen !== null && preg_match('~^\d{2,5}x\d{2,5}$~', $screen) !== 1) {
            $screen = null;
        }

        $language = $this->string($params, 'la', 20);
        $language = $language === null ? null : strtolower((string) preg_replace('~[^A-Za-z-]~', '', $language));

        $eventCategory = null;
        $eventAction   = null;
        if ($type === Hit::TYPE_EVENT) {
            $eventAction = $this->string($params, 'ea', 100);
            if ($eventAction === null) {
                return null;
            }
            $eventCategory = $this->string($params, 'ec', 100) ?? 'General';
        }

        $value = $params['ev'] ?? null;

        return new Hit(
            $type,
            $siteKey,
            $url,
            $visitorKey,
            $this->string($params, 'ti', 255),
            $this->string($params, 'r', 2048),
            $screen,
            $language === '' ? null : $language,
            $eventCategory,
            $eventAction,
            $type === Hit::TYPE_EVENT ? $this->string($params, 'el', 255) : null,
            $type === Hit::TYPE_EVENT && is_numeric($value) ? (float) $value : null,
        );
    }

    /**
     * A trimmed, valid UTF-8 parameter cut to a length, or null if it is missing or empty
     *
     * @param array<string, mixed> $params
     */
    private function string(array $params, string $name, int $maxLength): ?string
    {
        $value = $params[$name] ?? null;
        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        $value = trim(mb_substr($value, 0, $maxLength));

        return $value === '' ? null : $value;
    }
}
