<?php

declare(strict_types=1);

namespace Admin\Report;

use DateInterval;
use DateTimeImmutable;

use function array_key_exists;
use function preg_match;
use function sprintf;

/**
 * The date range a report covers: one of the presets, or a custom range
 */
final readonly class Period
{
    public const DEFAULT = '30d';

    public const PRESETS = [
        'today'      => 'Today',
        'yesterday'  => 'Yesterday',
        '7d'         => 'Last 7 days',
        '30d'        => 'Last 30 days',
        '90d'        => 'Last 90 days',
        'this-month' => 'This month',
        'last-month' => 'Last month',
        'this-year'  => 'This year',
        '12m'        => 'Last 12 months',
        'custom'     => 'Custom range',
    ];

    /** The longest custom range, so a mistyped year cannot make a report read everything */
    private const MAX_DAYS = 3660;

    private function __construct(
        public string $key,
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
    ) {
    }

    /**
     * @param string|null $from Y-m-d, for a custom range
     * @param string|null $to Y-m-d, for a custom range
     */
    public static function create(string $key, ?string $from, ?string $to, DateTimeImmutable $today): self
    {
        $today = $today->setTime(0, 0);
        if (! array_key_exists($key, self::PRESETS)) {
            $key = self::DEFAULT;
        }

        if ($key === 'custom') {
            $fromDate = self::date($from);
            $toDate   = self::date($to);
            if ($fromDate === null || $toDate === null) {
                return self::create(self::DEFAULT, null, null, $today);
            }
            if ($fromDate > $toDate) {
                [$fromDate, $toDate] = [$toDate, $fromDate];
            }
            if ($fromDate->diff($toDate)->days > self::MAX_DAYS) {
                $fromDate = $toDate->sub(new DateInterval('P' . self::MAX_DAYS . 'D'));
            }

            return new self($key, $fromDate, $toDate);
        }

        [$fromDate, $toDate] = match ($key) {
            'today'      => [$today, $today],
            'yesterday'  => [$today->modify('-1 day'), $today->modify('-1 day')],
            '7d'         => [$today->modify('-6 days'), $today],
            '90d'        => [$today->modify('-89 days'), $today],
            'this-month' => [$today->modify('first day of this month'), $today],
            'last-month' => [$today->modify('first day of last month'), $today->modify('last day of last month')],
            'this-year'  => [$today->setDate((int) $today->format('Y'), 1, 1), $today],
            '12m'        => [$today->modify('-1 year +1 day'), $today],
            default      => [$today->modify('-29 days'), $today],
        };

        return new self($key, $fromDate, $toDate);
    }

    private static function date(?string $value): ?DateTimeImmutable
    {
        if ($value === null || preg_match('~^\d{4}-\d{2}-\d{2}$~', $value) !== 1) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    public function getLabel(): string
    {
        if ($this->key !== 'custom') {
            return self::PRESETS[$this->key];
        }

        return $this->getDays() === 1
            ? $this->from->format('j M Y')
            : sprintf('%s – %s', $this->from->format('j M Y'), $this->to->format('j M Y'));
    }

    public function getDays(): int
    {
        return (int) $this->from->diff($this->to)->days + 1;
    }

    /**
     * The same number of days, just before this period, to compare with
     */
    public function previous(): self
    {
        $to   = $this->from->modify('-1 day');
        $from = $to->sub(new DateInterval('P' . ($this->getDays() - 1) . 'D'));

        return new self('custom', $from, $to);
    }
}
