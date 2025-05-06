<?php

declare(strict_types=1);

namespace WB\Parser\Service;

/**
 * Интерфейс сервиса параллельного парсинга продуктов.
 */
interface ProductParsingServiceInterface
{
    /**
     * Запускает парсинг всех брендов параллельно.
     *
     * @param array $brands Список брендов для парсинга
     * @param callable $onProgress Колбэк для прогресса
     */
    public function parseAllBrands(array $brands, callable $onProgress): void;
}
