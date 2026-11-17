<?php

declare(strict_types=1);

namespace AdminTest\View;

use Admin\View\ChartData;
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_map;
use function range;

final class ChartDataTest extends TestCase
{
    public function testTheMapColoursCountriesAsTheDonutColoursItsSlices(): void
    {
        $codes  = ['GB', null, 'US', 'FR', 'DE', 'IE', 'ES', 'NL'];
        $result = [
            'primary' => 'visits',
            'totals'  => ['visits' => 360],
            'rows'    => array_map(static fn (int $index): array => [
                'key'    => $codes[$index],
                'label'  => $codes[$index] ?? '(unknown)',
                'visits' => 80 - $index * 10,
            ], range(0, 7)),
        ];

        $donut = ChartData::donut($result, 'Visits');
        $map   = ChartData::map($result, 'Visits');

        self::assertSame(['GB', '(unknown)', 'US', 'FR', 'DE', 'Other'], $donut['labels']);
        // Each donut country keeps its slice's slot; "(unknown)" has no place on the map but keeps its slot
        self::assertSame(
            ['GB' => 0, 'US' => 2, 'FR' => 3, 'DE' => 4, 'IE' => null, 'ES' => null, 'NL' => null],
            array_map(static fn (array $region): ?int => $region['slot'], $map['regions'])
        );
        self::assertSame(80, $map['regions']['GB']['value']);
    }

    public function testSixCountriesAllGetTheirOwnSlot(): void
    {
        $result = [
            'primary' => 'visits',
            'totals'  => ['visits' => 18],
            'rows'    => array_map(static fn (string $code): array => [
                'key'    => $code,
                'label'  => $code,
                'visits' => 3,
            ], ['GB', 'US', 'FR', 'DE', 'IE', 'ES']),
        ];

        self::assertSame([0, 1, 2, 3, 4, 5], array_column(ChartData::map($result, 'Visits')['regions'], 'slot'));
        self::assertNotContains('Other', ChartData::donut($result, 'Visits')['labels']);
    }
}
