<?php

namespace App\Enums;

/**
 * 操作来源枚举
 */
enum OperationSource: string
{
    case ADMIN = 'admin';
    case SCHEDULE = 'schedule';
    case SYSTEM = 'system';
    case API = 'api';

    /**
     * 获取操作来源标签
     */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => '管理员操作',
            self::SCHEDULE => '定时任务',
            self::SYSTEM => '系统自动',
            self::API => 'API调用',
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
}
