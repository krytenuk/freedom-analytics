<?php

declare(strict_types=1);

namespace App\Tracking;

/**
 * Where a visit came from, as TrafficSourceClassifier works it out
 */
final readonly class TrafficSource
{
    /**
     * @param string $medium One of the App\Entity\Visit::MEDIUM_ constants
     */
    public function __construct(
        public string $medium,
        public ?string $source = null,
        public ?string $referrerUrl = null,
        public ?string $referrerHost = null,
        public ?string $campaign = null,
        public ?string $campaignContent = null,
        public ?string $searchTerms = null,
    ) {
    }
}
