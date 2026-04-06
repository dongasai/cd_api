<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 修改 mcp_clients 表的 transport 字段值
 *
 * 将 http_sse 改为 http，以匹配 php-mcp/client 的 TransportType 枚举
 */
return new class extends Migration
{
    /**
     * 修改 transport 字段
     */
    public function up(): void
    {
        // 先更新现有数据
        DB::table('mcp_clients')
            ->where('transport', 'http_sse')
            ->update(['transport' => 'http']);

        // 修改 enum 定义
        DB::statement("ALTER TABLE mcp_clients MODIFY COLUMN transport ENUM('stdio', 'http') DEFAULT 'http'");
    }

    /**
     * 回滚修改
     */
    public function down(): void
    {
        // 先更新数据
        DB::table('mcp_clients')
            ->where('transport', 'http')
            ->update(['transport' => 'http_sse']);

        // 恢复原 enum 定义
        DB::statement("ALTER TABLE mcp_clients MODIFY COLUMN transport ENUM('stdio', 'http_sse') DEFAULT 'http_sse'");
    }
};
