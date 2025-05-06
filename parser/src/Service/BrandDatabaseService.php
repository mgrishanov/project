<?php

declare(strict_types=1);

namespace WB\Parser\Service;

use WB\Parser\Database\ClickHouseConnection;
use WB\Parser\Util\Logger;

/**
 * Сервис для работы с брендами в базе данных ClickHouse
 */
class BrandDatabaseService
{
    private Logger $logger;

    public function __construct()
    {
        $this->logger = new Logger('brand-database-service');
    }

    /**
     * Получение всех брендов из базы данных с пагинацией
     * 
     * @param int $limit Максимальное количество брендов для получения
     * @param int $lastId ID последнего бренда
     * @return array Массив брендов с ключами id и name
     */
    public function getBrands(int $limit = 100, int $lastId = 0): array
    {
        try {
            $client = ClickHouseConnection::getClient();
            
            $this->logger->info('Getting brands from database with pagination', [
                'limit' => $limit,
                'last_id' => $lastId
            ]);
            
            $statement = $client->select(
                'SELECT id, name FROM brand WHERE id > :last_id ORDER BY id LIMIT :limit',
                [
                    'limit' => $limit,
                    'last_id' => $lastId
                ]
            );
            
            $rows = $statement->rows();
            
            $this->logger->info('Retrieved brands from database', ['count' => count($rows)]);
            
            return $rows;
        } catch (\Throwable $e) {
            $this->logger->error('Error getting brands from database', ['error' => $e->getMessage()]);
            
            // Если таблица не существует или произошла другая ошибка, возвращаем пустой массив
            return [];
        }
    }
    
    /**
     * Получение общего количества брендов в базе данных
     * 
     * @return int Количество брендов
     */
    public function getBrandsCount(): int
    {
        try {
            $client = ClickHouseConnection::getClient();
            
            $this->logger->info('Getting brands count from database');
            
            $statement = $client->select('SELECT count() as count FROM brand');
            $result = $statement->fetchOne('count');
            
            $this->logger->info('Retrieved brands count from database', ['count' => $result]);
            
            return (int) $result;
        } catch (\Throwable $e) {
            $this->logger->error('Error getting brands count from database', ['error' => $e->getMessage()]);
            return 0;
        }
    }
    
    /**
     * Получение бренда по ID
     * 
     * @param int $id ID бренда
     * @return array|null Данные бренда или null, если бренд не найден
     */
    public function getBrandById(int $id): ?array
    {
        try {
            $client = ClickHouseConnection::getClient();
            
            $this->logger->info('Getting brand by ID', ['id' => $id]);
            
            $statement = $client->select('SELECT id, name FROM brand WHERE id = :id', ['id' => $id]);
            $rows = $statement->rows();
            
            if (empty($rows)) {
                $this->logger->info('Brand not found', ['id' => $id]);
                return null;
            }
            
            $this->logger->info('Retrieved brand from database', ['id' => $id]);
            
            return $rows[0];
        } catch (\Throwable $e) {
            $this->logger->error('Error getting brand from database', ['id' => $id, 'error' => $e->getMessage()]);
            return null;
        }
    }
}
