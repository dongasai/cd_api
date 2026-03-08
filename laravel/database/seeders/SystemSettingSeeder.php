<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // 系统设置
            [
                'group' => SystemSetting::GROUP_SYSTEM,
                'key' => 'site_name',
                'value' => 'CdApi',
                'type' => SystemSetting::TYPE_STRING,
                'label' => '系统名称',
                'description' => '系统显示名称',
                'is_public' => true,
                'sort_order' => 1,
            ],
            [
                'group' => SystemSetting::GROUP_SYSTEM,
                'key' => 'site_description',
                'value' => 'AI大模型API代理工具',
                'type' => SystemSetting::TYPE_STRING,
                'label' => '系统描述',
                'description' => '系统描述信息',
                'is_public' => true,
                'sort_order' => 2,
            ],
            [
                'group' => SystemSetting::GROUP_SYSTEM,
                'key' => 'default_model',
                'value' => 'gpt-4',
                'type' => SystemSetting::TYPE_STRING,
                'label' => '默认模型',
                'description' => '未指定模型时使用的默认模型',
                'is_public' => false,
                'sort_order' => 3,
            ],
            [
                'group' => SystemSetting::GROUP_SYSTEM,
                'key' => 'request_timeout',
                'value' => '60',
                'type' => SystemSetting::TYPE_INTEGER,
                'label' => '请求超时(秒)',
                'description' => 'API请求的超时时间',
                'is_public' => false,
                'sort_order' => 4,
            ],
            [
                'group' => SystemSetting::GROUP_SYSTEM,
                'key' => 'max_retries',
                'value' => '3',
                'type' => SystemSetting::TYPE_INTEGER,
                'label' => '最大重试次数',
                'description' => '请求失败时的最大重试次数',
                'is_public' => false,
                'sort_order' => 5,
            ],

            // 配额设置
            [
                'group' => SystemSetting::GROUP_QUOTA,
                'key' => 'default_rate_limit',
                'value' => json_encode(['rpm' => 60, 'tpm' => 100000]),
                'type' => SystemSetting::TYPE_JSON,
                'label' => '默认速率限制',
                'description' => '默认的每分钟请求数和令牌数限制',
                'is_public' => false,
                'sort_order' => 1,
            ],
            [
                'group' => SystemSetting::GROUP_QUOTA,
                'key' => 'quota_warning_threshold',
                'value' => '0.8',
                'type' => SystemSetting::TYPE_FLOAT,
                'label' => '配额警告阈值',
                'description' => '配额使用率达到此阈值时发出警告',
                'is_public' => false,
                'sort_order' => 2,
            ],
            [
                'group' => SystemSetting::GROUP_QUOTA,
                'key' => 'quota_critical_threshold',
                'value' => '0.95',
                'type' => SystemSetting::TYPE_FLOAT,
                'label' => '配额临界阈值',
                'description' => '配额使用率达到此阈值时进入临界状态',
                'is_public' => false,
                'sort_order' => 3,
            ],

            // 安全设置
            [
                'group' => SystemSetting::GROUP_SECURITY,
                'key' => 'api_key_prefix',
                'value' => 'sk-',
                'type' => SystemSetting::TYPE_STRING,
                'label' => 'API Key前缀',
                'description' => '生成的API Key前缀',
                'is_public' => true,
                'sort_order' => 1,
            ],
            [
                'group' => SystemSetting::GROUP_SECURITY,
                'key' => 'key_length',
                'value' => '48',
                'type' => SystemSetting::TYPE_INTEGER,
                'label' => 'Key长度',
                'description' => '生成的API Key长度(不含前缀)',
                'is_public' => false,
                'sort_order' => 2,
            ],
            [
                'group' => SystemSetting::GROUP_SECURITY,
                'key' => 'enable_audit_log',
                'value' => '1',
                'type' => SystemSetting::TYPE_BOOLEAN,
                'label' => '启用审计日志',
                'description' => '是否记录审计日志',
                'is_public' => false,
                'sort_order' => 3,
            ],
            [
                'group' => SystemSetting::GROUP_SECURITY,
                'key' => 'sensitive_fields',
                'value' => json_encode(['api_key', 'password', 'token', 'secret']),
                'type' => SystemSetting::TYPE_ARRAY,
                'label' => '敏感字段',
                'description' => '需要在日志中脱敏的字段名',
                'is_public' => false,
                'sort_order' => 4,
            ],

            // 功能开关
            [
                'group' => SystemSetting::GROUP_FEATURES,
                'key' => 'enable_streaming',
                'value' => '1',
                'type' => SystemSetting::TYPE_BOOLEAN,
                'label' => '启用流式响应',
                'description' => '是否支持流式响应',
                'is_public' => true,
                'sort_order' => 1,
            ],
            [
                'group' => SystemSetting::GROUP_FEATURES,
                'key' => 'enable_cache',
                'value' => '1',
                'type' => SystemSetting::TYPE_BOOLEAN,
                'label' => '启用响应缓存',
                'description' => '是否启用响应缓存',
                'is_public' => false,
                'sort_order' => 2,
            ],
            [
                'group' => SystemSetting::GROUP_FEATURES,
                'key' => 'enable_model_mapping',
                'value' => '1',
                'type' => SystemSetting::TYPE_BOOLEAN,
                'label' => '启用模型映射',
                'description' => '是否启用模型名称映射功能',
                'is_public' => false,
                'sort_order' => 3,
            ],
            [
                'group' => SystemSetting::GROUP_FEATURES,
                'key' => 'enable_fallback',
                'value' => '1',
                'type' => SystemSetting::TYPE_BOOLEAN,
                'label' => '启用渠道降级',
                'description' => '请求失败时是否自动降级到其他渠道',
                'is_public' => false,
                'sort_order' => 4,
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['group' => $setting['group'], 'key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('系统配置已初始化完成');
    }
}
