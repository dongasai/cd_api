<?php

namespace App\Services\Shared\DTO;

/**
 * 实际请求信息 DTO
 *
 * 纯数据容器，用于记录 Provider 实际发送的请求信息
 */
class ActualRequestInfo
{
    public function __construct(
        public string $url,
        public string $path,
        public array $headers,
        public array $body,
    ) {}

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'path' => $this->path,
            'headers' => $this->headers,
            'body' => $this->body,
        ];
    }
}
