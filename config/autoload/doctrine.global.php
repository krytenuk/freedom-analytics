<?php

/**
 * Doctrine ORM, built by roave/psr-container-doctrine.
 *
 * The database connection details (host, port, dbname, user, password) live in local.php, which is not committed.
 * Copy local.php.dist to local.php and fill it in.
 */

declare(strict_types=1);

use Doctrine\DBAL\Driver\PDO\MySQL\Driver as PdoMysqlDriver;
use Doctrine\Migrations\Configuration\Migration\ConfigurationLoader;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Roave\PsrContainerDoctrine\EntityManagerFactory;
use Roave\PsrContainerDoctrine\Migrations\ConfigurationLoaderFactory;
use Roave\PsrContainerDoctrine\Migrations\DependencyFactoryFactory;

return [
    'dependencies' => [
        'aliases'   => [
            // krytenuk/doctrine-auth (and everything else) looks the entity manager up by this name
            EntityManagerInterface::class => 'doctrine.entity_manager.orm_default',
        ],
        'factories' => [
            'doctrine.entity_manager.orm_default' => EntityManagerFactory::class,
            // Doctrine Migrations, used by the migrations:* commands in bin/doctrine
            ConfigurationLoader::class => ConfigurationLoaderFactory::class,
            DependencyFactory::class   => DependencyFactoryFactory::class,
        ],
    ],
    'doctrine'     => [
        'migrations'    => [
            'orm_default' => [
                'table_storage'    => [
                    'table_name' => 'doctrine_migration_versions',
                ],
                'migrations_paths' => [
                    'App\\Migrations' => __DIR__ . '/../../src/App/src/Migrations',
                ],
                // MariaDB commits schema changes immediately, so one transaction cannot cover several migrations
                'all_or_nothing'          => false,
                'check_database_platform' => true,
            ],
        ],
        'connection'    => [
            'orm_default' => [
                'driver_class' => PdoMysqlDriver::class,
                'params'       => [
                    'charset' => 'utf8mb4',
                    // Matches the db service image, so DBAL does not need to connect just to detect the platform
                    'serverVersion' => '10.11.0-MariaDB',
                ],
            ],
        ],
        'configuration' => [
            'orm_default' => [
                'proxy_dir'       => 'data/cache/DoctrineEntityProxy',
                'proxy_namespace' => 'DoctrineEntityProxy',
            ],
        ],
        'driver'        => [
            // One attribute driver for every entity. krytenuk/doctrine-auth and its 2FA plugin add their own
            // src/Entity directories to these paths through their config providers.
            'orm_default' => [
                'class' => AttributeDriver::class,
                'paths' => [
                    __DIR__ . '/../../src/App/src/Entity',
                ],
            ],
        ],
    ],
];
