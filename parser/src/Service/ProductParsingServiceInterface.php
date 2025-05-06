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
     */
    public function parseAllBrands(array $brands): void;
}
