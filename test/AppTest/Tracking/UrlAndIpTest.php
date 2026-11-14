<?php

declare(strict_types=1);

namespace AppTest\Tracking;

use App\Entity\Site;
use App\Tracking\IpAddress;
use App\Tracking\UrlNormalizer;
use PHPUnit\Framework\TestCase;

final class UrlAndIpTest extends TestCase
{
    public function testUrlsLoseFragmentsAndCampaignParameters(): void
    {
        $url = (new UrlNormalizer())->normalize('HTTPS://WWW.Example.com/shop?utm_source=x&b=2&gclid=1&a=1#top');

        self::assertNotNull($url);
        self::assertSame('https://www.example.com/shop?a=1&b=2', $url['url']);
        self::assertSame('/shop?a=1&b=2', $url['path']);
        self::assertSame('www.example.com', $url['host']);
    }

    public function testAnonymisesAddresses(): void
    {
        self::assertSame('203.0.113.0', IpAddress::anonymize('203.0.113.77'));
        self::assertSame('2001:db8:85a3::', IpAddress::anonymize('2001:db8:85a3:8d3:1319:8a2e:370:7348'));
        self::assertNull(IpAddress::anonymize('not an ip'));
    }

    public function testMatchesExcludedAddresses(): void
    {
        self::assertTrue(IpAddress::matches('192.168.1.20', ['10.0.0.1', '192.168.1.*']));
        self::assertTrue(IpAddress::matches('10.0.0.1', ['10.0.0.1']));
        self::assertFalse(IpAddress::matches('192.168.10.1', ['192.168.1.*']));
    }

    public function testSitesAcceptTheirDomainAndSubdomains(): void
    {
        $site = (new Site())->setDomain('www.example.com');

        self::assertTrue($site->acceptsHost('www.example.com'));
        self::assertTrue($site->acceptsHost('example.com'));
        self::assertTrue($site->acceptsHost('shop.example.com'));
        self::assertFalse($site->acceptsHost('example.com.evil.test'));
        self::assertFalse($site->acceptsHost('notexample.com'));
    }
}
