<?php

declare(strict_types=1);

namespace AppTest\Tracking;

use App\Tracking\Hit;
use App\Tracking\HitParser;
use PHPUnit\Framework\TestCase;

final class HitParserTest extends TestCase
{
    public function testParsesAPageview(): void
    {
        $hit = (new HitParser())->parse([
            's'  => '608d2c2114a57846',
            'u'  => 'https://www.example.com/about',
            'v'  => 'abc123def456',
            'ti' => '  About us ',
            'sr' => '1920x1080',
            'la' => 'en-GB',
        ]);

        self::assertNotNull($hit);
        self::assertSame(Hit::TYPE_PAGEVIEW, $hit->type);
        self::assertSame('About us', $hit->title);
        self::assertSame('en-gb', $hit->language);
        self::assertSame('1920x1080', $hit->screenResolution);
        self::assertFalse($hit->isEvent());
    }

    public function testParsesAnEvent(): void
    {
        $hit = (new HitParser())->parse([
            's'  => '608d2c2114a57846',
            't'  => 'event',
            'u'  => 'https://www.example.com/',
            'ea' => 'Download',
            'el' => 'brochure.pdf',
            'ev' => '2.5',
        ]);

        self::assertNotNull($hit);
        self::assertSame('General', $hit->eventCategory);
        self::assertSame('Download', $hit->eventAction);
        self::assertSame(2.5, $hit->eventValue);
    }

    public function testRefusesBadRequests(): void
    {
        $parser = new HitParser();

        self::assertNull($parser->parse([]));
        self::assertNull($parser->parse(['s' => 'not hex!', 'u' => 'https://www.example.com/']));
        self::assertNull($parser->parse(['s' => '608d2c2114a57846', 'u' => 'javascript:alert(1)']));
        self::assertNull($parser->parse(['s' => '608d2c2114a57846', 'u' => 'https://x.test/', 't' => 'event']));
        self::assertNull($parser->parse(['s' => ['array'], 'u' => 'https://x.test/']));
    }

    public function testDropsInvalidOptionalValues(): void
    {
        $hit = (new HitParser())->parse([
            's'  => '608d2c2114a57846',
            'u'  => 'https://www.example.com/',
            'v'  => '<script>',
            'sr' => 'huge',
        ]);

        self::assertNotNull($hit);
        self::assertNull($hit->visitorKey);
        self::assertNull($hit->screenResolution);
    }
}
