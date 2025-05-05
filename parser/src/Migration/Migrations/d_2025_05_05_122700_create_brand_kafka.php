<?php

declare(strict_types=1);

namespace WB\Parser\Migration\Migrations;

use WB\Parser\Migration\Migration;

class d_2025_05_05_122700_create_brand_kafka extends Migration
{
    /**
     * Выполнить миграцию
     */
    public function up(): void
    {
        $this->client->write("
            CREATE TABLE IF NOT EXISTS brand_kafka (
              id UInt32,
              name String,
              created_at Date
            ) ENGINE = Kafka
            SETTINGS
              kafka_broker_list = 'kafka:9092',
              kafka_topic_list = 'brands',
              kafka_group_name = 'brands',
              kafka_format = 'JSONEachRow';
        ");
    }

    /**
     * Откатить миграцию
     */
    public function down(): void
    {
        $this->client->write("
            drop table if exists brand_kafka;
        ");
    }
}