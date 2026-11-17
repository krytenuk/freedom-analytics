<?php

declare(strict_types=1);

namespace AppTest\Tracking;

use function chr;
use function count;
use function pack;
use function strlen;

/**
 * Builds MaxMind DB files for tests
 */
final class TestDatabase
{
    /**
     * The smallest IPv4 MaxMind DB file: one node, sending 0.0.0.0/1 to {country: {iso_code: GB}} and leaving
     * 128.0.0.0/1 empty. See https://maxmind.github.io/MaxMind-DB/
     */
    public static function build(): string
    {
        $nodeCount = 1;
        // Records of 24 bits: a value above the node count points into the data section, equal to it means no data
        $tree = self::uint24($nodeCount + 16) . self::uint24($nodeCount);
        $data = self::map(['country' => self::map(['iso_code' => self::text('GB')])]);

        $metadata = self::map([
            'binary_format_major_version' => self::uint(5, 2),
            'binary_format_minor_version' => self::uint(5, 0),
            'build_epoch'                 => chr(8) . chr(9 - 7) . pack('J', 1_700_000_000),
            'database_type'               => self::text('Test-Country'),
            'description'                 => self::map([]),
            'ip_version'                  => self::uint(5, 4),
            'languages'                   => chr(0) . chr(11 - 7),
            'node_count'                  => self::uint(6, $nodeCount),
            'record_size'                 => self::uint(5, 24),
        ]);

        return $tree . "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0" . $data . "\xAB\xCD\xEFMaxMind.com" . $metadata;
    }

    private static function uint24(int $value): string
    {
        return chr($value >> 16 & 0xFF) . chr($value >> 8 & 0xFF) . chr($value & 0xFF);
    }

    /**
     * An unsigned integer of the given type (5 for uint16, 6 for uint32), in one byte
     */
    private static function uint(int $type, int $value): string
    {
        return chr($type << 5 | 1) . chr($value);
    }

    private static function text(string $value): string
    {
        return chr(2 << 5 | strlen($value)) . $value;
    }

    /**
     * @param array<string, string> $entries Encoded values, by key
     */
    private static function map(array $entries): string
    {
        $encoded = chr(7 << 5 | count($entries));
        foreach ($entries as $key => $value) {
            $encoded .= self::text($key) . $value;
        }

        return $encoded;
    }
}
