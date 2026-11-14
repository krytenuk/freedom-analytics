<?php

declare(strict_types=1);

namespace AdminTest\Report;

use Admin\Report\Period;
use Admin\View\Format;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PeriodTest extends TestCase
{
    private DateTimeImmutable $today;

    protected function setUp(): void
    {
        $this->today = new DateTimeImmutable('2026-11-14');
    }

    public function testPresets(): void
    {
        $period = Period::create('7d', null, null, $this->today);
        self::assertSame('2026-11-08', $period->from->format('Y-m-d'));
        self::assertSame('2026-11-14', $period->to->format('Y-m-d'));
        self::assertSame(7, $period->getDays());

        $lastMonth = Period::create('last-month', null, null, $this->today);
        self::assertSame('2026-10-01', $lastMonth->from->format('Y-m-d'));
        self::assertSame('2026-10-31', $lastMonth->to->format('Y-m-d'));
    }

    public function testTheComparisonPeriodIsJustBefore(): void
    {
        $previous = Period::create('7d', null, null, $this->today)->previous();

        self::assertSame('2026-11-01', $previous->from->format('Y-m-d'));
        self::assertSame('2026-11-07', $previous->to->format('Y-m-d'));
    }

    public function testCustomRangesAreCheckedAndPutInOrder(): void
    {
        $period = Period::create('custom', '2026-09-30', '2026-09-01', $this->today);
        self::assertSame('2026-09-01', $period->from->format('Y-m-d'));
        self::assertSame('2026-09-30', $period->to->format('Y-m-d'));

        self::assertSame(Period::DEFAULT, Period::create('custom', '2026-02-30', '2026-03-01', $this->today)->key);
        self::assertSame(Period::DEFAULT, Period::create('nonsense', null, null, $this->today)->key);
    }

    public function testFormatsMetrics(): void
    {
        self::assertSame('45s', Format::duration(45));
        self::assertSame('3m 05s', Format::duration(185));
        self::assertSame('1h 01m 01s', Format::duration(3661));
        self::assertSame('41.7%', Format::percent(0.4167));
        self::assertSame(0.5, Format::change(150, 100));
        self::assertNull(Format::change(5, 0));
    }
}
