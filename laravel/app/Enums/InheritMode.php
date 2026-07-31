<?php

namespace App\Enums;

/**
 * 渠道继承模式枚举
 *
 * 定义子渠道如何从父渠道继承配置的方式
 */
enum InheritMode: string
{
    /**
     * 合并模式（默认）
     *
     * 标量字段：子空值继承父值
     * 数组字段：array_replace_recursive（深度合并）
     */
    case MERGE = 'merge';

    /**
     * 覆盖模式
     *
     * 标量字段：子空值继承父值
     * 数组字段：完全使用子数组（不继承）
     */
    case OVERRIDE = 'override';

    /**
     * 获取所有选项（供 Admin 下拉选择使用）
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::MERGE->value => admin_trans('admin-channel.options.inherit_mode.merge'),
            self::OVERRIDE->value => admin_trans('admin-channel.options.inherit_mode.override'),
        ];
    }

    /**
     * 获取中文标签名称
     */
    public function label(): string
    {
        return match ($this) {
            self::MERGE => '合并',
            self::OVERRIDE => '覆盖',
        };
    }

    /**
     * 判断是否为合并模式
     */
    public function isMerge(): bool
    {
        return $this === self::MERGE;
    }

    /**
     * 判断是否为覆盖模式
     */
    public function isOverride(): bool
    {
        return $this === self::OVERRIDE;
    }
}
