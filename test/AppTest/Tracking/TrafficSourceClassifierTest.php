<?php

declare(strict_types=1);

namespace AppTest\Tracking;

use App\Entity\Site;
use App\Entity\Visit;
use App\Tracking\TrafficSourceClassifier;
use PHPUnit\Framework\TestCase;

final class TrafficSourceClassifierTest extends TestCase
{
    private Site $site;

    private TrafficSourceClassifier $classifier;

    protected function setUp(): void
    {
        $this->site       = (new Site())->setDomain('www.example.com');
        $this->classifier = new TrafficSourceClassifier();
    }

    public function testNoReferrerIsDirect(): void
    {
        $source = $this->classifier->classify($this->site, 'https://www.example.com/', null);

        self::assertSame(Visit::MEDIUM_DIRECT, $source->medium);
    }

    public function testTheSitesOwnPagesAreDirect(): void
    {
        $source = $this->classifier->classify($this->site, 'https://www.example.com/b', 'https://shop.example.com/a');

        self::assertSame(Visit::MEDIUM_DIRECT, $source->medium);
    }

    public function testSearchEnginesWithTerms(): void
    {
        $source = $this->classifier->classify(
            $this->site,
            'https://www.example.com/',
            'https://www.bing.com/search?q=web+design'
        );

        self::assertSame(Visit::MEDIUM_ORGANIC, $source->medium);
        self::assertSame('Bing', $source->source);
        self::assertSame('web design', $source->searchTerms);
    }

    public function testSocialNetworks(): void
    {
        $source = $this->classifier->classify($this->site, 'https://www.example.com/', 'https://t.co/abc');

        self::assertSame(Visit::MEDIUM_SOCIAL, $source->medium);
        self::assertSame('X (Twitter)', $source->source);
    }

    public function testOtherSitesAreReferrals(): void
    {
        $source = $this->classifier->classify($this->site, 'https://www.example.com/', 'https://www.blog.test/post');

        self::assertSame(Visit::MEDIUM_REFERRAL, $source->medium);
        self::assertSame('blog.test', $source->source);
        self::assertSame('blog.test', $source->referrerHost);
    }

    public function testCampaignParametersWin(): void
    {
        $source = $this->classifier->classify(
            $this->site,
            'https://www.example.com/?utm_source=newsletter&utm_medium=email&utm_campaign=autumn&utm_content=top',
            'https://www.google.com/'
        );

        self::assertSame(Visit::MEDIUM_CAMPAIGN, $source->medium);
        self::assertSame('newsletter', $source->source);
        self::assertSame('autumn', $source->campaign);
        self::assertSame('top', $source->campaignContent);
    }
}
