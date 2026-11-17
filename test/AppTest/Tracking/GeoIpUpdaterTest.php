<?php

declare(strict_types=1);

namespace AppTest\Tracking;

use App\Tracking\CountryLookup;
use App\Tracking\GeoIpUpdater;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_get_contents;
use function glob;
use function gzencode;
use function is_dir;
use function rmdir;
use function sprintf;
use function str_contains;
use function str_pad;
use function str_repeat;
use function strlen;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const STR_PAD_LEFT;

final class GeoIpUpdaterTest extends TestCase
{
    private string $directory;

    private string $path;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/' . uniqid('fa-geoip-', true);
        $this->path      = $this->directory . '/GeoLite2-Country.mmdb';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testUsesMaxMindOnlyWhenItsCredentialsAreSet(): void
    {
        self::assertSame(GeoIpUpdater::SOURCE_DBIP, (new GeoIpUpdater($this->path))->defaultSource());
        self::assertSame(GeoIpUpdater::SOURCE_DBIP, (new GeoIpUpdater($this->path, '123'))->defaultSource());
        self::assertSame(GeoIpUpdater::SOURCE_MAXMIND, (new GeoIpUpdater($this->path, '123', 'key'))->defaultSource());
    }

    public function testInstallsTheMaxMindDatabaseFromItsArchive(): void
    {
        $requests = [];
        $updater  = new GeoIpUpdater($this->path, '123', 'key', static function (
            string $url,
            ?string $credentials
        ) use (&$requests): string {
            $requests[] = [$url, $credentials];

            return (string) gzencode(
                self::tar('GeoLite2-Country_20261114/COPYRIGHT.txt', 'Copyright')
                . self::tar('GeoLite2-Country_20261114/GeoLite2-Country.mmdb', TestDatabase::build())
                . str_repeat("\0", 1024)
            );
        });

        self::assertSame('Test-Country built 14 Nov 2023', $updater->update('maxmind', new DateTimeImmutable()));
        self::assertSame('123:key', $requests[0][1]);
        self::assertSame('GB', (new CountryLookup($this->path))->country('81.2.69.160'));
    }

    public function testUsesLastMonthsDbIpDatabaseBeforeThisMonthsIsOut(): void
    {
        $urls    = [];
        $updater = new GeoIpUpdater($this->path, fetch: static function (
            string $url,
            ?string $credentials
        ) use (&$urls): string {
            $urls[] = $url;
            if ($credentials === null && str_contains($url, '2026-11')) {
                throw new RuntimeException('Not found', 404);
            }

            return (string) gzencode(TestDatabase::build());
        });

        $updater->update('dbip', new DateTimeImmutable('2026-11-01 08:00:00 UTC'));

        self::assertSame([
            'https://download.db-ip.com/free/dbip-country-lite-2026-11.mmdb.gz',
            'https://download.db-ip.com/free/dbip-country-lite-2026-10.mmdb.gz',
        ], $urls);
        self::assertSame('GB', (new CountryLookup($this->path))->country('81.2.69.160'));
    }

    public function testABadDownloadLeavesTheInstalledDatabaseAlone(): void
    {
        $updater = new GeoIpUpdater($this->path, fetch: static fn (): string => (string) gzencode('not a database'));
        (new GeoIpUpdater($this->path, fetch: static fn (): string => (string) gzencode(TestDatabase::build())))
            ->update('dbip', new DateTimeImmutable());

        try {
            $updater->update('dbip', new DateTimeImmutable());
            self::fail('The bad download was installed');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('not a MaxMind DB file', $exception->getMessage());
        }

        self::assertSame(TestDatabase::build(), file_get_contents($this->path));
        self::assertSame([$this->path], glob($this->directory . '/*'));
    }

    public function testMaxMindNeedsItsCredentials(): void
    {
        $this->expectExceptionMessage('maxmind_account_id');

        (new GeoIpUpdater($this->path, fetch: static fn (): string => ''))->update('maxmind', new DateTimeImmutable());
    }

    /**
     * One file in a tar archive: its 512 byte header, then its contents padded to a whole block
     */
    private static function tar(string $name, string $contents): string
    {
        $header = str_pad($name, 100, "\0")
            . str_repeat("\0", 24)
            . str_pad(sprintf('%o', strlen($contents)), 11, '0', STR_PAD_LEFT) . "\0"
            . str_repeat("\0", 20)
            . '0';
        $header = str_pad($header, 512, "\0");

        return $header . str_pad($contents, (int) (512 * (int) ((strlen($contents) + 511) / 512)), "\0");
    }
}
