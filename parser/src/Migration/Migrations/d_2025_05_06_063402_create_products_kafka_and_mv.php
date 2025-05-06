<?php

declare(strict_types=1);

namespace WB\Parser\Migration\Migrations;

use WB\Parser\Migration\Migration;

class d_2025_05_06_063402_create_products_kafka_and_mv extends Migration
{
    /**
     * Выполнить миграцию
     */
    public function up(): void
    {
        // Создаем таблицу-очередь для товаров
        $this->client->write("
            CREATE TABLE IF NOT EXISTS products_kafka (
              id UInt64,
              name String,
              seller_id UInt32,
              brand_id UInt32,
              subject_id UInt32,
              created_at Date
            ) ENGINE = Kafka
            SETTINGS
              kafka_broker_list = 'kafka:9092',
              kafka_topic_list = 'products',
              kafka_group_name = 'clickhouse_products_consumer',
              kafka_format = 'JSONEachRow'
        ");

        // Создаем материализованное представление для товаров
        $this->client->write("
            CREATE MATERIALIZED VIEW IF NOT EXISTS products_mv
            TO product
            AS SELECT * FROM products_kafka
        ");
    }

    /**
     * Откатить миграцию
     */
    public function down(): void
    {
        // Удаляем материализованное представление
        $this->client->write("
            DROP VIEW IF EXISTS products_mv
        ");

        // Удаляем таблицу-очередь
        $this->client->write("
            DROP TABLE IF EXISTS products_kafka
        ");
    }
}