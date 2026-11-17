<?php

declare(strict_types=1);

namespace App\Tracking;

use Psr\Container\ContainerInterface;

final class CountryLookupFactory
{
    public function __invoke(ContainerInterface $container): CountryLookup
    {
        $config = $container->get(TrackingConfig::class);

        return new CountryLookup($config instanceof TrackingConfig ? $config->geoipDatabase : null);
    }
}
