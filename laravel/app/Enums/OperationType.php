<?php

namespace App\Enums;

/**
 * 操作类型枚举
 *
 * 格式: 对象_动作
 */
enum OperationType: string
{
    // 渠道操作
    case CHANNEL_CREATE = 'channel_create';
    case CHANNEL_UPDATE = 'channel_update';
    case CHANNEL_DELETE = 'channel_delete';
    case CHANNEL_ENABLE = 'channel_enable';
    case CHANNEL_DISABLE = 'channel_disable';

    // Coding账户操作
    case CODING_ACCOUNT_CREATE = 'coding_account_create';
    case CODING_ACCOUNT_UPDATE = 'coding_account_update';
    case CODING_ACCOUNT_DELETE = 'coding_account_delete';
    case CODING_ACCOUNT_ENABLE = 'coding_account_enable';
    case CODING_ACCOUNT_DISABLE = 'coding_account_disable';
    case CODING_ACCOUNT_SYNC = 'coding_account_sync';
    case CODING_ACCOUNT_REOPEN = 'coding_account_reopen';

    // API密钥操作
    case API_KEY_CREATE = 'api_key_create';
    case API_KEY_UPDATE = 'api_key_update';
    case API_KEY_DELETE = 'api_key_delete';
    case API_KEY_ENABLE = 'api_key_enable';
    case API_KEY_DISABLE = 'api_key_disable';

    // 用户操作
    case USER_CREATE = 'user_create';
    case USER_UPDATE = 'user_update';
    case USER_DELETE = 'user_delete';
    case USER_LOGIN = 'user_login';
    case USER_LOGOUT = 'user_logout';

    // 系统操作
    case SYSTEM_CONFIG_UPDATE = 'system_config_update';
    case SYSTEM_CACHE_CLEAR = 'system_cache_clear';

    /**
     * 获取操作类型标签
     */
    public function label(): string
    {
        return match ($this) {
            // 渠道操作
            self::CHANNEL_CREATE => '渠道-创建',
            self::CHANNEL_UPDATE => '渠道-更新',
            self::CHANNEL_DELETE => '渠道-删除',
            self::CHANNEL_ENABLE => '渠道-启用',
            self::CHANNEL_DISABLE => '渠道-禁用',

            // Coding账户操作
            self::CODING_ACCOUNT_CREATE => 'Coding账户-创建',
            self::CODING_ACCOUNT_UPDATE => 'Coding账户-更新',
            self::CODING_ACCOUNT_DELETE => 'Coding账户-删除',
            self::CODING_ACCOUNT_ENABLE => 'Coding账户-启用',
            self::CODING_ACCOUNT_DISABLE => 'Coding账户-禁用',
            self::CODING_ACCOUNT_SYNC => 'Coding账户-同步',
            self::CODING_ACCOUNT_REOPEN => 'Coding账户-重新开启',

            // API密钥操作
            self::API_KEY_CREATE => 'API密钥-创建',
            self::API_KEY_UPDATE => 'API密钥-更新',
            self::API_KEY_DELETE => 'API密钥-删除',
            self::API_KEY_ENABLE => 'API密钥-启用',
            self::API_KEY_DISABLE => 'API密钥-禁用',

            // 用户操作
            self::USER_CREATE => '用户-创建',
            self::USER_UPDATE => '用户-更新',
            self::USER_DELETE => '用户-删除',
            self::USER_LOGIN => '用户-登录',
            self::USER_LOGOUT => '用户-登出',

            // 系统操作
            self::SYSTEM_CONFIG_UPDATE => '系统-配置更新',
            self::SYSTEM_CACHE_CLEAR => '系统-缓存清除',
        };
    }

    /**
     * 获取操作对象
     */
    public function getTarget(): OperationTarget
    {
        return match ($this) {
            self::CHANNEL_CREATE,
            self::CHANNEL_UPDATE,
            self::CHANNEL_DELETE,
            self::CHANNEL_ENABLE,
            self::CHANNEL_DISABLE => OperationTarget::CHANNEL,

            self::CODING_ACCOUNT_CREATE,
            self::CODING_ACCOUNT_UPDATE,
            self::CODING_ACCOUNT_DELETE,
            self::CODING_ACCOUNT_ENABLE,
            self::CODING_ACCOUNT_DISABLE,
            self::CODING_ACCOUNT_SYNC,
            self::CODING_ACCOUNT_REOPEN => OperationTarget::CODING_ACCOUNT,

            self::API_KEY_CREATE,
            self::API_KEY_UPDATE,
            self::API_KEY_DELETE,
            self::API_KEY_ENABLE,
            self::API_KEY_DISABLE => OperationTarget::API_KEY,

            self::USER_CREATE,
            self::USER_UPDATE,
            self::USER_DELETE,
            self::USER_LOGIN,
            self::USER_LOGOUT => OperationTarget::USER,

            self::SYSTEM_CONFIG_UPDATE,
            self::SYSTEM_CACHE_CLEAR => OperationTarget::SYSTEM,
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
     * 按对象分组获取选项
     */
    public static function optionsGroupedByTarget(): array
    {
        $groups = [];
        foreach (self::cases() as $case) {
            $target = $case->getTarget();
            if (! isset($groups[$target->value])) {
                $groups[$target->value] = [
                    'label' => $target->label(),
                    'options' => [],
                ];
            }
            $groups[$target->value]['options'][$case->value] = $case->label();
        }

        return $groups;
    }
}
