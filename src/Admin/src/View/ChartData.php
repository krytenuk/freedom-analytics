<?php

declare(strict_types=1);

namespace Admin\View;

use DateTimeImmutable;

use function array_column;
use function array_map;
use function array_slice;
use function array_sum;
use function count;

/**
 * Turns report results into the data partial/chart.phtml takes
 */
final class ChartData
{
    /**
     * The biggest slices, with the rest folded into "Other", so the colours stay distinguishable
     *
     * @param array{rows: list<array<string, mixed>>, totals: array<string, int|float>, primary: string} $result
     * @return array{labels: list<string>, values: list<int|float>, name: string, format: string}
     */
    public static function donut(array $result, string $name, int $slices = 5): array
    {
        $metric = $result['primary'];
        $rows   = $result['rows'];
        $shown  = count($rows) > $slices + 1 ? array_slice($rows, 0, $slices) : $rows;

        $labels = array_map(static fn (array $row): string => (string) $row['label'], $shown);
        $values = array_map(static fn (array $row): int|float => $row[$metric], $shown);

        $other = (float) ($result['totals'][$metric] ?? 0) - array_sum($values);
        if ($other > 0.5) {
            $labels[] = 'Other';
            $values[] = $other;
        }

        return ['labels' => $labels, 'values' => $values, 'name' => $name, 'format' => 'number'];
    }

    /**
     * @param array{rows: list<array<string, mixed>>, primary: string} $result
     * @return array{labels: list<string>, values: list<int|float>, name: string, format: string}
     */
    public static function bar(array $result, string $name): array
    {
        return [
            'labels' => array_map(static fn (array $row): string => (string) $row['label'], $result['rows']),
            'values' => array_column($result['rows'], $result['primary']),
            'name'   => $name,
            'format' => 'number',
        ];
    }

    /**
     * @param list<array<string, mixed>> $trend Rows with a "date" key
     * @param array<string, string> $series Column => series name, which must share one scale
     * @return array{categories: list<string>, series: list<array{name: string, data: list<int|float>}>, format: string}
     */
    public static function trend(array $trend, array $series, string $format = 'number'): array
    {
        $data = [];
        foreach ($series as $column => $name) {
            $data[] = ['name' => $name, 'data' => array_column($trend, $column)];
        }

        return [
            'categories' => array_map(
                static fn (array $row): string => (new DateTimeImmutable((string) $row['date']))->format('j M'),
                $trend
            ),
            'series'     => $data,
            'format'     => $format,
        ];
    }
}
