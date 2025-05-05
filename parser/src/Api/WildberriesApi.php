<?php

declare(strict_types=1);

namespace WB\Parser\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use WB\Parser\Util\Logger;
use WB\Parser\Api\WildberriesHeaders;

class WildberriesApi
{
    private Client $client;
    private Logger $logger;

    public function __construct(
        private readonly string $userAgent,
        private readonly int $maxRetries = 3,
        private readonly int $requestDelay = 500, // ms
    ) {
        $this->client = new Client([
            'headers' => array_merge(
                WildberriesHeaders::getBaseHeaders(),
                ['User-Agent' => $this->userAgent]
            ),
            'timeout' => 30,
        ]);
        
        $this->logger = new Logger('wildberries-api');
    }

    /**
     * Получение списка брендов по букве/символу
     * 
     * @param string $letter Буква или символ
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function getBrandsByLetter(string $letter): array
    {
        $url = $_ENV['WB_API_BASE_URL'] . '/wildberries/brandlist/data?letter=' . urlencode($letter);
        $this->logger->debug('Getting brands by letter', ['url' => $url, 'letter' => $letter]);
        
        try {
            // Используем специальные заголовки для запроса брендов
            $options = [
                'headers' => WildberriesHeaders::getBrandHeaders(),
            ];
            
            $response = $this->makeRequest('POST', $url, $options);
            $this->logger->debug('Brands by letter response', ['letter' => $letter, 'response_size' => is_array($response) ? count($response) : 0]);
            
            // Проверяем, что ответ содержит нужные данные
            if (!empty($response) && isset($response['value']['brandsList']) && !empty($response['value']['brandsList'])) {
                return $response;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to get brands for letter from API, using mock data', ['letter' => $letter, 'error' => $e->getMessage()]);
        }
        
        return [];
    }

    /**
     * Получение списка всех категорий
     * 
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function getAllSubjects(): array
    {
        $url = $_ENV['WB_STATIC_API_URL'] . '/vol0/data/subject-base.json';
        return $this->makeRequest('GET', $url);
    }

    /**
     * Получение списка товаров по ID бренда
     * 
     * @param int $brandId ID бренда
     * @param int $page Номер страницы
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function getProductsByBrand(int $brandId, int $page = 1): array
    {
        $url = $_ENV['WB_CATALOG_API_URL'] . '/brands/v2/catalog?' . http_build_query([
            'ab_testing' => 'false',
            'appType' => 1,
            'brand' => $brandId,
            'curr' => 'rub',
            'dest' => 12358314, // Москва
            'lang' => 'ru',
            'page' => $page,
            'sort' => 'popular',
            'spp' => 30,
        ]);
        
        // Используем специальные заголовки для запроса продуктов
        $options = [
            'headers' => WildberriesHeaders::getProductHeaders(),
        ];
        
        return $this->makeRequest('GET', $url, $options);
    }

    /**
     * Получение детальной информации о товарах по ID
     * 
     * @param array<int> $productIds Массив ID товаров
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function getProductDetails(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }
        
        // Ограничиваем количество товаров в одном запросе
        $productIds = array_slice($productIds, 0, 500);
        
        $url = $_ENV['WB_CARD_API_URL'] . '/cards/v2/detail?' . http_build_query([
            'appType' => 1,
            'curr' => 'rub',
            'dest' => 12358314, // Москва
            'lang' => 'ru',
            'ab_testing' => 'false',
            'nm' => implode(';', $productIds),
        ]);
        
        // Используем специальные заголовки для запроса деталей продуктов
        $options = [
            'headers' => WildberriesHeaders::getProductHeaders(),
        ];
        
        return $this->makeRequest('GET', $url, $options);
    }

    /**
     * Получение списка всех складов
     * 
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function getAllStores(): array
    {
        $url = $_ENV['WB_STATIC_API_URL'] . '/vol0/data/stores-data.json';
        return $this->makeRequest('GET', $url);
    }

    /**
     * Выполнение HTTP-запроса с повторными попытками в случае ошибок
     * 
     * @param string $method HTTP-метод
     * @param string $url URL
     * @param array<string, mixed> $options Дополнительные параметры запроса
     * @return array<string, mixed>
     * @throws \Exception
     */
    private function makeRequest(string $method, string $url, array $options = []): array
    {
        $attempts = 0;
        
        while ($attempts < $this->maxRetries) {
            try {
                // Добавляем задержку между запросами, кроме первого
                if ($attempts > 0) {
                    usleep($this->requestDelay * 1000);
                }
                
                $attempts++;
                
                $this->logger->debug('Making API request', [
                    'url' => $url,
                    'method' => $method,
                    'attempt' => $attempts,
                ]);
                
                $response = $this->client->request($method, $url, $options);
                $content = $response->getBody()->getContents();
                
                $result = json_decode($content, true) ?: [];
                
                $this->logger->debug('API request successful', [
                    'url' => $url,
                    'method' => $method,
                    'status_code' => $response->getStatusCode(),
                ]);
                
                return $result;
            } catch (GuzzleException $e) {
                $this->logger->error('API request failed', [
                    'url' => $url,
                    'method' => $method,
                    'attempt' => $attempts,
                    'error' => $e->getMessage(),
                ]);
                
                // Если это последняя попытка, пробрасываем исключение
                if ($attempts >= $this->maxRetries) {
                    throw new \Exception("Failed to make request to {$url} after {$this->maxRetries} attempts: " . $e->getMessage());
                }
                
                // Увеличиваем задержку с каждой попыткой
                usleep($this->requestDelay * $attempts * 1000);
            }
        }
        
        // Этот код никогда не должен выполняться из-за условий выше
        throw new \Exception("Unexpected error when making request to {$url}");
    }
}
