<?php

declare(strict_types=1);

namespace Admin\View;

use DateTimeInterface;

use function abs;
use function intdiv;
use function max;
use function number_format;
use function round;
use function sprintf;

/**
 * How report numbers are shown
 */
final class Format
{
    public static function number(int|float $value): string
    {
        return number_format((float) $value);
    }

    public static function decimal(int|float $value): string
    {
        return number_format((float) $value, 2);
    }

    /**
     * @param int|float $value A fraction, such as 0.25 for 25%
     */
    public static function percent(int|float $value, int $decimals = 1): string
    {
        return number_format((float) $value * 100, $decimals) . '%';
    }

    /**
     * Seconds as 1h 02m 03s, 2m 05s or 45s
     */
    public static function duration(int|float $seconds): string
    {
        $seconds = (int) round($seconds);
        if ($seconds < 60) {
            return $seconds . 's';
        }

        $hours   = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $rest    = $seconds % 60;

        return $hours > 0
            ? sprintf('%dh %02dm %02ds', $hours, $minutes, $rest)
            : sprintf('%dm %02ds', $minutes, $rest);
    }

    /**
     * How long ago something happened, briefly: "just now", "45s ago", "3m ago" or "1h 05m ago"
     */
    public static function ago(DateTimeInterface $time, DateTimeInterface $now): string
    {
        $seconds = max(0, $now->getTimestamp() - $time->getTimestamp());
        if ($seconds < 10) {
            return 'just now';
        }
        if ($seconds < 60) {
            return $seconds . 's ago';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60) . 'm ago';
        }

        return sprintf('%dh %02dm ago', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    /**
     * @param string $format number, decimal, percent or duration, as in ReportModel::METRICS
     */
    public static function metric(int|float|null $value, string $format): string
    {
        $value ??= 0;

        return match ($format) {
            'percent'  => self::percent($value),
            'duration' => self::duration($value),
            'decimal'  => self::decimal($value),
            default    => self::number($value),
        };
    }

    /**
     * The change from the previous period, as a fraction, or null if there was nothing before
     */
    public static function change(int|float $current, int|float $previous): ?float
    {
        if ((float) $previous === 0.0) {
            return null;
        }

        return ($current - $previous) / abs($previous);
    }
}
