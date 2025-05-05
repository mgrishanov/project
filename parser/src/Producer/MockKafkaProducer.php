<?php

declare(strict_types=1);

namespace WB\Parser\Producer;

use WB\Parser\Producer\ProducerInterface;
use WB\Parser\Util\Logger;

/**
 * Имитация отправки сообщений в Kafka для тестирования
 */
class MockKafkaProducer implements ProducerInterface
{
    private Logger $logger;
    private string $outputDir;

    public function __construct(
        private readonly string $brokers,
        private readonly string $brandsTopic,
        private readonly string $productsTopic,
        private readonly string $quantitiesTopic,
        string $outputDir = 'output'
    ) {
        $this->logger = new Logger('mock-kafka-producer');
        $this->outputDir = $outputDir;
        
        // Создаем директорию для вывода, если она не существует
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }
        
        $this->logger->info('Initialized Mock Kafka Producer', [
            'output_dir' => $this->outputDir,
            'brokers' => $this->brokers,
            'topics' => [
                'brands' => $this->brandsTopic,
                'products' => $this->productsTopic,
                'quantities' => $this->quantitiesTopic
            ]
        ]);
    }

    /**
     * Отправка данных о бренде в файл
     * 
     * @param array<string, mixed> $brandData
     */
    public function sendBrand(array $brandData): void
    {
        $this->writeToFile($this->outputDir . '/brands.json', $brandData);
        $this->logger->info('Brand data saved to file', [
            'brand_id' => $brandData['id'] ?? 'unknown',
            'brand_name' => $brandData['name'] ?? 'unknown'
        ]);
    }

    /**
     * Отправка данных о товаре в файл
     * 
     * @param array<string, mixed> $productData
     */
    public function sendProduct(array $productData): void
    {
        $this->writeToFile($this->outputDir . '/products.json', $productData);
        $this->logger->info('Product data saved to file', [
            'product_id' => $productData['id'] ?? 'unknown',
            'product_name' => $productData['name'] ?? 'unknown'
        ]);
    }

    /**
     * Отправка данных о количестве в файл
     * 
     * @param array<string, mixed> $quantityData
     */
    public function sendQuantity(array $quantityData): void
    {
        $this->writeToFile($this->outputDir . '/quantities.json', $quantityData);
        $this->logger->info('Quantity data saved to file', [
            'product_id' => $quantityData['product_id'] ?? 'unknown',
            'quantity' => $quantityData['quantity'] ?? 0
        ]);
    }

    /**
     * Запись данных в файл в формате JSON
     */
    private function writeToFile(string $filename, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        file_put_contents($filename, $json, FILE_APPEND);
    }

    /**
     * Закрытие соединения (для совместимости с интерфейсом)
     */
    public function close(): void
    {
        $this->logger->info('Mock Kafka Producer closed');
    }
}
