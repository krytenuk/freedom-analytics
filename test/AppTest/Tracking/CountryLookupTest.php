<?php

declare(strict_types=1);

namespace AppTest\Tracking;

use App\Tracking\CountryLookup;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class CountryLookupTest extends TestCase
{
    private string $database;

    protected function setUp(): void
    {
        $this->database = (string) tempnam(sys_get_temp_dir(), 'fa-geoip');
        file_put_contents($this->database, TestDatabase::build());
    }

    protected function tearDown(): void
    {
        unlink($this->database);
    }

    public function testFindsTheCountryOfAnAddressInTheDatabase(): void
    {
        self::assertSame('GB', (new CountryLookup($this->database))->country('81.2.69.160'));
    }

    public function testAddressesNotInTheDatabaseHaveNoCountry(): void
    {
        $lookup = new CountryLookup($this->database);

        self::assertNull($lookup->country('203.0.113.1'));
        // An IPv6 address cannot be looked up in an IPv4 database
        self::assertNull($lookup->country('2001:db8::1'));
        self::assertNull($lookup->country('not an ip'));
    }

    public function testNoDatabaseMeansNoCountry(): void
    {
        self::assertNull((new CountryLookup(null))->country('81.2.69.160'));
        self::assertNull((new CountryLookup($this->database . '-missing'))->country('81.2.69.160'));

        file_put_contents($this->database, 'not a MaxMind DB file');
        self::assertNull((new CountryLookup($this->database))->country('81.2.69.160'));
    }
}
