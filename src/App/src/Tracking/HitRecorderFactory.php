<?php

declare(strict_types=1);

namespace App\Tracking;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;

final class HitRecorderFactory
{
    public function __invoke(ContainerInterface $container): HitRecorder
    {
        return new HitRecorder(
            $container->get(EntityManagerInterface::class),
            $container->get(TrafficSourceClassifier::class),
            $container->get(UrlNormalizer::class),
            $container->get(TrackingConfig::class),
        );
    }
}
