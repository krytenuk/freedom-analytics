<?php

declare(strict_types=1);

namespace App\Tracking;

/**
 * One tracking request from a visitor's browser: a page view or an event
 */
final readonly class Hit
{
    public const TYPE_PAGEVIEW = 'pageview';
    public const TYPE_EVENT    = 'event';

    public function __construct(
        public string $type,
        public string $siteKey,
        public string $url,
        public ?string $visitorKey,
        public ?string $title = null,
        public ?string $referrer = null,
        public ?string $screenResolution = null,
        public ?string $language = null,
        public ?string $eventCategory = null,
        public ?string $eventAction = null,
        public ?string $eventLabel = null,
        public ?float $eventValue = null,
    ) {
    }

    public function isEvent(): bool
    {
        return $this->type === self::TYPE_EVENT;
    }
}
