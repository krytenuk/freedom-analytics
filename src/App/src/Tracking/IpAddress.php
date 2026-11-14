<?php

declare(strict_types=1);

namespace App\Tracking;

use function filter_var;
use function inet_ntop;
use function inet_pton;
use function str_ends_with;
use function str_repeat;
use function str_starts_with;
use function strlen;
use function substr;

use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

/**
 * Helpers for visitors' IP addresses
 */
final class IpAddress
{
    /**
     * Zeroes the last octet of an IPv4 address, or the last 80 bits of an IPv6 one, so the address no longer identifies
     * a single household. Returns null for anything that is not an IP address.
     */
    public static function anonymize(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $packed = (string) inet_pton($ip);

            return (string) inet_ntop(substr($packed, 0, 3) . "\0");
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $packed = (string) inet_pton($ip);

            return (string) inet_ntop(substr($packed, 0, 6) . str_repeat("\0", strlen($packed) - 6));
        }

        return null;
    }

    public static function isValid(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Whether the address is one of these, which can also be IPv4 ranges ending in a "*", such as 192.168.1.*
     *
     * @param list<string> $list
     */
    public static function matches(string $ip, array $list): bool
    {
        foreach ($list as $entry) {
            if ($entry === $ip) {
                return true;
            }

            if (str_ends_with($entry, '*') && str_starts_with($ip, substr($entry, 0, -1))) {
                return true;
            }
        }

        return false;
    }
}
