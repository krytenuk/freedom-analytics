<?php

declare(strict_types=1);

namespace Admin\View;

use DateTimeImmutable;

use function array_column;
use function array_map;
use function array_slice;
use function array_sum;
use function count;
use function is_string;
use function preg_match;

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
        $shown  = self::slices($result['rows'], $slices);

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
     * Countries for the world map, coloured as donut() colours its slices: each of the donut's countries takes its
     * slice's colour slot, and every other country with visits takes "Other" (slot null). Rows without a country, such
     * as "(unknown)", keep their slot but have nowhere on the map.
     *
     * @param array{rows: list<array<string, mixed>>, primary: string} $result Every country, not one page of them
     * @return array{
     *     regions: array<string, array{label: string, value: int|float, slot: int|null}>,
     *     name: string,
     *     format: string
     * }
     */
    public static function map(array $result, string $name, int $slices = 5): array
    {
        $metric  = $result['primary'];
        $shown   = count(self::slices($result['rows'], $slices));
        $regions = [];
        foreach ($result['rows'] as $index => $row) {
            $code = $row['key'] ?? null;
            if (! is_string($code) || preg_match('~^[A-Z]{2}$~', $code) !== 1) {
                continue;
            }

            $regions[$code] = [
                'label' => (string) $row['label'],
                'value' => $row[$metric],
                'slot'  => $index < $shown ? $index : null,
            ];
        }

        return ['regions' => $regions, 'name' => $name, 'format' => 'number'];
    }

    /**
     * The rows that get their own slice. Six rows are all shown, as folding one into "Other" would hide nothing.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function slices(array $rows, int $slices): array
    {
        return count($rows) > $slices + 1 ? array_slice($rows, 0, $slices) : $rows;
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
