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
        $pool = Pool::create()->concurrency(8); // 8 процессов

        foreach ($brands as $brand) {
            $pool->add(function () use ($brand) {
                $this->productService->processProductsByBrand($brand['id']);
            })->then(function () use ($onProgress) {
                $onProgress();
            })->catch(function (Throwable $exception) use ($brand) {
                // TODO: логирование ошибок
            });
        }

        $pool->wait();
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
