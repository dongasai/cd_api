<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 更新菜单标题为翻译键格式
        $menuMappings = [
            50 => 'admin.menu_titles.dashboard',
            52 => 'admin.menu_titles.api_keys',
            77 => 'admin.menu_titles.model_test',
            51 => 'admin.menu_titles.channels',
            57 => 'admin.menu_titles.channel_list',
            58 => 'admin.menu_titles.channel_groups',
            59 => 'admin.menu_titles.channel_tags',
            60 => 'admin.menu_titles.channel_models',
            61 => 'admin.menu_titles.coding_accounts',
            54 => 'admin.menu_titles.model_management',
            62 => 'admin.menu_titles.model_list',
            55 => 'admin.menu_titles.logs',
            64 => 'admin.menu_titles.audit_logs',
            65 => 'admin.menu_titles.request_logs',
            66 => 'admin.menu_titles.response_logs',
            67 => 'admin.menu_titles.channel_request_logs',
            68 => 'admin.menu_titles.operation_logs',
            56 => 'admin.menu_titles.system_settings',
            69 => 'admin.menu_titles.settings',
            70 => 'admin.menu_titles.channel_affinity',
            76 => 'admin.menu_titles.user_agents',
            71 => 'admin.menu_titles.system',
            72 => 'admin.menu_titles.administrators',
            73 => 'admin.menu_titles.roles',
            74 => 'admin.menu_titles.permissions',
            75 => 'admin.menu_titles.menu',
        ];

        foreach ($menuMappings as $id => $title) {
            \DB::table('admin_menu')->where('id', $id)->update(['title' => $title]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 回滚：恢复原始中文标题
        $menuMappings = [
            50 => '仪表盘',
            52 => 'API密钥管理',
            77 => '模型测试',
            51 => '渠道管理',
            57 => '渠道列表',
            58 => '渠道分组',
            59 => '渠道标签',
            60 => '渠道模型配置',
            61 => 'Coding账户',
            54 => '模型管理',
            62 => '模型列表',
            55 => '日志中心',
            64 => '审计日志',
            65 => '请求日志',
            66 => '响应日志',
            67 => '渠道请求日志',
            68 => '操作日志',
            56 => '系统设置',
            69 => '系统配置',
            70 => '渠道亲和性规则',
            76 => 'User-Agent规则',
            71 => '系统管理',
            72 => '管理员',
            73 => '角色',
            74 => '权限',
            75 => '菜单',
        ];

        foreach ($menuMappings as $id => $title) {
            \DB::table('admin_menu')->where('id', $id)->update(['title' => $title]);
        }
    }
};
