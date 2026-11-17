<?php

declare(strict_types=1);

namespace App\Tracking;

use Psr\Container\ContainerInterface;

use function is_array;

final class GeoIpUpdaterFactory
{
    public function __invoke(ContainerInterface $container): GeoIpUpdater
    {
        $config    = $container->get('config');
        $analytics = is_array($config) && is_array($config['analytics'] ?? null) ? $config['analytics'] : [];
        $tracking  = $container->get(TrackingConfig::class);

        return new GeoIpUpdater(
            $tracking instanceof TrackingConfig ? $tracking->geoipDatabase : null,
            (string) ($analytics['maxmind_account_id'] ?? ''),
            (string) ($analytics['maxmind_licence_key'] ?? ''),
        );
    }
}
