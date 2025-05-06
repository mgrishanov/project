<?php

declare(strict_types=1);

namespace WB\Parser\Migration\Migrations;

use WB\Parser\Migration\Migration;

class d_2025_05_06_071752_create_log_day_kafka_and_mv extends Migration
{
    /**
     * Выполнить миграцию
     */
    public function up(): void
    {
        // Создаем таблицу-очередь для Kafka
        $this->client->write("
            CREATE TABLE IF NOT EXISTS log_day_kafka (
              date Date,
              time DateTime,
              product_id UInt64,
              rating UInt8,
              review_rating Float64,
              feedbacks UInt32,
              volume UInt16,
              view_flags UInt64
            ) ENGINE = Kafka
            SETTINGS kafka_broker_list = 'kafka:9092',
                    kafka_topic_list = 'log_day',
                    kafka_group_name = 'log_day_consumer_group',
                    kafka_format = 'JSONEachRow',
                    kafka_max_block_size = 1048576
        ");
        
        // Создаем материализованное представление для переноса данных из Kafka в основную таблицу
        $this->client->write("
            CREATE MATERIALIZED VIEW IF NOT EXISTS log_day_mv
            TO log_day
            AS SELECT
                date,
                time,
                product_id,
                rating,
                review_rating,
                feedbacks,
                volume,
                view_flags
            FROM log_day_kafka
        ");
    }

    /**
     * Откатить миграцию
     */
    public function down(): void
    {
        // Удаляем материализованное представление
        $this->client->write("
            DROP VIEW IF EXISTS log_day_mv
        ");
        
        // Удаляем таблицу-очередь для Kafka
        $this->client->write("
            DROP TABLE IF EXISTS log_day_kafka
        ");
    }
}