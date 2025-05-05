<?php

declare(strict_types=1);

namespace WB\Parser\Service;

use WB\Parser\Api\WildberriesApi;
use WB\Parser\Model\Quantity;
use WB\Parser\Producer\ProducerInterface;
use WB\Parser\Util\Logger;

class QuantityService
{
    private Logger $logger;
    private ?\Closure $progressCallback = null;

    public function __construct(
        private readonly WildberriesApi $api,
        private readonly ProducerInterface $producer,
    ) {
        $this->logger = new Logger('quantity-service');
    }

    /**
     * Устанавливает колбэк для отображения прогресса
     */
    public function setProgressCallback(\Closure $callback): void
    {
        $this->progressCallback = $callback;
    }

    /**
     * Обработка данных о количестве товаров
     * 
     * @param array<array<string, mixed>> $productsData Данные о товарах
     * @return int Количество обработанных записей
     */
    public function processQuantities(array $productsData): int
    {
        $this->logger->info('Processing quantities for products', ['count' => count($productsData)]);
        
        $processedCount = 0;
        
        try {
            // Получаем информацию о складах
            $stores = $this->getStoresMap();
            
            foreach ($productsData as $productData) {
                if (!isset($productData['id']) || !isset($productData['sizes'])) {
                    continue;
                }
                
                $productId = (int) $productData['id'];
                
                foreach ($productData['sizes'] as $size) {
                    if (!isset($size['optionId']) || !isset($size['stocks'])) {
                        continue;
                    }
                    
                    $optionId = (int) $size['optionId'];
                    
                    foreach ($size['stocks'] as $stock) {
                        if (!isset($stock['wh']) || !isset($stock['qty'])) {
                            continue;
                        }
                        
                        $warehouseId = (int) $stock['wh'];
                        $qty = (int) $stock['qty'];
                        
                        // Проверяем, что склад существует в нашей базе
                        if (!isset($stores[$warehouseId])) {
                            $this->logger->warning('Unknown warehouse', [
                                'warehouse_id' => $warehouseId,
                                'product_id' => $productId,
                            ]);
                            continue;
                        }
                        
                        $storeId = $warehouseId;
                        
                        // Создаем запись о количестве товара
                        $quantity = new Quantity(
                            productId: $productId,
                            optionId: $optionId,
                            storeId: $storeId,
                            quantity: $qty
                        );
                        
                        // Отправляем данные в Kafka
                        $this->producer->sendQuantity($quantity->toArray());
                        
                        // Вызываем колбэк прогресса, если он установлен
                        if ($this->progressCallback !== null) {
                            ($this->progressCallback)();
                        }
                        
                        $processedCount++;
                    }
                }
            }
            
            $this->logger->info('Finished processing quantities', ['count' => $processedCount]);
            
            return $processedCount;
        } catch (\Throwable $e) {
            $this->logger->error('Error processing quantities', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Получение карты складов
     * 
     * @return array<int, array<string, mixed>>
     */
    private function getStoresMap(): array
    {
        static $storesMap = null;
        
        if ($storesMap !== null) {
            return $storesMap;
        }
        
        $storesMap = [];
        
        try {
            $response = $this->api->getAllStores();
            
            if (isset($response['data'])) {
                foreach ($response['data'] as $store) {
                    if (isset($store['id'])) {
                        $storeId = (int) $store['id'];
                        $storesMap[$storeId] = $store;
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error fetching stores', ['error' => $e->getMessage()]);
        }
        
        return $storesMap;
    }
}
