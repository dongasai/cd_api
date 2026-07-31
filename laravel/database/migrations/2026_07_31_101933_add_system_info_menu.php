<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 幂等检查：是否已存在该菜单
        $exists = DB::table('admin_menu')
            ->where('title', 'system_info')
            ->exists();

        if ($exists) {
            return;
        }

        // 动态查询"系统管理"菜单 ID
        $parentId = DB::table('admin_menu')
            ->where('title', 'system')
            ->value('id');

        DB::table('admin_menu')->insert([
            'parent_id' => $parentId ?: 0,
            'order' => 28,
            'title' => 'system_info',
            'icon' => 'feather icon-info',
            'uri' => 'system-info',
            'show' => 1,
            'extension' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('admin_menu')
            ->where('title', 'system_info')
            ->delete();
    }
};
