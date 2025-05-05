<?php

declare(strict_types=1);

namespace WB\Parser\Service;

use WB\Parser\Api\WildberriesApi;
use WB\Parser\Model\Product;
use WB\Parser\Producer\ProducerInterface;
use WB\Parser\Util\Logger;

class ProductService
{
    private Logger $logger;
    private ?\Closure $progressCallback = null;

    public function __construct(
        private readonly WildberriesApi $api,
        private readonly ProducerInterface $producer,
    ) {
        $this->logger = new Logger('product-service');
    }

    /**
     * Устанавливает колбэк для отображения прогресса
     */
    public function setProgressCallback(\Closure $callback): void
    {
        $this->progressCallback = $callback;
    }

    /**
     * Обработка всех товаров бренда
     * 
     * @param int $brandId ID бренда
     * @return int Количество обработанных товаров
     */
    public function processProductsByBrand(int $brandId): int
    {
        $this->logger->info('Starting to process products for brand', ['brand_id' => $brandId]);
        
        $page = 1;
        $totalProducts = 0;
        $hasMorePages = true;
        
        try {
            while ($hasMorePages) {
                $this->logger->info('Processing products page', [
                    'brand_id' => $brandId,
                    'page' => $page,
                    'total_processed' => $totalProducts,
                ]);
                
                $response = $this->api->getProductsByBrand($brandId, $page);
                
                // Извлекаем данные о товарах
                $products = $this->extractProducts($response, $brandId);
                $count = count($products);
                
                if ($count === 0) {
                    $hasMorePages = false;
                } else {
                    $totalProducts += $count;
                    
                    // Отправляем данные в Kafka
                    foreach ($products as $product) {
                        $this->producer->sendProduct($product->toArray());
                        
                        // Вызываем колбэк прогресса, если он установлен
                        if ($this->progressCallback !== null) {
                            ($this->progressCallback)();
                        }
                    }
                    
                    // Получаем детальную информацию о товарах (включая остатки)
                    $this->processProductDetails(array_map(fn($p) => $p->id, $products));
                    
                    $page++;
                    
                    // Добавляем паузу между запросами
                    usleep(intval($_ENV['PARSER_REQUEST_DELAY'] ?? 500) * 1000);
                }
            }
            
            $this->logger->info('Finished processing products for brand', [
                'brand_id' => $brandId,
                'total' => $totalProducts,
            ]);
            
            return $totalProducts;
        } catch (\Throwable $e) {
            $this->logger->error('Error processing products for brand', [
                'brand_id' => $brandId,
                'error' => $e->getMessage(),
                'page' => $page,
            ]);
            
            throw $e;
        }
    }

    /**
     * Извлечение данных о товарах из ответа API
     * 
     * @param array<string, mixed> $response Ответ API
     * @param int $brandId ID бренда
     * @return array<Product>
     */
    private function extractProducts(array $response, int $brandId): array
    {
        $products = [];
        
        if (isset($response['data']['products'])) {
            $productsData = $response['data']['products'];
            
            foreach ($productsData as $productData) {
                if (isset($productData['id']) && isset($productData['name'])) {
                    $product = new Product(
                        id: (int) $productData['id'],
                        name: (string) $productData['name'],
                        sellerId: (int) ($productData['sellerId'] ?? 0),
                        brandId: $brandId,
                        subjectId: (int) ($productData['subjectId'] ?? 0)
                    );
                    
                    $products[] = $product;
                }
            }
        }
        
        return $products;
    }

    /**
     * Обработка детальной информации о товарах
     * 
     * @param array<int> $productIds Массив ID товаров
     */
    private function processProductDetails(array $productIds): void
    {
        if (empty($productIds)) {
            return;
        }
        
        $chunkSize = intval($_ENV['PARSER_CHUNK_SIZE'] ?? 100);
        
        // Обрабатываем товары порциями по 100 штук
        foreach (array_chunk($productIds, $chunkSize) as $chunk) {
            try {
                $this->logger->info('Processing product details', ['count' => count($chunk)]);
                
                $response = $this->api->getProductDetails($chunk);
                
                if (isset($response['data']['products'])) {
                    $productsData = $response['data']['products'];
                    
                    $quantityService = new QuantityService($this->api, $this->producer);
                    $quantityService->processQuantities($productsData);
                    
                    // Добавляем паузу между запросами
                    usleep(intval($_ENV['PARSER_REQUEST_DELAY'] ?? 500) * 1000);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Error processing product details', [
                    'product_ids' => $chunk,
                    'error' => $e->getMessage(),
                ]);
                
                // Продолжаем работу с другими порциями
                continue;
            }
        }
    }
}
