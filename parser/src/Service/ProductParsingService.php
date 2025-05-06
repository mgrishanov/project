<?php

declare(strict_types=1);

namespace WB\Parser\Service;

use Spatie\Async\Pool;
use Throwable;

/**
 * Сервис параллельного парсинга продуктов с использованием spatie/async.
 */
class ProductParsingService implements ProductParsingServiceInterface
{
    private readonly ProductService $productService;

    public function __construct(
        ProductService $productService
    ) {
        $this->productService = $productService;
    }

    /**
     * @inheritDoc
     */
    public function parseAllBrands(array $brands, callable $onProgress): void
    {
        // Собираем массив brandId
        $brandIds = array_column($brands, 'id');
        // Запускаем пул параллельных запросов через Guzzle (50 одновременных)
        $this->productService->setProgressCallback($onProgress);
        $this->productService->processProductsByBrands($brandIds, 50);
    }

    /**
     * Парсинг одного бренда (реализация будет добавлена отдельно).
     *
     * @param array $brand
     */
    private function parseBrand(array $brand): void
    {
        // TODO: Вынести сюда бизнес-логику парсинга одного бренда
    }
}
