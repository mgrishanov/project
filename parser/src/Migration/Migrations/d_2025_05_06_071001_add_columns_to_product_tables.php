<?php

declare(strict_types=1);

namespace WB\Parser\Migration\Migrations;

use WB\Parser\Migration\Migration;

class d_2025_05_06_071001_add_columns_to_product_tables extends Migration
{
    /**
     * Выполнить миграцию
     */
    public function up(): void
    {
        // Добавляем колонки в таблицу product
        $this->client->write("
            ALTER TABLE product
            ADD COLUMN IF NOT EXISTS root UInt64,
            ADD COLUMN IF NOT EXISTS kind_id UInt8,
            ADD COLUMN IF NOT EXISTS subject_parent_id UInt32,
            ADD COLUMN IF NOT EXISTS match_id UInt64
        ");

        // Удаляем и пересоздаем материализованное представление products_mv
        $this->client->write("DROP VIEW IF EXISTS products_mv");

        // Удаляем и пересоздаем таблицу products_kafka с новыми колонками
        $this->client->write("DROP TABLE IF EXISTS products_kafka");
        
        $this->client->write("
            CREATE TABLE IF NOT EXISTS products_kafka (
              id UInt64,
              name String,
              seller_id UInt32,
              brand_id UInt32,
              subject_id UInt32,
              root UInt64,
              kind_id UInt8,
              subject_parent_id UInt32,
              match_id UInt64,
              created_at Date
            ) ENGINE = Kafka
            SETTINGS
              kafka_broker_list = 'kafka:9092',
              kafka_topic_list = 'products',
              kafka_group_name = 'clickhouse_products_consumer',
              kafka_format = 'JSONEachRow'
        ");

        // Создаем заново материализованное представление
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
        $this->client->write("DROP VIEW IF EXISTS products_mv");

        // Удаляем таблицу products_kafka
        $this->client->write("DROP TABLE IF EXISTS products_kafka");

        // Удаляем добавленные колонки из таблицы product
        $this->client->write("
            ALTER TABLE product
            DROP COLUMN IF EXISTS root,
            DROP COLUMN IF EXISTS kind_id,
            DROP COLUMN IF EXISTS subject_parent_id,
            DROP COLUMN IF EXISTS match_id
        ");

        // Воссоздаем таблицу products_kafka без новых колонок
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

        // Воссоздаем материализованное представление
        $this->client->write("
            CREATE MATERIALIZED VIEW IF NOT EXISTS products_mv
            TO product
            AS SELECT * FROM products_kafka
        ");
    }
}