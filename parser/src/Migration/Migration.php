<?php

declare(strict_types=1);

namespace WB\Parser\Migration;

use ClickHouseDB\Client;

/**
 * Базовый класс для миграций ClickHouse
 */
abstract class Migration
{
    /**
     * Клиент ClickHouse
     */
    protected Client $client;

    /**
     * Имя миграции
     */
    protected string $name;

    /**
     * Конструктор
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->name = static::class;
    }

    /**
     * Получить имя миграции
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Выполнить миграцию
     */
    abstract public function up(): void;

    /**
     * Откатить миграцию
     */
    abstract public function down(): void;
}
