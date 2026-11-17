<?php

declare(strict_types=1);

namespace App\Tracking;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use MaxMind\Db\Reader;
use RuntimeException;
use Throwable;

use function curl_errno;
use function curl_error;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt_array;
use function dirname;
use function file_put_contents;
use function gzdecode;
use function intdiv;
use function is_dir;
use function is_string;
use function mkdir;
use function octdec;
use function rename;
use function rtrim;
use function sprintf;
use function str_ends_with;
use function strlen;
use function substr;
use function trim;
use function unlink;

use const CURLINFO_RESPONSE_CODE;
use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_MAXREDIRS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_USERAGENT;
use const CURLOPT_USERPWD;

/**
 * Downloads the country database that CountryLookup reads, and swaps it in only once it has been checked, so a failed
 * download never leaves the server without one. From MaxMind (GeoLite2 Country) when an account id and licence key are
 * set, otherwise from DB-IP (IP to Country Lite), which needs no account.
 */
final class GeoIpUpdater
{
    public const SOURCE_MAXMIND = 'maxmind';
    public const SOURCE_DBIP    = 'dbip';

    private const MAXMIND_URL = 'https://download.maxmind.com/geoip/databases/GeoLite2-Country/download?suffix=tar.gz';
    private const DBIP_URL    = 'https://download.db-ip.com/free/dbip-country-lite-%s.mmdb.gz';

    /** @var Closure(string, ?string): string */
    private readonly Closure $fetch;

    /**
     * @param string|null $databasePath Where CountryLookup reads the database (the geoip_database setting)
     * @param Closure(string $url, ?string $credentials): string|null $fetch Downloads a URL, sending the
     *     "user:password" credentials when given, and throws a RuntimeException whose code is the HTTP status when it
     *     fails. Defaults to cURL.
     */
    public function __construct(
        private readonly ?string $databasePath,
        private readonly string $maxmindAccountId = '',
        private readonly string $maxmindLicenceKey = '',
        ?Closure $fetch = null,
    ) {
        $this->fetch = $fetch ?? self::curl(...);
    }

    /**
     * MaxMind when its account id and licence key are set, otherwise DB-IP
     */
    public function defaultSource(): string
    {
        return $this->maxmindAccountId !== '' && $this->maxmindLicenceKey !== ''
            ? self::SOURCE_MAXMIND
            : self::SOURCE_DBIP;
    }

    /**
     * Downloads and installs the database, and returns a description of it, such as "GeoLite2-Country built 14 Nov
     * 2026"
     */
    public function update(string $source, DateTimeImmutable $now): string
    {
        if ($this->databasePath === null) {
            throw new RuntimeException('The geoip_database setting is empty, so there is nowhere to put the database.');
        }

        $database = match ($source) {
            self::SOURCE_MAXMIND => $this->maxmind(),
            self::SOURCE_DBIP    => $this->dbip($now),
            default              => throw new RuntimeException(sprintf('Unknown source "%s".', $source)),
        };

        return $this->install($database);
    }

    private function maxmind(): string
    {
        if ($this->defaultSource() !== self::SOURCE_MAXMIND) {
            throw new RuntimeException(
                'Set maxmind_account_id and maxmind_licence_key in the "analytics" config to download from MaxMind.'
            );
        }

        $archive = $this->gunzip(($this->fetch)(self::MAXMIND_URL, $this->maxmindAccountId . ':'
            . $this->maxmindLicenceKey));

        return self::fileFromTar($archive, '.mmdb')
            ?? throw new RuntimeException('The MaxMind download has no .mmdb file in it.');
    }

    /**
     * This month's file, or last month's early in a month, before DB-IP has published the new one
     */
    private function dbip(DateTimeImmutable $now): string
    {
        $month = $now->setTimezone(new DateTimeZone('UTC'));

        try {
            return $this->gunzip(($this->fetch)(sprintf(self::DBIP_URL, $month->format('Y-m')), null));
        } catch (RuntimeException $exception) {
            if ($exception->getCode() !== 404) {
                throw $exception;
            }
        }

        $previous = $month->modify('first day of last month');

        return $this->gunzip(($this->fetch)(sprintf(self::DBIP_URL, $previous->format('Y-m')), null));
    }

    /**
     * Checks the file opens as a MaxMind DB, then moves it into place in one step, so lookups never see half a file
     */
    private function install(string $database): string
    {
        $path      = (string) $this->databasePath;
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Could not create %s.', $directory));
        }

        $temporary = $path . '.download';
        if (file_put_contents($temporary, $database) !== strlen($database)) {
            throw new RuntimeException(sprintf('Could not write %s.', $temporary));
        }

        try {
            $reader   = new Reader($temporary);
            $metadata = $reader->metadata();
            $reader->close();
        } catch (Throwable $exception) {
            unlink($temporary);

            throw new RuntimeException('The download is not a MaxMind DB file: ' . $exception->getMessage());
        }

        if (! rename($temporary, $path)) {
            unlink($temporary);

            throw new RuntimeException(sprintf('Could not replace %s.', $path));
        }

        return sprintf(
            '%s built %s',
            $metadata->databaseType,
            (new DateTimeImmutable('@' . $metadata->buildEpoch))->format('j M Y')
        );
    }

    private function gunzip(string $data): string
    {
        $decoded = @gzdecode($data);
        if (! is_string($decoded)) {
            throw new RuntimeException('The download is not a gzip file.');
        }

        return $decoded;
    }

    /**
     * The first regular file in a tar archive whose name ends with the suffix
     */
    private static function fileFromTar(string $tar, string $suffix): ?string
    {
        $offset = 0;
        while ($offset + 512 <= strlen($tar)) {
            $header = substr($tar, $offset, 512);
            $name   = rtrim(substr($header, 0, 100), "\0");
            if ($name === '') {
                // Two empty blocks end the archive
                return null;
            }

            $size = (int) octdec(trim(substr($header, 124, 12), " \0"));
            $type = $header[156];
            if (($type === '0' || $type === "\0") && str_ends_with($name, $suffix)) {
                return substr($tar, $offset + 512, $size);
            }

            $offset += 512 + intdiv($size + 511, 512) * 512;
        }

        return null;
    }

    private static function curl(string $url, ?string $credentials): string
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            // MaxMind redirects to its storage; cURL sends the credentials only to the host they were given for
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_USERAGENT      => 'freedom-analytics',
        ] + ($credentials === null ? [] : [CURLOPT_USERPWD => $credentials]));

        $body   = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if (! is_string($body) || curl_errno($curl) !== 0) {
            throw new RuntimeException(sprintf('Could not download %s: %s', $url, curl_error($curl)));
        }

        if ($status >= 400) {
            throw new RuntimeException(sprintf('Could not download %s: HTTP %d', $url, $status), $status);
        }

        return $body;
    }
}
