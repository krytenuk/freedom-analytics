<?php

declare(strict_types=1);

namespace App\Tracking;

use function explode;
use function preg_match;
use function str_contains;
use function str_replace;
use function trim;

/**
 * Picks out the browser, operating system and kind of device from a User-Agent header, and spots robots. It knows the
 * common browsers rather than every one, as Open Web Analytics' browscap does: anything else is reported as "Other".
 */
final class UserAgentParser
{
    private const BOT_PATTERN = '~bot\b|bot/|crawl|spider|slurp|archiver|facebookexternalhit|embedly|preview|'
        . 'headless|lighthouse|pagespeed|pingdom|uptime|monitor|curl/|wget/|python-|go-http-client|java/|okhttp|'
        . 'httpclient|libwww|scrapy|phantomjs|bingpreview|mediapartners|feedfetcher|validator~i';

    /**
     * Browsers, most specific first, as many browsers' User-Agents also name Chrome and Safari
     *
     * @var array<string, string> Name => pattern whose first group is the version
     */
    private const BROWSERS = [
        'Edge'              => '~Edg(?:e|A|iOS)?/([\d.]+)~',
        'Opera'             => '~(?:OPR|Opera|OPiOS)/([\d.]+)~',
        'Samsung Internet'  => '~SamsungBrowser/([\d.]+)~',
        'Vivaldi'           => '~Vivaldi/([\d.]+)~',
        'Yandex Browser'    => '~YaBrowser/([\d.]+)~',
        'UC Browser'        => '~UCBrowser/([\d.]+)~',
        'Firefox'           => '~(?:Firefox|FxiOS)/([\d.]+)~',
        'Chrome'            => '~(?:Chrome|CriOS)/([\d.]+)~',
        'Safari'            => '~Version/([\d.]+).*Safari/~',
        'Internet Explorer' => '~(?:MSIE |Trident/.*rv:)([\d.]+)~',
    ];

    private const WINDOWS_VERSIONS = [
        '10.0' => '10',
        '6.3'  => '8.1',
        '6.2'  => '8',
        '6.1'  => '7',
        '6.0'  => 'Vista',
        '5.1'  => 'XP',
    ];

    public function parse(?string $userAgent): ClientInfo
    {
        $userAgent = trim((string) $userAgent);
        if ($userAgent === '') {
            return new ClientInfo(null, null, null, null, ClientInfo::DEVICE_DESKTOP, true);
        }

        [$browser, $browserVersion] = $this->browser($userAgent);
        [$os, $osVersion]           = $this->os($userAgent);

        return new ClientInfo(
            $browser,
            $browserVersion,
            $os,
            $osVersion,
            $this->deviceType($userAgent),
            preg_match(self::BOT_PATTERN, $userAgent) === 1,
        );
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function browser(string $userAgent): array
    {
        foreach (self::BROWSERS as $name => $pattern) {
            if (preg_match($pattern, $userAgent, $matches) === 1) {
                return [$name, $this->majorVersion($matches[1])];
            }
        }

        return ['Other', null];
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function os(string $userAgent): array
    {
        return match (true) {
            preg_match('~Windows NT ([\d.]+)~', $userAgent, $m) === 1
                => ['Windows', self::WINDOWS_VERSIONS[$m[1]] ?? $m[1]],
            preg_match('~(?:iPhone|iPad|iPod).*? OS ([\d_]+)~', $userAgent, $m) === 1
                => ['iOS', $this->majorVersion(str_replace('_', '.', $m[1]))],
            preg_match('~Android ([\d.]+)~', $userAgent, $m) === 1
                => ['Android', $this->majorVersion($m[1])],
            str_contains($userAgent, 'Android')                   => ['Android', null],
            str_contains($userAgent, 'CrOS')                      => ['Chrome OS', null],
            preg_match('~Mac OS X ([\d_.]+)~', $userAgent, $m) === 1
                => ['macOS', $this->majorVersion(str_replace('_', '.', $m[1]), 2)],
            str_contains($userAgent, 'Macintosh')                 => ['macOS', null],
            str_contains($userAgent, 'Windows')                   => ['Windows', null],
            str_contains($userAgent, 'Ubuntu')                    => ['Ubuntu', null],
            str_contains($userAgent, 'Linux')                     => ['Linux', null],
            default                                               => [null, null],
        };
    }

    private function deviceType(string $userAgent): string
    {
        if (preg_match('~iPad|Tablet|PlayBook|Silk|Kindle|(Android(?!.*Mobile))~i', $userAgent) === 1) {
            return ClientInfo::DEVICE_TABLET;
        }

        if (preg_match('~Mobi|iPhone|iPod|Android|BlackBerry|Opera Mini|IEMobile|Windows Phone~i', $userAgent) === 1) {
            return ClientInfo::DEVICE_MOBILE;
        }

        return ClientInfo::DEVICE_DESKTOP;
    }

    /**
     * The first part of a version, or the first two for operating systems such as macOS 10.15
     */
    private function majorVersion(string $version, int $parts = 1): string
    {
        $pieces = explode('.', $version);

        return $parts === 1 || ! isset($pieces[1]) || $pieces[0] !== '10'
            ? $pieces[0]
            : $pieces[0] . '.' . $pieces[1];
    }
}
