<?php

namespace App\Enums;

/**
 * 渠道运营状态枚举
 */
enum ChannelStatus: string
{
    /**
     * 启用
     */
    case ACTIVE = 'active';

    /**
     * 禁用
     */
    case DISABLED = 'disabled';

    /**
     * 获取所有选项（带多语言支持）
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::ACTIVE->value => trans('admin-channel.options.status.active'),
            self::DISABLED->value => trans('admin-channel.options.status.disabled'),
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
