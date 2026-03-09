<?php

namespace App\Enums;

/**
 * 操作对象枚举
 */
enum OperationTarget: string
{
    case CHANNEL = 'channel';
    case CODING_ACCOUNT = 'coding_account';
    case API_KEY = 'api_key';
    case USER = 'user';
    case MODEL = 'model';
    case SYSTEM = 'system';

    /**
     * 获取操作对象标签
     */
    public function label(): string
    {
        return match ($this) {
            self::CHANNEL => '渠道',
            self::CODING_ACCOUNT => 'Coding账户',
            self::API_KEY => 'API密钥',
            self::USER => '用户',
            self::MODEL => '模型',
            self::SYSTEM => '系统',
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
