<?php

namespace App\Services\Protocol\Contracts;

use App\Services\Shared\DTO\Response as SharedResponse;

/**
 * 协议响应接口
 *
 * 所有协议响应结构体必须实现此接口
 */
interface ProtocolResponse
{
    /**
     * 转换为数组
     */
    public function toArray(): array;

    /**
     * 转换为 Shared\DTO
     */
    public function toSharedDTO(): SharedResponse;

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(object $dto): static;

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static;

    /**
     * 获取响应 ID
     */
    public function getId(): string;

    /**
     * 获取模型名称
     */
    public function getModel(): string;

    /**
     * 获取使用量
     */
    public function getUsage(): ?object;
}
