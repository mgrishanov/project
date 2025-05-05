<?php

declare(strict_types=1);

namespace WB\Parser\Service;

use WB\Parser\Api\WildberriesApi;
use WB\Parser\Model\Brand;
use WB\Parser\Producer\ProducerInterface;
use WB\Parser\Util\Logger;

class BrandService
{
    private Logger $logger;
    private ?\Closure $progressCallback = null;

    public function __construct(
        private readonly WildberriesApi $api,
        private readonly ProducerInterface $producer,
    ) {
        $this->logger = new Logger('brand-service');
    }

    /**
     * Устанавливает колбэк для отображения прогресса
     */
    public function setProgressCallback(\Closure $callback): void
    {
        $this->progressCallback = $callback;
    }

    /**
     * Парсинг и отправка в Kafka всех брендов
     */
    public function processAllBrands(): void
    {
        $this->logger->info('Starting to process all brands');
        
        try {
            // Получаем все буквы/символы для брендов
            $letters = $this->getBrandLetters();
            $this->logger->info('Got brand letters', ['count' => count($letters)]);
            
            $processedBrands = 0;
            
            // Обрабатываем бренды по каждой букве/символу
            foreach ($letters as $letter) {
                $this->logger->info('Processing brands for letter', ['letter' => $letter]);
                
                $brandsCount = $this->processBrandsByLetter($letter);
                $processedBrands += $brandsCount;
                
                $this->logger->info('Processed brands for letter', [
                    'letter' => $letter,
                    'count' => $brandsCount,
                    'total' => $processedBrands,
                ]);
                
                // Добавляем паузу между запросами
                usleep(intval($_ENV['PARSER_REQUEST_DELAY'] ?? 500) * 1000);
            }
            
            $this->logger->info('Finished processing all brands', ['total' => $processedBrands]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to process brands', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Получение всех брендов без отправки в Kafka
     * 
     * @return array<Brand>
     * @throws \Exception
     */
    public function getAllBrands(): array
    {
        $this->logger->info('Getting all brands');
        $brands = [];
        
        try {
            // Получаем все буквы/символы для брендов
            $letters = $this->getBrandLetters();
            $this->logger->info('Got brand letters', ['count' => count($letters)]);
            
            // Обрабатываем бренды по каждой букве/символу
            foreach ($letters as $letter) {
                $this->logger->info('Getting brands for letter', ['letter' => $letter]);
                
                $response = $this->api->getBrandsByLetter($letter);
                
                if (isset($response['value']['data'])) {
                    foreach ($response['value']['data'] as $brandData) {
                        if (isset($brandData['id'], $brandData['name'])) {
                            $brands[] = new Brand(
                                $brandData['id'],
                                $brandData['name'],
                            );
                        }
                    }
                }
                
                // Добавляем паузу между запросами
                usleep(intval($_ENV['PARSER_REQUEST_DELAY'] ?? 500) * 1000);
            }
            
            $this->logger->info('Finished getting all brands', ['total' => count($brands)]);
            return $brands;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get brands', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Отправляет бренд в Kafka
     */
    public function sendBrandToKafka(Brand $brand): void
    {
        $this->producer->sendBrand($brand->toArray());
    }

    /**
     * Получение списка всех букв/символов для брендов
     * 
     * @return array<string>
     * @throws \Exception
     */
    private function getBrandLetters(): array
    {
        $letters = [];
        
        try {
            // Путь к файлу с буквами
            $filePath = __DIR__ . '/../../store/letters.json';
            
            // Проверяем существование файла
            if (!file_exists($filePath)) {
                throw new \Exception("Файл с буквами не найден: {$filePath}");
            }
            
            // Читаем содержимое файла
            $jsonContent = file_get_contents($filePath);
            if ($jsonContent === false) {
                throw new \Exception("Не удалось прочитать файл с буквами: {$filePath}");
            }
            
            // Декодируем JSON
            $data = json_decode($jsonContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Ошибка декодирования JSON: " . json_last_error_msg());
            }
            
            // Извлекаем буквы из данных
            if (isset($data['letters']) && is_array($data['letters'])) {
                foreach ($data['letters'] as $item) {
                    if (isset($item['name'])) {
                        $letters[] = $item['name'];
                    }
                }
            }
            
            if (empty($letters)) {
                throw new \Exception("В файле не найдены буквы");
            }
            
            $this->logger->info('Loaded brand letters from file', ['count' => count($letters)]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get brand letters from file', ['error' => $e->getMessage()]);
            throw $e;
        }
        
        return $letters;
    }

    /**
     * Обработка брендов по букве/символу
     * 
     * @param string $letter Буква или символ
     * @return int Количество обработанных брендов
     * @throws \Exception
     */
    private function processBrandsByLetter(string $letter): int
    {
        $count = 0;
        
        try {
            $response = $this->api->getBrandsByLetter($letter);
            
            if (isset($response['value']['brandsList'])) {
                foreach ($response['value']['brandsList'] as $brandData) {
                    if (isset($brandData['id'], $brandData['name'])) {
                        $brand = new Brand(
                            $brandData['id'],
                            $brandData['name'],
                        );
                        
                        $this->producer->sendBrand($brand->toArray());
                        $count++;
                        
                        // Вызываем колбэк прогресса, если он установлен
                        if ($this->progressCallback !== null) {
                            ($this->progressCallback)();
                        }
                        
                        // Логируем каждые 10 брендов
                        if ($count % 10 === 0) {
                            $this->logger->info('Processed brands', [
                                'letter' => $letter,
                                'count' => $count,
                            ]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to process brands by letter', [
                'letter' => $letter,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
        
        return $count;
    }
}
