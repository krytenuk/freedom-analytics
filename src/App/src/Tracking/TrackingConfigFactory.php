<?php

declare(strict_types=1);

namespace App\Tracking;

use Psr\Container\ContainerInterface;

use function is_array;

final class TrackingConfigFactory
{
    public function __invoke(ContainerInterface $container): TrackingConfig
    {
        $config    = $container->get('config');
        $analytics = is_array($config) && is_array($config['analytics'] ?? null) ? $config['analytics'] : [];

        return TrackingConfig::fromArray($analytics);
    }
}
