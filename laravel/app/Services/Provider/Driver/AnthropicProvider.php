<?php

namespace App\Services\Provider\Driver;

use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\DTO\ProviderResponse;
use App\Services\Provider\DTO\ProviderStreamChunk;

/**
 * Anthropic 供应商
 *
 * Anthropic Claude API 供应商实现
 */
class AnthropicProvider extends AbstractProvider
{
    /**
     * API 版本
     */
    protected string $apiVersion = '2023-06-01';

    /**
     * 支持的模型列表
     */
    protected array $supportedModels = [
        'claude-3-5-sonnet-20241022',
        'claude-3-5-haiku-20241022',
        'claude-3-opus-20240229',
        'claude-3-sonnet-20240229',
        'claude-3-haiku-20240307',
        'claude-2.1',
        'claude-2.0',
        'claude-instant-1.2',
    ];

    /**
     * 获取默认 API 基础 URL
     */
    public function getDefaultBaseUrl(): string
    {
        return 'https://api.anthropic.com/v1';
    }

    /**
     * 获取 API 端点
     */
    public function getEndpoint(ProviderRequest $request): string
    {
        return '/messages';
    }

    /**
     * 获取请求头
     */
    public function getHeaders(): array
    {
        return [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->apiVersion,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * 构建请求体
     */
    public function buildRequestBody(ProviderRequest $request): array
    {
        return $request->toAnthropicFormat();
    }

    /**
     * 解析响应
     */
    public function parseResponse(array $response): ProviderResponse
    {
        return ProviderResponse::fromAnthropic($response);
    }

    /**
     * 解析流式响应块
     */
    public function parseStreamChunk(string $rawChunk): ?ProviderStreamChunk
    {
        return ProviderStreamChunk::fromAnthropic($rawChunk);
    }

    /**
     * 获取支持的模型列表
     */
    public function getModels(): array
    {
        return $this->supportedModels;
    }

    /**
     * 获取供应商名称
     */
    public function getProviderName(): string
    {
        return 'anthropic';
    }
}
