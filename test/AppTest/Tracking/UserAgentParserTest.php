<?php

declare(strict_types=1);

namespace AppTest\Tracking;

use App\Tracking\ClientInfo;
use App\Tracking\UserAgentParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserAgentParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string|null, string|null, string, bool}>
     */
    public static function userAgents(): iterable
    {
        yield 'Chrome on Windows' => [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 '
                . 'Safari/537.36',
            'Chrome',
            '130',
            'Windows',
            ClientInfo::DEVICE_DESKTOP,
            false,
        ];
        yield 'Edge, which also names Chrome' => [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 '
                . 'Safari/537.36 Edg/130.0.0.0',
            'Edge',
            '130',
            'Windows',
            ClientInfo::DEVICE_DESKTOP,
            false,
        ];
        yield 'Safari on iPhone' => [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) '
                . 'Version/18.1 Mobile/15E148 Safari/604.1',
            'Safari',
            '18',
            'iOS',
            ClientInfo::DEVICE_MOBILE,
            false,
        ];
        yield 'iPad is a tablet' => [
            'Mozilla/5.0 (iPad; CPU OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 '
                . 'Mobile/15E148 Safari/604.1',
            'Safari',
            '17',
            'iOS',
            ClientInfo::DEVICE_TABLET,
            false,
        ];
        yield 'Chrome on Android phone' => [
            'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile '
                . 'Safari/537.36',
            'Chrome',
            '130',
            'Android',
            ClientInfo::DEVICE_MOBILE,
            false,
        ];
        yield 'Firefox on macOS' => [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:132.0) Gecko/20100101 Firefox/132.0',
            'Firefox',
            '132',
            'macOS',
            ClientInfo::DEVICE_DESKTOP,
            false,
        ];
        yield 'Googlebot' => [
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'Other',
            null,
            null,
            ClientInfo::DEVICE_DESKTOP,
            true,
        ];
        yield 'curl' => ['curl/8.5.0', 'Other', null, null, ClientInfo::DEVICE_DESKTOP, true];
    }

    #[DataProvider('userAgents')]
    public function testParsesUserAgents(
        string $userAgent,
        string $browser,
        ?string $version,
        ?string $os,
        string $device,
        bool $isBot,
    ): void {
        $client = (new UserAgentParser())->parse($userAgent);

        self::assertSame($browser, $client->browser);
        self::assertSame($version, $client->browserVersion);
        self::assertSame($os, $client->os);
        self::assertSame($device, $client->deviceType);
        self::assertSame($isBot, $client->isBot);
    }

    public function testAnEmptyUserAgentIsTreatedAsARobot(): void
    {
        self::assertTrue((new UserAgentParser())->parse('')->isBot);
    }
}
