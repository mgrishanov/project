<?php

declare(strict_types=1);

namespace WB\Parser\Database;

use ClickHouseDB\Client;
use WB\Parser\Util\Logger;

/**
 * Класс для подключения к ClickHouse
 */
class ClickHouseConnection
{
    /**
     * Клиент ClickHouse
     */
    private static ?Client $client = null;

    /**
     * Логгер
     */
    private static Logger $logger;

    /**
     * Получение клиента ClickHouse
     */
    public static function getClient(): Client
    {
        if (self::$client === null) {
            self::$logger = new Logger('clickhouse-connection');
            
            $host = $_ENV['CLICKHOUSE_HOST'] ?? 'clickhouse';
            $port = (int)($_ENV['CLICKHOUSE_PORT'] ?? 8123);
            $username = $_ENV['CLICKHOUSE_USER'] ?? 'datagrid';
            $password = $_ENV['CLICKHOUSE_PASSWORD'] ?? 'datagrid_password';
            $database = $_ENV['CLICKHOUSE_DB'] ?? 'wb_data';
            
            self::$logger->info('Connecting to ClickHouse', [
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'database' => $database
            ]);
            
            self::$client = new Client([
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'password' => $password,
                'database' => $database,
                'connect_timeout' => 10,
                'timeout' => 30
            ]);
            
            try {
                // Проверка соединения
                self::$client->ping();
                self::$logger->info('Connected to ClickHouse successfully');
            } catch (\Throwable $e) {
                self::$logger->error('Failed to connect to ClickHouse', ['error' => $e->getMessage()]);
                throw $e;
            }
        }
        
        return self::$client;
    }
}
