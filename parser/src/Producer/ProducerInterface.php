<?php

declare(strict_types=1);

namespace WB\Parser\Producer;

/**
 * Интерфейс для всех продюсеров сообщений (реальных и мок)
 */
interface ProducerInterface
{
    /**
     * Отправка данных о бренде
     * 
     * @param array<string, mixed> $brandData
     */
    public function sendBrand(array $brandData): void;
    
    /**
     * Отправка данных о товаре
     * 
     * @param array<string, mixed> $productData
     */
    public function sendProduct(array $productData): void;
    
    /**
     * Отправка данных о количестве товара
     * 
     * @param array<string, mixed> $quantityData
     */
    public function sendQuantity(array $quantityData): void;
    
    /**
     * Закрытие соединения
     */
    public function close(): void;
}
