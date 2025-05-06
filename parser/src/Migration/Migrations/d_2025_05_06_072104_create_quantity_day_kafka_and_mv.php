<?php

declare(strict_types=1);

namespace WB\Parser\Migration\Migrations;

use WB\Parser\Migration\Migration;

class d_2025_05_06_072104_create_quantity_day_kafka_and_mv extends Migration
{
    /**
     * Выполнить миграцию
     */
    public function up(): void
    {
        // Создаем таблицу-очередь для Kafka
        $this->client->write("
            CREATE TABLE IF NOT EXISTS quantity_day_kafka (
              date Date,
              time DateTime,
              product_id UInt64,
              option_id UInt64,
              store_id UInt32,
              quantity UInt32,
              sales UInt32,
              refund UInt32
            ) ENGINE = Kafka
            SETTINGS kafka_broker_list = 'kafka:9092',
                    kafka_topic_list = 'quantity_day',
                    kafka_group_name = 'quantity_day_consumer_group',
                    kafka_format = 'JSONEachRow',
                    kafka_max_block_size = 1048576
        ");
        
        // Создаем материализованное представление для переноса данных из Kafka в основную таблицу
        $this->client->write("
            CREATE MATERIALIZED VIEW IF NOT EXISTS quantity_day_mv
            TO quantity_day
            AS SELECT
                date,
                time,
                product_id,
                option_id,
                store_id,
                quantity,
                sales,
                refund
            FROM quantity_day_kafka
        ");
    }

    /**
     * Откатить миграцию
     */
    public function down(): void
    {
        // Удаляем материализованное представление
        $this->client->write("
            DROP VIEW IF EXISTS quantity_day_mv
        ");
        
        // Удаляем таблицу-очередь для Kafka
        $this->client->write("
            DROP TABLE IF EXISTS quantity_day_kafka
        ");
    }
}