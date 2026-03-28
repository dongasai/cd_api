<?php

namespace App\Services\Shared\DTO;

/**
 * 容器信息 DTO
 *
 * 用于代码执行工具的容器信息
 * 纯数据容器，不包含业务逻辑
 */
class Container
{
    /**
     * 容器标识符
     */
    public string $id = '';

    /**
     * 容器过期时间（ISO 8601 格式）
     */
    public ?string $expiresAt = null;
}
