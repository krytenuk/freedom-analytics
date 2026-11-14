<?php

declare(strict_types=1);

namespace App\Tracking;

use DateTimeZone;

use function date_default_timezone_get;
use function is_string;
use function max;

/**
 * The "analytics" config, as config/autoload/analytics.global.php describes it
 */
final readonly class TrackingConfig
{
    public function __construct(
        public DateTimeZone $timezone,
        public int $visitTimeoutMinutes = 30,
        public bool $anonymizeIp = true,
        public ?string $countryHeader = null,
        public ?string $ipHeader = null,
        public string $visitorHashSecret = '',
        public int $retentionDays = 0,
    ) {
    }

    /**
     * @param array<string, mixed> $config The "analytics" config
     */
    public static function fromArray(array $config): self
    {
        $countryHeader = $config['country_header'] ?? null;
        $ipHeader      = $config['ip_header'] ?? null;

        return new self(
            new DateTimeZone((string) ($config['timezone'] ?? date_default_timezone_get())),
            max(1, (int) ($config['visit_timeout_minutes'] ?? 30)),
            (bool) ($config['anonymize_ip'] ?? true),
            is_string($countryHeader) && $countryHeader !== '' ? $countryHeader : null,
            is_string($ipHeader) && $ipHeader !== '' ? $ipHeader : null,
            (string) ($config['visitor_hash_secret'] ?? ''),
            max(0, (int) ($config['retention_days'] ?? 0)),
        );
    }
}
