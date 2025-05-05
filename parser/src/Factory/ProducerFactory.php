<?php

declare(strict_types=1);

namespace WB\Parser\Factory;

use WB\Parser\Producer\KafkaProducer;
use WB\Parser\Producer\MockKafkaProducer;
use WB\Parser\Producer\ProducerInterface;

/**
 * Фабрика для создания производителей сообщений
 */
class ProducerFactory
{
    /**
     * Создание экземпляра продюсера сообщений (реального или мок)
     *
     * @param bool $useMock Использовать ли мок-продюсер вместо реального
     * @param string $outputDir Директория для вывода данных при использовании мок-продюсера
     * @return ProducerInterface
     */
    public static function create(bool $useMock = false, string $outputDir = 'output'): ProducerInterface
    {
        $brokers = $_ENV['KAFKA_BROKERS'] ?? 'localhost:9092';
        $brandsTopic = $_ENV['KAFKA_TOPIC_BRANDS'] ?? 'wb_brands';
        $productsTopic = $_ENV['KAFKA_TOPIC_PRODUCTS'] ?? 'wb_products';
        $quantitiesTopic = $_ENV['KAFKA_TOPIC_QUANTITIES'] ?? 'wb_quantities';
        
        if ($useMock) {
            return new MockKafkaProducer(
                $brokers,
                $brandsTopic,
                $productsTopic,
                $quantitiesTopic,
                $outputDir
            );
        }
        
        return new KafkaProducer(
            $brokers,
            $brandsTopic,
            $productsTopic,
            $quantitiesTopic
        );
    }
}
