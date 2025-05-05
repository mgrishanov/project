<?php

declare(strict_types=1);

namespace WB\Parser\Migration\Migrations;

use WB\Parser\Migration\Migration;

class d_2025_05_05_122134_drop_old_brand extends Migration
{
    /**
     * Выполнить миграцию
     */
    public function up(): void
    {
        $this->client->write("
            drop table if exists brands_mv;
        ");

        $this->client->write("
            drop table if exists brands_kafka;
        ");

        $this->client->write("
            drop table if exists brands;
        ");
    }

    /**
     * Откатить миграцию
     */
    public function down(): void
    {
        $this->client->write("
            -- Ваш SQL-код для отката миграции
        ");
    }
}