<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

$container = new ContainerBuilder();

$container
    ->register(WB\Parser\Api\WildberriesApi::class)
    ->setArguments([
        $_ENV['PARSER_USER_AGENT'] ?? 'Wildberries Parser',
        intval($_ENV['PARSER_MAX_RETRIES'] ?? 3),
        intval($_ENV['PARSER_REQUEST_DELAY'] ?? 500),
    ]);

$container
    ->register(WB\Parser\Producer\ProducerInterface::class)
    ->setFactory([WB\Parser\Factory\ProducerFactory::class, 'create'])
    ->setArguments([boolval($_ENV['USE_MOCK_PRODUCER'] ?? false), 'output']);

$container
    ->register(WB\Parser\Service\ProductService::class)
    ->setArguments([
        new Reference(WB\Parser\Api\WildberriesApi::class),
        new Reference(WB\Parser\Producer\ProducerInterface::class),
    ]);

$container
    ->register(WB\Parser\Service\ProductParsingService::class)
    ->setArguments([
        new Reference(WB\Parser\Service\ProductService::class),
    ]);

$container
    ->register(WB\Parser\Command\ParseAllProductsCommand::class)
    ->setArguments([
        new Reference(WB\Parser\Service\ProductParsingService::class),
        new Reference(WB\Parser\Service\ProductService::class),
    ])
    ->addTag('console.command')
    ->setPublic(true);

$container
    ->register(WB\Parser\Command\ParseAllCommand::class)
    ->addTag('console.command')
    ->setPublic(true);

$container
    ->register(WB\Parser\Command\ParseBrandsCommand::class)
    ->addTag('console.command')
    ->setPublic(true);

$container
    ->register(WB\Parser\Command\ParseProductsCommand::class)
    ->addTag('console.command')
    ->setPublic(true);

$container
    ->register(WB\Parser\Command\MigrateCommand::class)
    ->addTag('console.command')
    ->setPublic(true);

$container->compile();

return $container;
