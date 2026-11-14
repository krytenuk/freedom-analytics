<?php

declare(strict_types=1);

namespace App\Tracking;

/**
 * What UserAgentParser found out about a visitor's browser
 */
final readonly class ClientInfo
{
    public const DEVICE_DESKTOP = 'desktop';
    public const DEVICE_MOBILE  = 'mobile';
    public const DEVICE_TABLET  = 'tablet';

    public function __construct(
        public ?string $browser,
        public ?string $browserVersion,
        public ?string $os,
        public ?string $osVersion,
        public string $deviceType,
        public bool $isBot,
    ) {
    }
}
