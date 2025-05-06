<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services->set(WB\Parser\Api\WildberriesApi::class)
        ->args([
            '%env(PARSER_USER_AGENT)%',
            '%env(int:PARSER_MAX_RETRIES)%',
            '%env(int:PARSER_REQUEST_DELAY)%',
        ]);

    $services->set(WB\Parser\Producer\ProducerInterface::class)
        ->factory([WB\Parser\Factory\ProducerFactory::class, 'create'])
        ->args(['%env(bool:USE_MOCK_PRODUCER)%', 'output']);

    $services->set(WB\Parser\Service\ProductService::class)
        ->args([
            service(WB\Parser\Api\WildberriesApi::class),
            service(WB\Parser\Producer\ProducerInterface::class),
        ]);

    $services->set(WB\Parser\Service\ProductParsingService::class)
        ->args([
            service(WB\Parser\Service\ProductService::class),
        ]);

    $services->set(WB\Parser\Command\ParseAllProductsCommand::class)
        ->args([
            service(WB\Parser\Service\ProductParsingService::class),
            service(WB\Parser\Service\ProductService::class),
        ])
        ->tag('console.command');

    $services->set(WB\Parser\Command\ParseAllCommand::class)
        ->tag('console.command');

    $services->set(WB\Parser\Command\ParseBrandsCommand::class)
        ->tag('console.command');

    $services->set(WB\Parser\Command\ParseProductsCommand::class)
        ->tag('console.command');

    $services->set(WB\Parser\Command\MigrateCommand::class)
        ->tag('console.command');
};
