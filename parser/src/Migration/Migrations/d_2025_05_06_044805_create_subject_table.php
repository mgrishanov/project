<?php

declare(strict_types=1);

namespace WB\Parser\Migration\Migrations;

use WB\Parser\Migration\Migration;

class d_2025_05_06_044805_create_subject_table extends Migration
{
    /**
     * Выполнить миграцию
     */
    public function up(): void
    {
        $this->client->write("
            CREATE TABLE IF NOT EXISTS subject (
              id UInt32,
              parent_id UInt32,
              name String,
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
            DROP TABLE IF EXISTS subject
        ");
    }
}