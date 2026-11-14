<?php

declare(strict_types=1);

namespace App\Tracking;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class CollectHandlerFactory
{
    public function __invoke(ContainerInterface $container): CollectHandler
    {
        $logger = $container->has(LoggerInterface::class) ? $container->get(LoggerInterface::class) : null;

        return new CollectHandler(
            $container->get(EntityManagerInterface::class),
            $container->get(HitParser::class),
            $container->get(HitRecorder::class),
            $container->get(UserAgentParser::class),
            $container->get(TrackingConfig::class),
            $logger instanceof LoggerInterface ? $logger : null,
        );
    }
}
