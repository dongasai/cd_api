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

    /**
     * 过滤响应中的 thinking 内容块
     */
    public function filterThinking(bool $filter = true): static;

    /**
     * 流式后处理
     *
     * 流式响应结束后，协议特定的处理逻辑
     * - 默认实现：无操作（通过 ProtocolResponseTrait）
     * - Responses API：提取完整内容，存储状态
     *
     * @param  array  $chunks  累积的流式块 (StreamChunk[])
     * @param  object|null  $context  协议上下文（从请求传递）
     */
    public function postStreamProcess(array $chunks, ?object $context = null): void;
}
