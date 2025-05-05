<?php

declare(strict_types=1);

namespace WB\Parser\Migration;

use ClickHouseDB\Client;
use WB\Parser\Util\Logger;

/**
 * Менеджер миграций для ClickHouse
 */
class MigrationManager
{
    /**
     * Клиент ClickHouse
     */
    private Client $client;

    /**
     * Логгер
     */
    private Logger $logger;

    /**
     * Директория с миграциями
     */
    private string $migrationsPath;

    /**
     * Конструктор
     */
    public function __construct(Client $client, string $migrationsPath = null)
    {
        $this->client = $client;
        $this->logger = new Logger('migration-manager');
        $this->migrationsPath = $migrationsPath ?? __DIR__ . '/Migrations';
        
        $this->initMigrationsTable();
    }

    /**
     * Инициализация таблицы миграций
     */
    private function initMigrationsTable(): void
    {
        $this->client->write("
            CREATE TABLE IF NOT EXISTS migrations (
                id UInt32,
                name String,
                batch UInt32,
                created_at DateTime DEFAULT now()
            ) ENGINE = MergeTree()
            ORDER BY id
        ");
    }

    /**
     * Получение списка выполненных миграций
     * 
     * @return array<string>
     */
    public function getAppliedMigrations(): array
    {
        $result = $this->client->select('SELECT name FROM migrations ORDER BY id')->rows();
        return array_column($result, 'name');
    }

    /**
     * Получение следующего ID для миграции
     */
    private function getNextMigrationId(): int
    {
        $result = $this->client->select('SELECT MAX(id) as max_id FROM migrations')->fetchOne();
        return (int)($result['max_id'] ?? 0) + 1;
    }

    /**
     * Получение текущего номера пакета миграций
     */
    private function getCurrentBatch(): int
    {
        $result = $this->client->select('SELECT MAX(batch) as current_batch FROM migrations')->fetchOne();
        return (int)($result['current_batch'] ?? 0) + 1;
    }

    /**
     * Получение списка доступных миграций
     * 
     * @return array<string>
     */
    public function getAvailableMigrations(): array
    {
        $files = glob($this->migrationsPath . '/*.php');
        if (!$files) {
            return [];
        }

        $migrations = [];
        foreach ($files as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $fullClassName = 'WB\\Parser\\Migration\\Migrations\\' . $className;

            if (class_exists($fullClassName)) {
                $migrations[] = $fullClassName;
            }
        }

        return $migrations;
    }

    /**
     * Получение списка миграций для выполнения
     * 
     * @return array<string>
     */
    public function getMigrationsToRun(): array
    {
        $appliedMigrations = $this->getAppliedMigrations();
        $availableMigrations = $this->getAvailableMigrations();
        
        return array_diff($availableMigrations, $appliedMigrations);
    }

    /**
     * Выполнение всех новых миграций
     * 
     * @return array<string> Список выполненных миграций
     */
    public function migrate(): array
    {
        $migrationsToRun = $this->getMigrationsToRun();
        if (empty($migrationsToRun)) {
            $this->logger->info('No migrations to run');
            return [];
        }

        $batch = $this->getCurrentBatch();
        $appliedMigrations = [];

        foreach ($migrationsToRun as $migrationClass) {
            $this->logger->info('Running migration', ['migration' => $migrationClass]);
            
            try {
                /** @var Migration $migration */
                $migration = new $migrationClass($this->client);
                $migration->up();
                
                $this->client->insert('migrations', [
                    [
                        'id' => $this->getNextMigrationId(),
                        'name' => $migrationClass,
                        'batch' => $batch,
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                ]);
                
                $appliedMigrations[] = $migrationClass;
                $this->logger->info('Migration completed', ['migration' => $migrationClass]);
            } catch (\Throwable $e) {
                $this->logger->error('Migration failed', [
                    'migration' => $migrationClass,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }

        return $appliedMigrations;
    }

    /**
     * Откат последней партии миграций
     * 
     * @return array<string> Список откаченных миграций
     */
    public function rollback(): array
    {
        $lastBatch = $this->client->select('SELECT MAX(batch) as last_batch FROM migrations')->fetchOne();
        $lastBatch = (int)($lastBatch['last_batch'] ?? 0);
        
        if ($lastBatch === 0) {
            $this->logger->info('No migrations to rollback');
            return [];
        }

        $migrations = $this->client->select("
            SELECT id, name FROM migrations 
            WHERE batch = {$lastBatch} 
            ORDER BY id DESC
        ")->rows();

        $rolledBackMigrations = [];

        foreach ($migrations as $migration) {
            $migrationClass = $migration['name'];
            $this->logger->info('Rolling back migration', ['migration' => $migrationClass]);
            
            try {
                /** @var Migration $migrationInstance */
                $migrationInstance = new $migrationClass($this->client);
                $migrationInstance->down();
                
                $this->client->write("DELETE FROM migrations WHERE id = {$migration['id']}");
                
                $rolledBackMigrations[] = $migrationClass;
                $this->logger->info('Rollback completed', ['migration' => $migrationClass]);
            } catch (\Throwable $e) {
                $this->logger->error('Rollback failed', [
                    'migration' => $migrationClass,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }

        return $rolledBackMigrations;
    }

    /**
     * Сброс всех миграций
     * 
     * @return array<string> Список откаченных миграций
     */
    public function reset(): array
    {
        $migrations = $this->client->select('SELECT id, name FROM migrations ORDER BY id DESC')->rows();
        
        $rolledBackMigrations = [];

        foreach ($migrations as $migration) {
            $migrationClass = $migration['name'];
            $this->logger->info('Resetting migration', ['migration' => $migrationClass]);
            
            try {
                /** @var Migration $migrationInstance */
                $migrationInstance = new $migrationClass($this->client);
                $migrationInstance->down();
                
                $rolledBackMigrations[] = $migrationClass;
                $this->logger->info('Reset completed', ['migration' => $migrationClass]);
            } catch (\Throwable $e) {
                $this->logger->error('Reset failed', [
                    'migration' => $migrationClass,
                    'error' => $e->getMessage()
                ]);
                // Продолжаем сброс даже при ошибке
            }
        }

        // Очищаем таблицу миграций
        $this->client->write('TRUNCATE TABLE migrations');

        return $rolledBackMigrations;
    }

    /**
     * Обновление миграций (сброс и повторное применение)
     * 
     * @return array<string> Список примененных миграций
     */
    public function refresh(): array
    {
        $this->reset();
        return $this->migrate();
    }

    /**
     * Создание новой миграции
     */
    public function create(string $name): string
    {
        $timestamp = date('Y_m_d_His');
        $className = 'd_'.$timestamp . '_' . $name;
        $filePath = $this->migrationsPath . '/' . $className . '.php';
        
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace WB\Parser\Migration\Migrations;

use WB\Parser\Migration\Migration;

class {$className} extends Migration
{
    /**
     * Выполнить миграцию
     */
    public function up(): void
    {
        \$this->client->write("
            -- Ваш SQL-код для применения миграции
        ");
    }

    /**
     * Откатить миграцию
     */
    public function down(): void
    {
        \$this->client->write("
            -- Ваш SQL-код для отката миграции
        ");
    }
}
PHP;

        file_put_contents($filePath, $content);
        $this->logger->info('Migration created', ['name' => $className, 'path' => $filePath]);
        
        return $filePath;
    }
}
