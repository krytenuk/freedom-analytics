<?php

declare(strict_types=1);

namespace App\Tracking;

use App\Entity\Site;
use App\Entity\Visit;

use function is_string;
use function mb_substr;
use function parse_str;
use function parse_url;
use function preg_match;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

use const PHP_URL_HOST;
use const PHP_URL_QUERY;

/**
 * Works out how a visitor arrived, as Open Web Analytics' traffic attribution does: from a campaign in the page's
 * address (utm_ parameters), a search engine, a social network, another site, or directly.
 */
final class TrafficSourceClassifier
{
    /**
     * Search engines by a pattern of their host name, and the query parameters that hold the search terms
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private const SEARCH_ENGINES = [
        '~(^|\.)google\.~'         => ['Google', ['q']],
        '~(^|\.)bing\.com$~'       => ['Bing', ['q']],
        '~(^|\.)yahoo\.~'          => ['Yahoo', ['p', 'q']],
        '~(^|\.)duckduckgo\.com$~' => ['DuckDuckGo', ['q']],
        '~(^|\.)baidu\.com$~'      => ['Baidu', ['wd', 'word']],
        '~(^|\.)yandex\.~'         => ['Yandex', ['text']],
        '~(^|\.)ecosia\.org$~'     => ['Ecosia', ['q']],
        '~(^|\.)ask\.com$~'        => ['Ask', ['q']],
        '~^search\.brave\.com$~'   => ['Brave Search', ['q']],
        '~(^|\.)startpage\.com$~'  => ['Startpage', ['query', 'q']],
        '~(^|\.)qwant\.com$~'      => ['Qwant', ['q']],
        '~(^|\.)aol\.~'            => ['AOL', ['q', 'query']],
    ];

    /** @var array<string, string> */
    private const SOCIAL_NETWORKS = [
        '~(^|\.)(facebook\.com|fb\.me|fb\.com)$~'       => 'Facebook',
        '~(^|\.)instagram\.com$~'                       => 'Instagram',
        '~^(t\.co|(.+\.)?twitter\.com|(.+\.)?x\.com)$~' => 'X (Twitter)',
        '~(^|\.)(linkedin\.com|lnkd\.in)$~'             => 'LinkedIn',
        '~(^|\.)pinterest\.~'                           => 'Pinterest',
        '~(^|\.)reddit\.com$~'                          => 'Reddit',
        '~(^|\.)(youtube\.com|youtu\.be)$~'             => 'YouTube',
        '~(^|\.)tiktok\.com$~'                          => 'TikTok',
        '~(^|\.)threads\.net$~'                         => 'Threads',
        '~(^|\.)bsky\.app$~'                            => 'Bluesky',
        '~(^|\.)whatsapp\.com$~'                        => 'WhatsApp',
    ];

    public function classify(Site $site, string $pageUrl, ?string $referrer): TrafficSource
    {
        $referrer     = $this->cleanReferrer($referrer);
        $referrerHost = $referrer === null ? null : $this->host($referrer);

        $campaign = $this->campaign($pageUrl);
        if ($campaign !== null) {
            return new TrafficSource(
                Visit::MEDIUM_CAMPAIGN,
                $campaign['source'] ?? $referrerHost,
                $referrer,
                $referrerHost,
                $campaign['campaign'] ?? null,
                $campaign['content'] ?? null,
                $campaign['term'] ?? null,
            );
        }

        // No referrer, or another page of the same site, as when an earlier visit timed out
        if ($referrer === null || $referrerHost === null || $site->acceptsHost($referrerHost)) {
            return new TrafficSource(Visit::MEDIUM_DIRECT);
        }

        foreach (self::SEARCH_ENGINES as $pattern => [$name, $parameters]) {
            if (preg_match($pattern, $referrerHost) === 1) {
                return new TrafficSource(
                    Visit::MEDIUM_ORGANIC,
                    $name,
                    $referrer,
                    $referrerHost,
                    searchTerms: $this->searchTerms($referrer, $parameters),
                );
            }
        }

        foreach (self::SOCIAL_NETWORKS as $pattern => $name) {
            if (preg_match($pattern, $referrerHost) === 1) {
                return new TrafficSource(Visit::MEDIUM_SOCIAL, $name, $referrer, $referrerHost);
            }
        }

        return new TrafficSource(Visit::MEDIUM_REFERRAL, $referrerHost, $referrer, $referrerHost);
    }

    /**
     * The utm_ campaign parameters in the page's address, or null if it has none
     *
     * @return array<string, string>|null
     */
    private function campaign(string $pageUrl): ?array
    {
        $query = parse_url($pageUrl, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $parameters);
        $campaign = [];
        foreach (['source', 'medium', 'campaign', 'content', 'term'] as $name) {
            $value = $parameters['utm_' . $name] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $campaign[$name] = mb_substr(trim($value), 0, 255);
            }
        }

        return isset($campaign['source']) || isset($campaign['campaign']) ? $campaign : null;
    }

    /**
     * @param list<string> $parameters
     */
    private function searchTerms(string $referrer, array $parameters): ?string
    {
        $query = parse_url($referrer, PHP_URL_QUERY);
        if (! is_string($query)) {
            return null;
        }

        parse_str($query, $values);
        foreach ($parameters as $parameter) {
            $value = $values[$parameter] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return mb_substr(trim($value), 0, 255);
            }
        }

        // Most search engines no longer pass the search terms on
        return null;
    }

    private function cleanReferrer(?string $referrer): ?string
    {
        $referrer = trim((string) $referrer);
        if ($referrer === '' || ! preg_match('~^https?://~i', $referrer)) {
            return null;
        }

        return substr($referrer, 0, 2048);
    }

    private function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
