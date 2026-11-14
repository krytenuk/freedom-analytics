<?php

declare(strict_types=1);

namespace App\Tracking;

use function http_build_query;
use function in_array;
use function is_string;
use function ksort;
use function mb_substr;
use function parse_str;
use function parse_url;
use function str_starts_with;
use function strtolower;

/**
 * Turns a page's address into the one it is reported under: without the fragment, and without campaign and click ids
 * (utm_*, gclid, fbclid and the like), which would otherwise make one page look like many.
 */
final class UrlNormalizer
{
    private const DROPPED_PARAMETERS = ['gclid', 'fbclid', 'msclkid', 'dclid', 'yclid', 'mc_cid', 'mc_eid', '_ga'];

    /**
     * @return array{url: string, path: string, host: string}|null
     */
    public function normalize(string $url): ?array
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        $path = isset($parts['path']) && $parts['path'] !== '' ? $parts['path'] : '/';

        $query = '';
        if (isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $parameters);
            foreach ($parameters as $name => $value) {
                $name = (string) $name;
                if (str_starts_with($name, 'utm_') || in_array($name, self::DROPPED_PARAMETERS, true)) {
                    unset($parameters[$name]);
                }
            }
            ksort($parameters);
            $query = http_build_query($parameters);
        }

        $pathAndQuery = mb_substr($path . ($query === '' ? '' : '?' . $query), 0, 768);
        $port         = isset($parts['port']) ? ':' . $parts['port'] : '';

        return [
            'url'  => strtolower($parts['scheme']) . '://' . $host . $port . $pathAndQuery,
            'path' => $pathAndQuery,
            'host' => $host,
        ];
    }
}
