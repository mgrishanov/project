<?php

declare(strict_types=1);

namespace WB\Parser\Migration\Migrations;

use WB\Parser\Migration\Migration;

class d_2025_05_06_044821_create_option_table extends Migration
{
    /**
     * Выполнить миграцию
     */
    public function up(): void
    {
        $this->client->write("
            CREATE TABLE IF NOT EXISTS option (
              id UInt64,
              name String
            ) ENGINE = MergeTree()
            ORDER BY id
        ");
    }

    /**
     * Откатить миграцию
     */
    public function down(): void
    {
        $this->client->write("
            DROP TABLE IF EXISTS option
        ");
    }
}