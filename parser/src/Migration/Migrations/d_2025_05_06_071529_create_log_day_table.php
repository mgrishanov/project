<?php

declare(strict_types=1);

namespace WB\Parser\Migration\Migrations;

use WB\Parser\Migration\Migration;

class d_2025_05_06_071529_create_log_day_table extends Migration
{
    /**
     * Выполнить миграцию
     */
    public function up(): void
    {
        $this->client->write("
            CREATE TABLE IF NOT EXISTS log_day (
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
    }

    /**
     * Откатить миграцию
     */
    public function down(): void
    {
        $this->client->write("
            DROP TABLE IF EXISTS log_day
        ");
    }
}