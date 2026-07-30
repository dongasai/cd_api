<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 系统配置预置数据
     */
    public function up(): void
    {
        $now = now();

        $settings = [
            // 系统设置
            ['group' => 'system', 'key' => 'site_name', 'value' => 'CdApi', 'type' => 'string', 'label' => '系统名称', 'description' => '系统显示名称', 'is_public' => 1, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'system', 'key' => 'site_description', 'value' => 'AI大模型API网关工具', 'type' => 'string', 'label' => '系统描述', 'description' => '系统描述信息', 'is_public' => 1, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'system', 'key' => 'default_model', 'value' => 'gpt-4', 'type' => 'string', 'label' => '默认模型', 'description' => '未指定模型时使用的默认模型', 'is_public' => 0, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'system', 'key' => 'request_timeout', 'value' => '60', 'type' => 'integer', 'label' => '请求超时(秒)', 'description' => 'API请求的超时时间', 'is_public' => 0, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'system', 'key' => 'max_retries', 'value' => '3', 'type' => 'integer', 'label' => '最大重试次数', 'description' => '请求失败时的最大重试次数', 'is_public' => 0, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],

            // 安全设置
            ['group' => 'security', 'key' => 'api_key_prefix', 'value' => 'cdapi-', 'type' => 'string', 'label' => 'API Key前缀', 'description' => '生成的API Key前缀', 'is_public' => 1, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'security', 'key' => 'key_length', 'value' => '48', 'type' => 'integer', 'label' => 'Key长度', 'description' => '生成的API Key长度(不含前缀)', 'is_public' => 0, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'security', 'key' => 'enable_audit_log', 'value' => '1', 'type' => 'boolean', 'label' => '启用审计日志', 'description' => '是否记录审计日志', 'is_public' => 0, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'security', 'key' => 'sensitive_fields', 'value' => json_encode(['api_key', 'password', 'token', 'secret']), 'type' => 'array', 'label' => '敏感字段', 'description' => '需要在日志中脱敏的字段名', 'is_public' => 0, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],

            // 功能开关
            ['group' => 'features', 'key' => 'enable_streaming', 'value' => '1', 'type' => 'boolean', 'label' => '启用流式响应', 'description' => '是否支持流式响应', 'is_public' => 1, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'features', 'key' => 'enable_cache', 'value' => '1', 'type' => 'boolean', 'label' => '启用响应缓存', 'description' => '是否启用响应缓存', 'is_public' => 0, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'features', 'key' => 'enable_model_mapping', 'value' => '1', 'type' => 'boolean', 'label' => '启用模型映射', 'description' => '是否启用模型名称映射功能', 'is_public' => 0, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'features', 'key' => 'enable_fallback', 'value' => '1', 'type' => 'boolean', 'label' => '启用渠道降级', 'description' => '请求失败时是否自动降级到其他渠道', 'is_public' => 0, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],

            // 渠道亲和性配置
            ['group' => 'channel_affinity', 'key' => 'enabled', 'value' => '1', 'type' => 'boolean', 'label' => '启用渠道亲和性', 'description' => '是否启用渠道亲和性功能', 'is_public' => 1, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'channel_affinity', 'key' => 'switch_on_success', 'value' => '1', 'type' => 'boolean', 'label' => '成功时切换渠道', 'description' => '请求成功时是否记录亲和性以切换到该渠道', 'is_public' => 1, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],

            // 测试配置
            ['group' => 'test', 'key' => 'default_test_api_key', 'value' => '', 'type' => 'string', 'label' => '默认测试API Key', 'description' => '系统API测试使用的默认API Key', 'is_public' => 0, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],

            // MCP 服务配置
            ['group' => 'mcp', 'key' => 'webparser_base_url', 'value' => 'http://127.0.0.1/api/openai/v1', 'type' => 'string', 'label' => 'WebParser API地址', 'description' => 'AI服务API地址，默认指向本系统内部地址', 'is_public' => 0, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'mcp', 'key' => 'webparser_api_key', 'value' => '', 'type' => 'string', 'label' => 'WebParser API Key', 'description' => '用于AI处理的API Key（必填）', 'is_public' => 0, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'mcp', 'key' => 'webparser_model', 'value' => 'gpt-4o', 'type' => 'string', 'label' => 'WebParser模型', 'description' => '用于AI处理的模型名称', 'is_public' => 0, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'mcp', 'key' => 'webparser_temperature', 'value' => '0.3', 'type' => 'float', 'label' => 'WebParser温度', 'description' => 'AI生成温度参数，0-1之间', 'is_public' => 0, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['group' => $setting['group'], 'key' => $setting['key']],
                $setting
            );
        }
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->whereIn('group', ['system', 'security', 'features', 'channel_affinity', 'test', 'mcp'])
            ->delete();
    }
};
