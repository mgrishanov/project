<?php

declare(strict_types=1);

namespace WB\Parser\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WB\Parser\Api\WildberriesApi;
use WB\Parser\Factory\ProducerFactory;
use WB\Parser\Service\BrandService;
use WB\Parser\Util\Logger;

/**
 * Команда для парсинга и отправки брендов в Kafka
 */
#[AsCommand(
    name: 'parse:brands',
    description: 'Parse and send all brands to Kafka',
)]
class ParseBrandsCommand extends Command
{
    private Logger $logger;

    protected function configure(): void
    {
        $this
            ->addOption(
                'mock',
                'm',
                InputOption::VALUE_NONE,
                'Run in mock mode without connecting to Kafka'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->logger = new Logger('parser:brands');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Wildberries Brands Parser');

        $useMockProducer = $input->getOption('mock');
        if ($useMockProducer) {
            $io->note('Running in mock mode without connecting to Kafka');
        }

        try {
            $io->section('Initializing API client');
            $api = new WildberriesApi(
                userAgent: $_ENV['PARSER_USER_AGENT'] ?? 'Wildberries Parser',
                maxRetries: intval($_ENV['PARSER_MAX_RETRIES'] ?? 3),
                requestDelay: intval($_ENV['PARSER_REQUEST_DELAY'] ?? 500)
            );

            $io->section('Initializing Kafka producer');
            $producer = ProducerFactory::create($useMockProducer);

            $io->section('Starting brands parsing process');
            $brandService = new BrandService($api, $producer);
            
            $io->progressStart(); // Здесь можно добавить общее количество брендов, если известно
            
            // Добавляем обработчик прогресса
            $brandService->setProgressCallback(function () use ($io) {
                $io->progressAdvance();
            });
            
            $brandService->processAllBrands();
            
            $io->progressFinish();

            // Закрываем соединение с Kafka
            $producer->close();

            $io->success('Brands parsing completed successfully');
            $this->logger->info('Brands parsing completed successfully');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Parser failed: ' . $e->getMessage());
            $this->logger->error('Parser failed', ['error' => $e->getMessage()]);

            return Command::FAILURE;
        }
    }
}
