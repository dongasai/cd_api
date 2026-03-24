<?php

namespace App\Services\Shared\DTO;

/**
 * 实际请求信息 DTO
 *
 * 纯数据容器，用于记录 Provider 实际发送的请求信息
 */
class ActualRequestInfo
{
    /**
     * 请求完整 URL
     */
    public string $url;

    /**
     * 请求路径
     */
    public string $path;

    /**
     * 请求头
     */
    public array $headers;

    /**
     * 请求体
     */
    public array $body;
}
