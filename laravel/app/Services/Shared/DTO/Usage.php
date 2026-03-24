<?php

namespace App\Services\Shared\DTO;

/**
 * 使用量 DTO（合并 TokenUsage + StandardUsage）
 *
 * 纯数据容器，不包含业务逻辑
 */
class Usage
{
    /**
     * 输入 Token 数量
     */
    public int $inputTokens = 0;

    /**
     * 输出 Token 数量
     */
    public int $outputTokens = 0;

    /**
     * 总 Token 数量
     */
    public ?int $totalTokens = null;

    /**
     * 缓存读取输入 Token
     */
    public ?int $cacheReadInputTokens = null;

    /**
     * 缓存创建输入 Token
     */
    public ?int $cacheCreationInputTokens = null;

    /**
     * OpenAI cached_tokens
     */
    public ?int $cachedTokens = null;

    /**
     * 缓存创建详情（Anthropic）
     */
    public ?array $cacheCreation = null;

    /**
     * 推理地理位置（Anthropic）
     */
    public ?string $inferenceGeo = null;

    /**
     * 服务端工具使用（Anthropic）
     */
    public ?array $serverToolUse = null;

    /**
     * 服务层级（Anthropic）
     */
    public ?string $serviceTier = null;

    /**
     * 音频 Token
     */
    public ?int $audioTokens = null;

    /**
     * 推理 Token
     */
    public ?int $reasoningTokens = null;

    /**
     * 获取总 Token 数量
     */
    public function getTotalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * 合并两个使用量对象（用于流式累加）
     */
    public function merge(Usage $other): self
    {
        $result = new self;
        $result->inputTokens = $this->inputTokens + $other->inputTokens;
        $result->outputTokens = $this->outputTokens + $other->outputTokens;
        $result->cacheReadInputTokens = ($this->cacheReadInputTokens ?? 0) + ($other->cacheReadInputTokens ?? 0);
        $result->cacheCreationInputTokens = ($this->cacheCreationInputTokens ?? 0) + ($other->cacheCreationInputTokens ?? 0);
        $result->cachedTokens = ($this->cachedTokens ?? 0) + ($other->cachedTokens ?? 0);
        $result->cacheCreation = $this->cacheCreation ?? $other->cacheCreation;
        $result->inferenceGeo = $this->inferenceGeo ?? $other->inferenceGeo;
        $result->serverToolUse = $this->serverToolUse ?? $other->serverToolUse;
        $result->serviceTier = $this->serviceTier ?? $other->serviceTier;
        $result->audioTokens = ($this->audioTokens ?? 0) + ($other->audioTokens ?? 0);
        $result->reasoningTokens = ($this->reasoningTokens ?? 0) + ($other->reasoningTokens ?? 0);

        return $result;
    }
}
