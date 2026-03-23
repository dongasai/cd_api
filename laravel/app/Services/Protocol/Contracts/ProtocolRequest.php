<?php

namespace App\Services\Protocol\Contracts;

use App\Services\Shared\DTO\Request as SharedRequest;

/**
 * 协议请求接口
 *
 * 所有协议请求结构体必须实现此接口
 */
interface ProtocolRequest
{
    /**
     * 转换为数组
     */
    public function toArray(): array;

    /**
     * 转换为 Shared\DTO
     */
    public function toSharedDTO(): SharedRequest;

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(object $dto): static;

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static;

    /**
     * 获取模型名称
     */
    public function getModel(): string;

    /**
     * 设置模型名称
     */
    public function setModel(string $model): static;

    /**
     * 是否流式请求
     */
    public function isStream(): bool;

    /**
     * 设置流式标志
     */
    public function setStream(bool $stream): static;

    /**
     * 设置原始请求体（用于 body_passthrough）
     */
    public function setRawBodyString(string $rawBody): static;
}
