<?php

declare(strict_types=1);

namespace WB\Parser\Producer;

use longlang\phpkafka\Producer\Producer;
use longlang\phpkafka\Producer\ProducerConfig;
use WB\Parser\Producer\ProducerInterface;
use WB\Parser\Util\Logger;

class KafkaProducer implements ProducerInterface
{
    private Producer $producer;
    private Logger $logger;

    public function __construct(
        private readonly string $brokers,
        private readonly string $brandsTopic,
        private readonly string $productsTopic,
        private readonly string $quantitiesTopic,
    ) {
        $config = new ProducerConfig();
        $config->setBrokers($this->brokers);
        $config->setBootstrapServers($this->brokers);
        $config->setAcks(-1); // Ждать подтверждения от всех реплик
        $config->setProduceRetry(3); // Количество повторных попыток
        $config->setProduceRetrySleep(1.0); // Пауза между повторными попытками (в секундах)
        
        $this->producer = new Producer($config);
        
        $this->logger = new Logger('kafka-producer');
    }

    /**
     * Отправка данных о бренде в Kafka
     * 
     * @param array<string, mixed> $brandData
     * @throws \Exception
     */
    public function sendBrand(array $brandData): void
    {
        $this->send($this->brandsTopic, $brandData);
    }

    /**
     * Отправка данных о товаре в Kafka
     * 
     * @param array<string, mixed> $productData
     * @throws \Exception
     */
    public function sendProduct(array $productData): void
    {
        $this->send($this->productsTopic, $productData);
    }

    /**
     * Отправка данных о количестве товара в Kafka
     * 
     * @param array<string, mixed> $quantityData
     * @throws \Exception
     */
    public function sendQuantity(array $quantityData): void
    {
        $this->send($this->quantitiesTopic, $quantityData);
    }

    /**
     * Отправка данных в Kafka
     * 
     * @param string $topic Топик
     * @param array<string, mixed> $data Данные
     * @throws \Exception
     */
    private function send(string $topic, array $data): void
    {
        try {
            // Преобразуем данные в JSON
            $json = json_encode($data, JSON_THROW_ON_ERROR);
            
            // Отправляем сообщение в Kafka
            $this->producer->send($topic, $json);
            
            $this->logger->info('Message sent to Kafka', [
                'topic' => $topic,
                'data_size' => strlen($json),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send message to Kafka', [
                'topic' => $topic,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            
            throw new \Exception("Failed to send message to Kafka topic {$topic}: " . $e->getMessage());
        }
    }

    /**
     * Закрытие соединения с Kafka
     */
    public function close(): void
    {
        $this->producer->close();
    }
}
