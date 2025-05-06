<?php

declare(strict_types=1);

namespace WB\Parser\Migration\Migrations;

use WB\Parser\Migration\Migration;

class d_2025_05_06_044743_create_store_table extends Migration
{
    /**
     * Выполнить миграцию
     */
    public function up(): void
    {
        $this->client->write("
            CREATE TABLE IF NOT EXISTS store (
              id UInt32,
              name String,
              is_fbo UInt8,
              created_at Date,
              updated_at DateTime DEFAULT now()
            ) ENGINE = ReplacingMergeTree(updated_at)
            ORDER BY id
        ");
    }

    /**
     * Откатить миграцию
     */
    public function down(): void
    {
        $this->client->write("
            DROP TABLE IF EXISTS store
        ");
    }
}