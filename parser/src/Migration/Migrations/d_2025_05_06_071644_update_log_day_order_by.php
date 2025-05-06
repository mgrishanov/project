<?php

declare(strict_types=1);

namespace WB\Parser\Migration\Migrations;

use WB\Parser\Migration\Migration;

class d_2025_05_06_071644_update_log_day_order_by extends Migration
{
    /**
     * Выполнить миграцию
     */
    public function up(): void
    {
        // В ClickHouse нельзя изменить ключ сортировки без пересоздания таблицы
        // Поэтому создаем временную таблицу, копируем данные, удаляем старую и переименовываем новую
        
        // Шаг 1: Создаем временную таблицу с новым ключом сортировки
        $this->client->write("
            CREATE TABLE IF NOT EXISTS log_day_new (
              date Date,
              time DateTime,
              product_id UInt64,
              rating UInt8,
              review_rating Float64,
              feedbacks UInt32,
              volume UInt16,
              view_flags UInt64
            ) ENGINE = MergeTree()
            PARTITION BY toYYYYMMDD(time)
            ORDER BY (product_id, time)
            TTL time + INTERVAL 7 DAY
        ");
        
        // Шаг 2: Копируем данные из старой таблицы в новую
        $this->client->write("
            INSERT INTO log_day_new
            SELECT * FROM log_day
        ");
        
        // Шаг 3: Удаляем старую таблицу
        $this->client->write("
            DROP TABLE IF EXISTS log_day
        ");
        
        // Шаг 4: Переименовываем новую таблицу
        $this->client->write("
            RENAME TABLE log_day_new TO log_day
        ");
    }

    /**
     * Откатить миграцию
     */
    public function down(): void
    {
        // Восстанавливаем таблицу с прежним ключом сортировки
        
        // Шаг 1: Создаем временную таблицу с прежним ключом сортировки
        $this->client->write("
            CREATE TABLE IF NOT EXISTS log_day_old (
              date Date,
              time DateTime,
              product_id UInt64,
              rating UInt8,
              review_rating Float64,
              feedbacks UInt32,
              volume UInt16,
              view_flags UInt64
            ) ENGINE = MergeTree()
            PARTITION BY toYYYYMMDD(time)
            ORDER BY (date, product_id, time)
            TTL time + INTERVAL 7 DAY
        ");
        
        // Шаг 2: Копируем данные из текущей таблицы во временную
        $this->client->write("
            INSERT INTO log_day_old
            SELECT * FROM log_day
        ");
        
        // Шаг 3: Удаляем текущую таблицу
        $this->client->write("
            DROP TABLE IF EXISTS log_day
        ");
        
        // Шаг 4: Переименовываем временную таблицу
        $this->client->write("
            RENAME TABLE log_day_old TO log_day
        ");
    }
}