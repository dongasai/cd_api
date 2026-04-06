<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 创建账户管理顶级菜单
 *
 * 创建新的顶级菜单"账户管理"，将 Coding账户和错误处理规则移至此菜单下
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 创建顶级菜单"账户管理"
        $accountMenuId = DB::table('admin_menu')->insertGetId([
            'parent_id' => 0,
            'order' => 4, // 放在渠道管理后面
            'title' => 'account_management',
            'icon' => 'feather icon-briefcase',
            'uri' => null,
            'extension' => '',
            'show' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 将 Coding账户菜单移至账户管理下
        DB::table('admin_menu')
            ->where('id', 61) // coding_accounts
            ->update([
                'parent_id' => $accountMenuId,
                'order' => 1,
                'updated_at' => now(),
            ]);

        // 创建错误处理规则子菜单
        DB::table('admin_menu')->insert([
            'parent_id' => $accountMenuId,
            'order' => 2,
            'title' => 'coding_error_rules',
            'icon' => 'feather icon-alert-triangle',
            'uri' => 'coding-error-rules',
            'extension' => '',
            'show' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 更新渠道管理下的其他子菜单排序
        DB::table('admin_menu')
            ->where('parent_id', 51) // channels
            ->where('id', '!=', 61)
            ->update([
                'order' => DB::raw('`order` - 1'),
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 查找账户管理菜单ID
        $accountMenuId = DB::table('admin_menu')
            ->where('title', 'account_management')
            ->value('id');

        if ($accountMenuId) {
            // 将 Coding账户移回渠道管理
            DB::table('admin_menu')
                ->where('id', 61)
                ->update([
                    'parent_id' => 51,
                    'order' => 5,
                    'updated_at' => now(),
                ]);

            // 删除错误处理规则菜单
            DB::table('admin_menu')
                ->where('parent_id', $accountMenuId)
                ->where('uri', 'coding-error-rules')
                ->delete();

            // 删除账户管理顶级菜单
            DB::table('admin_menu')
                ->where('id', $accountMenuId)
                ->delete();
        }

        // 恢复渠道管理下的子菜单排序
        DB::table('admin_menu')
            ->where('parent_id', 51)
            ->update([
                'order' => DB::raw('`order` + 1'),
                'updated_at' => now(),
            ]);
    }
};
