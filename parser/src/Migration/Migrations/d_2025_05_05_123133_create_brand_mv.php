<?php

declare(strict_types=1);

namespace WB\Parser\Migration\Migrations;

use WB\Parser\Migration\Migration;

class d_2025_05_05_123133_create_brand_mv extends Migration
{
    /**
     * Выполнить миграцию
     */
    public function up(): void
    {
        $this->client->write("
            CREATE MATERIALIZED VIEW IF NOT EXISTS brands_mv
            TO brand
            AS SELECT * FROM brand_kafka;
        ");
    }

    /**
     * Откатить миграцию
     */
    public function down(): void
    {
        $this->client->write("
            drop materialized view if exists brands_mv;
        ");
    }
}