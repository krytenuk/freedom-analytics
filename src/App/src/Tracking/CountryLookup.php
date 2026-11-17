<?php

declare(strict_types=1);

namespace App\Tracking;

use MaxMind\Db\Reader;
use Throwable;

use function is_array;
use function is_file;
use function is_string;
use function preg_match;
use function strtoupper;

/**
 * Finds a visitor's country from their IP address, in a MaxMind DB file such as MaxMind's GeoLite2 Country (or City)
 * or DB-IP's IP to Country Lite. Used when no country header came with the hit.
 *
 * Returns null, rather than failing the hit, when there is no database, the address is not in it (private addresses
 * never are) or the file cannot be read. The database is opened on the first lookup, so hits that already have a
 * country never open it.
 */
final class CountryLookup
{
    private ?Reader $reader = null;

    private bool $opened = false;

    /**
     * @param string|null $databasePath The .mmdb file, relative to the application's root or absolute; null for none
     */
    public function __construct(private readonly ?string $databasePath)
    {
    }

    /**
     * The country's two letter code, upper case, or null when it is not known
     */
    public function country(string $ip): ?string
    {
        $reader = $this->reader();
        if ($reader === null) {
            return null;
        }

        try {
            $record = $reader->get($ip);
        } catch (Throwable) {
            // Not an address, or an IPv6 address in an IPv4 only database
            return null;
        }

        if (! is_array($record)) {
            return null;
        }

        // Some ranges, such as anycast ones, have only the country the block is registered to
        foreach (['country', 'registered_country'] as $key) {
            $code = is_array($record[$key] ?? null) ? $record[$key]['iso_code'] ?? null : null;
            if (is_string($code) && preg_match('~^[A-Z]{2}$~', strtoupper($code)) === 1) {
                return strtoupper($code);
            }
        }

        return null;
    }

    private function reader(): ?Reader
    {
        if (! $this->opened) {
            $this->opened = true;
            if ($this->databasePath !== null && is_file($this->databasePath)) {
                try {
                    $this->reader = new Reader($this->databasePath);
                } catch (Throwable) {
                    // Unreadable or not a MaxMind DB file; countries are left out until it is replaced
                    $this->reader = null;
                }
            }
        }

        return $this->reader;
    }
}
