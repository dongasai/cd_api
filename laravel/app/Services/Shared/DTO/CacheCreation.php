<?php

namespace App\Services\Shared\DTO;

/**
 * 缓存创建详情 DTO
 *
 * 纯数据容器，不包含业务逻辑
 */
class CacheCreation
{
    /**
     * 1小时缓存创建的输入 Token 数
     */
    public int $ephemeral1hInputTokens = 0;

    /**
     * 5分钟缓存创建的输入 Token 数
     */
    public int $ephemeral5mInputTokens = 0;
}
