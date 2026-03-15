<?php

namespace App\Services\Shared\Enums;

/**
 * 工具类型枚举
 */
enum ToolType: string
{
    case Function = 'function';

    /**
     * 获取所有可用的工具类型
     */
    public static function available(): array
    {
        return [
            self::Function,
        ];
    }
}
