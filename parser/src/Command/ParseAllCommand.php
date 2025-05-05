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
use WB\Parser\Service\ProductService;
use WB\Parser\Util\Logger;

/**
 * Команда для парсинга и отправки всех данных в Kafka
 */
#[AsCommand(
    name: 'parse:all',
    description: 'Parse and send all data (brands, products) to Kafka',
)]
class ParseAllCommand extends Command
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
            )
            ->addOption(
                'limit-brands',
                'l',
                InputOption::VALUE_REQUIRED,
                'Limit the number of brands to process',
                0
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->logger = new Logger('parser:all');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Wildberries Full Parser');

        $useMockProducer = $input->getOption('mock');
        $limitBrands = (int) $input->getOption('limit-brands');

        if ($useMockProducer) {
            $io->note('Running in mock mode without connecting to Kafka');
        }

        if ($limitBrands > 0) {
            $io->note(sprintf('Limiting to %d brands', $limitBrands));
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

            // Парсинг брендов
            $io->section('Starting brands parsing process');
            $brandService = new BrandService($api, $producer);
            
            // Получаем бренды и обрабатываем их
            $brands = $brandService->getAllBrands();
            
            if ($limitBrands > 0 && count($brands) > $limitBrands) {
                $io->note(sprintf('Limiting from %d to %d brands', count($brands), $limitBrands));
                $brands = array_slice($brands, 0, $limitBrands);
            }
            
            $io->progressStart(count($brands));
            
            foreach ($brands as $brand) {
                $io->text(sprintf('Processing brand: %s (ID: %d)', $brand->getName(), $brand->getId()));
                
                // Отправляем бренд в Kafka
                $brandService->sendBrandToKafka($brand);
                
                // Парсим товары для этого бренда
                $io->text(sprintf('Parsing products for brand: %s', $brand->getName()));
                $productService = new ProductService($api, $producer);
                $productService->processProductsByBrand($brand->getId());
                
                $io->progressAdvance();
            }
            
            $io->progressFinish();

            // Закрываем соединение с Kafka
            $producer->close();

            $io->success('Full parsing completed successfully');
            $this->logger->info('Full parsing completed successfully');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Parser failed: ' . $e->getMessage());
            $this->logger->error('Parser failed', ['error' => $e->getMessage()]);

            return Command::FAILURE;
        }
    }
}
