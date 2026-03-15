<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 注意：body_passthrough 字段存储在 config JSON 字段中
     * 不需要修改数据库结构
     */
    public function up(): void
    {
        // 此迁移仅用于记录，实际字段存储在 config JSON 中
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 此迁移仅用于记录，实际字段存储在 config JSON 中
    }
};
