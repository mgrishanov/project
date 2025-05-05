<?php

declare(strict_types=1);

namespace WB\Parser\Migration\Migrations;

use WB\Parser\Migration\Migration;

class d_2025_05_05_122417_create_brand_table extends Migration
{
    /**
     * Выполнить миграцию
     */
    public function up(): void
    {
        $this->client->write("
            CREATE TABLE IF NOT EXISTS brand (
              id UInt32,
              name String,
              created_at Date,
              updated_at DateTime DEFAULT now() -- поле для версионирования
            ) ENGINE = ReplacingMergeTree(updated_at) -- используем updated_at как версию
            ORDER BY id;
        ");
    }

    /**
     * Откатить миграцию
     */
    public function down(): void
    {
        $this->client->write("
            drop table if exists brand;
        ");
    }
}