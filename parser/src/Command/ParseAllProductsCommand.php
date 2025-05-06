<?php

declare(strict_types=1);

namespace WB\Parser\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WB\Parser\Service\BrandDatabaseService;
use WB\Parser\Service\ProductParsingServiceInterface;
use WB\Parser\Service\ProductService;
use WB\Parser\Util\Logger;

/**
 * Команда для параллельного парсинга всех товаров всех брендов
 */
#[AsCommand(
    name: 'parse:all-products',
    description: 'Parse and send all products from all brands to Kafka using parallel processing',
)]
class ParseAllProductsCommand extends Command
{
    private Logger $logger;
    private const MAX_THREADS = 20; // Максимальное количество параллельных потоков
    private const BATCH_SIZE = 100; // Размер пакета брендов для обработки
    private ProductParsingServiceInterface $productParsingService;
    private ProductService $productService;

    public function __construct(ProductParsingServiceInterface $productParsingService, ProductService $productService)
    {
        parent::__construct();
        $this->productParsingService = $productParsingService;
        $this->productService = $productService;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'mock',
                'm',
                InputOption::VALUE_NONE,
                'Run in mock mode without connecting to Kafka'
            )
            ->addOption(
                'threads',
                't',
                InputOption::VALUE_OPTIONAL,
                'Number of parallel threads to use (default: 20)',
                self::MAX_THREADS
            )
            ->addOption(
                'batch-size',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Number of brands to process in one batch (default: 100)',
                self::BATCH_SIZE
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->logger = new Logger('parser:all-products');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Wildberries All Products Parallel Parser');

        $useMockProducer = $input->getOption('mock');
        $threads = (int) $input->getOption('threads');
        $batchSize = (int) $input->getOption('batch-size');
        
        if ($threads <= 0 || $threads > 100) {
            $threads = self::MAX_THREADS;
        }
        
        if ($batchSize <= 0 || $batchSize > 1000) {
            $batchSize = self::BATCH_SIZE;
        }

        if ($useMockProducer) {
            $io->note('Running in mock mode without connecting to Kafka');
        }

        try {
            $io->section('Getting brands count from database');
            $brandService = new BrandDatabaseService();
            $totalBrands = $brandService->getBrandsCount();
            
            if ($totalBrands === 0) {
                $io->warning('No brands found in the database. Please run parse:brands command first to populate the database.');
                return Command::FAILURE;
            }
            
            $io->info(sprintf('Found %d brands to process', $totalBrands));
            $io->section(sprintf('Starting parallel parsing with %d threads and batch size %d', $threads, $batchSize));
            $io->progressStart($totalBrands);

            $processedCount = 0;
            $lastId = 0;
            while (true) {
                $brands = $brandService->getBrands($batchSize, $lastId);
                if (empty($brands)) {
                    break;
                }

                // Параллельный запуск вынесен в сервис
                $io->progressAdvance($batchSize);
                $this->productParsingService->parseAllBrands($brands);
                $processedCount += count($brands);
                $lastId = end($brands)['id'];
                $this->logger->info('Processed batch of brands', [
                    'batch_size' => count($brands),
                    'processed_total' => $processedCount,
                    'total_brands' => $totalBrands,
                    'last_id' => $lastId
                ]);
            }
            $io->progressFinish();
            $io->section('Parsing Statistics');
            // Статистику по завершённым/ошибочным брендам теперь возвращает сервис
            // Здесь можно добавить соответствующий вывод, если сервис будет возвращать массивы
            $io->success('All products parsing completed');
            $this->logger->info('All products parsing completed', [
                'total_brands' => $totalBrands,
                'processed' => $processedCount,
            ]);
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Parser failed: ' . $e->getMessage());
            $this->logger->error('Parser failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }
}
