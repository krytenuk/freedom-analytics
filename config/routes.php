<?php

declare(strict_types=1);

use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;

/**
 * The routes are in each module's ConfigProvider::getRoutes(), and added by ApplicationConfigInjectionDelegator.
 */
return static function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
};
