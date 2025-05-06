<?php

declare(strict_types=1);

namespace WB\Parser\Migration\Migrations;

use WB\Parser\Migration\Migration;

class d_2025_05_06_064745_create_quantity_day_table extends Migration
{
    /**
     * Выполнить миграцию
     */
    public function up(): void
    {
        $this->client->write("
            CREATE TABLE IF NOT EXISTS quantity_day (
              date Date,
              time DateTime,
              product_id UInt64,
              option_id UInt64,
              store_id UInt32,
              quantity UInt32,
              sales UInt32,
              refund UInt32
            ) ENGINE = MergeTree()
            PARTITION BY toYYYYMMDD(time)
            ORDER BY (product_id, option_id, store_id, time)
            TTL time + INTERVAL 7 DAY
        ");
    }

    /**
     * Откатить миграцию
     */
    public function down(): void
    {
        $this->client->write("
            DROP TABLE IF EXISTS quantity_day
        ");
    }
}