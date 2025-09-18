<?php

declare(strict_types=1);

use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Infrastructure\Repository\PdoProductRepository;

use App\Infrastructure\Db\ConnectionFactory;

// ne pas mettre => use PDO;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        LoggerInterface::class => function (ContainerInterface $c) {
            $settings = $c->get(SettingsInterface::class);

            $loggerSettings = $settings->get('logger');
            $logger = new Logger($loggerSettings['name']);

            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

            $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
            $logger->pushHandler($handler);

            return $logger;
        },
    ]);

    $containerBuilder->addDefinitions([
        \PDO::class => function (): \PDO {
            return ConnectionFactory::makeFromEnv();
        },
    ]);


    return function (\DI\ContainerBuilder $containerBuilder) {
        $containerBuilder->addDefinitions([
            PDO::class => function () {
                return \App\Infrastructure\Db\ConnectionFactory::makeFromEnv();
            },

            ProductRepositoryInterface::class => function (\Psr\Container\ContainerInterface $c) {
                return new PdoProductRepository($c->get(PDO::class));
            },
        ]);
    };


};
