<?php

declare(strict_types=1);

namespace App;

use App\Auth\UserRolesProvider;
use App\Command\CreateUserCommand;
use App\Command\DemoDataCommand;
use App\Command\PruneCommand;
use App\Tracking\CollectHandler;
use App\Tracking\CollectHandlerFactory;
use App\Tracking\HitParser;
use App\Tracking\HitRecorder;
use App\Tracking\HitRecorderFactory;
use App\Tracking\TrackingConfig;
use App\Tracking\TrackingConfigFactory;
use App\Tracking\TrafficSourceClassifier;
use App\Tracking\UrlNormalizer;
use App\Tracking\UserAgentParser;
use Doctrine\ORM\EntityManagerInterface;
use FwsDoctrineAuth\Mezzio\RolesProviderInterface;
use FwsDoctrineAuth\Password\PasswordHasherInterface;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Mezzio\Application;
use Mezzio\Container\ApplicationConfigInjectionDelegator;

/**
 * The tracking side of the application: the entities, the /collect endpoint that the tracker sends hits to, and the
 * console commands. The reports are in the Admin module.
 *
 * @psalm-api
 */
final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies'               => $this->getDependencies(),
            'templates'                  => $this->getTemplates(),
            'routes'                     => $this->getRoutes(),
            'laminas-cli'                => $this->getCliConfig(),
            ConfigAbstractFactory::class => $this->getConfigAbstractFactoryConfig(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'aliases'    => [
                // Puts each user's role on the logged-in Mezzio\Authentication\UserInterface
                RolesProviderInterface::class => UserRolesProvider::class,
            ],
            'invokables' => [
                HitParser::class               => HitParser::class,
                TrafficSourceClassifier::class => TrafficSourceClassifier::class,
                UrlNormalizer::class           => UrlNormalizer::class,
                UserAgentParser::class         => UserAgentParser::class,
                UserRolesProvider::class       => UserRolesProvider::class,
            ],
            'factories'  => [
                CollectHandler::class    => CollectHandlerFactory::class,
                CreateUserCommand::class => ConfigAbstractFactory::class,
                DemoDataCommand::class   => ConfigAbstractFactory::class,
                HitRecorder::class       => HitRecorderFactory::class,
                PruneCommand::class      => ConfigAbstractFactory::class,
                TrackingConfig::class    => TrackingConfigFactory::class,
            ],
            // Adds the "routes" config of every module to the application
            'delegators' => [
                Application::class => [ApplicationConfigInjectionDelegator::class],
            ],
        ];
    }

    public function getConfigAbstractFactoryConfig(): array
    {
        return [
            CreateUserCommand::class => [EntityManagerInterface::class, PasswordHasherInterface::class],
            DemoDataCommand::class   => [
                EntityManagerInterface::class,
                HitRecorder::class,
                UserAgentParser::class,
                TrackingConfig::class,
            ],
            PruneCommand::class      => [EntityManagerInterface::class, TrackingConfig::class],
        ];
    }

    public function getCliConfig(): array
    {
        return [
            'commands' => [
                CreateUserCommand::NAME => CreateUserCommand::class,
                DemoDataCommand::NAME   => DemoDataCommand::class,
                PruneCommand::NAME      => PruneCommand::class,
            ],
        ];
    }

    public function getTemplates(): array
    {
        return [
            'paths' => [
                'error' => [__DIR__ . '/../templates/error'],
            ],
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                // The tracker sends hits here. GET is the image fallback, POST is navigator.sendBeacon(), and OPTIONS
                // answers CORS preflight requests.
                'path'            => '/collect',
                'middleware'      => CollectHandler::class,
                'name'            => 'collect',
                'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
            ],
        ];
    }
}
