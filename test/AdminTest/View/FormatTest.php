<?php

declare(strict_types=1);

namespace AdminTest\View;

use Admin\View\Format;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormatTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function agoProvider(): array
    {
        return [
            'moments ago'      => ['12:00:55', 'just now'],
            'seconds'          => ['12:00:15', '45s ago'],
            'minutes'          => ['11:57:01', '3m ago'],
            'hours'            => ['10:55:00', '1h 06m ago'],
            'clock a bit fast' => ['12:01:05', 'just now'],
        ];
    }

    #[DataProvider('agoProvider')]
    public function testAgo(string $time, string $expected): void
    {
        self::assertSame(
            $expected,
            Format::ago(new DateTimeImmutable('2026-11-15 ' . $time), new DateTimeImmutable('2026-11-15 12:01:00'))
        );
    }
}
