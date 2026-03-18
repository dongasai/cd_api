<?php

namespace App\Enums;

/**
 * 渠道运营状态枚举
 */
enum ChannelStatus: string
{
    /**
     * 正常
     */
    case ACTIVE = 'active';

    /**
     * 禁用
     */
    case DISABLED = 'disabled';

    /**
     * 维护中
     */
    case MAINTENANCE = 'maintenance';

    /**
     * 获取所有选项
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::ACTIVE->value => '正常',
            self::DISABLED->value => '禁用',
            self::MAINTENANCE->value => '维护中',
        ];
    }

    /**
     * 获取标签样式
     */
    public function labelStyle(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::DISABLED => 'default',
            self::MAINTENANCE => 'warning',
        };
    }

    /**
     * 获取中文标签
     */
    public function label(): string
    {
        return self::options()[$this->value];
    }
}
