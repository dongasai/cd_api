<?php

namespace App\Enums;

/**
 * 渠道健康状态枚举
 */
enum ChannelHealthStatus: string
{
    /**
     * 正常
     */
    case NORMAL = 'normal';

    /**
     * 禁用
     */
    case DISABLED = 'disabled';

    /**
     * 获取所有选项
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::NORMAL->value => '正常',
            self::DISABLED->value => '禁用',
        ];
    }

    /**
     * 获取标签样式
     */
    public function labelStyle(): string
    {
        return match ($this) {
            self::NORMAL => 'success',
            self::DISABLED => 'danger',
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
