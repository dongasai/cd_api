<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 添加 MCP 客户端菜单项
 */
return new class extends Migration
{
    /**
     * 添加菜单
     */
    public function up(): void
    {
        // 在 app_settings 下添加 MCP 客户端菜单
        DB::table('admin_menu')->insert([
            'parent_id' => 56, // app_settings 的 ID
            'title' => 'mcp_clients',
            'uri' => 'mcp-clients',
            'icon' => 'feather icon-plug',
            'order' => 22,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 删除菜单
     */
    public function down(): void
    {
        DB::table('admin_menu')
            ->where('uri', 'mcp-clients')
            ->delete();
    }
};
