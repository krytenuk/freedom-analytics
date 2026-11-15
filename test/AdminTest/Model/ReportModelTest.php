<?php

declare(strict_types=1);

namespace AdminTest\Model;

use Admin\Model\ReportModel;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function array_column;

final class ReportModelTest extends TestCase
{
    public function testPerMinuteCountsEachMinuteIncludingEmptyOnes(): void
    {
        $minutes = ReportModel::perMinute(
            ['2026-11-14 23:59:10', '2026-11-14 23:59:59', '2026-11-15 00:01:00'],
            new DateTimeImmutable('2026-11-14 23:58:40'),
            4
        );

        self::assertSame(['23:58', '23:59', '00:00', '00:01'], array_column($minutes, 'minute'));
        self::assertSame([0, 2, 0, 1], array_column($minutes, 'pageviews'));
    }
}
