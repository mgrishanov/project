<?php

declare(strict_types=1);

namespace WB\Parser\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WB\Parser\Api\WildberriesApi;
use WB\Parser\Factory\ProducerFactory;
use WB\Parser\Service\ProductService;
use WB\Parser\Util\Logger;

/**
 * Команда для парсинга и отправки продуктов в Kafka
 */
#[AsCommand(
    name: 'parse:products',
    description: 'Parse and send products for a specific brand to Kafka',
)]
class ParseProductsCommand extends Command
{
    private Logger $logger;

    public function __construct()
    {
        parent::__construct('parse:products');
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'brand-id',
                InputArgument::REQUIRED,
                'Brand ID to parse products for'
            )
            ->addOption(
                'mock',
                'm',
                InputOption::VALUE_NONE,
                'Run in mock mode without connecting to Kafka'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->logger = new Logger('parser:products');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Wildberries Products Parser');

        $brandId = (int) $input->getArgument('brand-id');
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

            $io->section(sprintf('Starting products parsing for brand ID: %d', $brandId));
            $productService = new ProductService($api, $producer);
            
            $io->progressStart(); // Здесь можно добавить общее количество продуктов, если известно
            
            // Добавляем обработчик прогресса
            $productService->setProgressCallback(function () use ($io) {
                $io->progressAdvance();
            });
            
            $productService->processProductsByBrand($brandId);
            
            $io->progressFinish();

            // Закрываем соединение с Kafka
            $producer->close();

            $io->success(sprintf('Products parsing for brand ID %d completed successfully', $brandId));
            $this->logger->info('Products parsing completed successfully', ['brandId' => $brandId]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Parser failed: ' . $e->getMessage());
            $this->logger->error('Parser failed', ['error' => $e->getMessage(), 'brandId' => $brandId]);

            return Command::FAILURE;
        }
    }
}
