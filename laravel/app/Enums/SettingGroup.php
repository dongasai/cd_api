<?php

namespace App\Enums;

/**
 * 系统设置分组枚举
 */
enum SettingGroup: string
{
    case SYSTEM = 'system';
    case SECURITY = 'security';
    case FEATURES = 'features';
    case CHANNEL_AFFINITY = 'channel_affinity';
    case MCP = 'mcp';
    case TEST = 'test';

    /**
     * 获取分组标签
     */
    public function label(): string
    {
        return match ($this) {
            self::SYSTEM => '系统设置',
            self::SECURITY => '安全设置',
            self::FEATURES => '功能开关',
            self::CHANNEL_AFFINITY => '渠道亲和性',
            self::MCP => 'MCP服务',
            self::TEST => '测试配置',
        };
    }

    /**
     * 获取分组样式
     */
    public function style(): string
    {
        return match ($this) {
            self::SYSTEM => 'primary',
            self::SECURITY => 'danger',
            self::FEATURES => 'info',
            self::CHANNEL_AFFINITY => 'success',
            self::MCP => 'secondary',
            self::TEST => 'warning',
        };
    }

    /**
     * 获取所有选项
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * 获取分组与样式的映射
     */
    public static function styleMapping(): array
    {
        $mapping = [];
        foreach (self::cases() as $case) {
            $mapping[$case->value] = $case->style();
        }

        return $mapping;
    }
}
