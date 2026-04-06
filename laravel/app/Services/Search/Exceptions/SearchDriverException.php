<?php

namespace App\Services\Search\Exceptions;

/**
 * 搜索驱动异常
 */
class SearchDriverException extends \RuntimeException
{
    /**
     * 驱动未注册异常
     */
    public static function driverNotRegistered(string $name): self
    {
        return new self("Search driver '{$name}' is not registered.");
    }

    /**
     * 驱动配置无效异常
     */
    public static function invalidConfig(string $name, string $reason): self
    {
        return new self("Search driver '{$name}' has invalid config: {$reason}");
    }

    /**
     * 搜索请求失败异常
     */
    public static function requestFailed(string $name, string $reason): self
    {
        return new self("Search request failed for driver '{$name}': {$reason}");
    }
}
