<?php

namespace App\Services\Provider\Driver;

use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\DTO\ProviderResponse;
use App\Services\Provider\DTO\ProviderStreamChunk;

/**
 * OpenAI 供应商
 *
 * OpenAI 官方 API 供应商实现
 */
class OpenAIProvider extends AbstractProvider
{
    /**
     * 支持的模型列表
     */
    protected array $supportedModels = [
        'gpt-4o',
        'gpt-4o-mini',
        'gpt-4-turbo',
        'gpt-4-turbo-preview',
        'gpt-4',
        'gpt-4-32k',
        'gpt-3.5-turbo',
        'gpt-3.5-turbo-16k',
        'o1',
        'o1-mini',
        'o1-preview',
    ];

    /**
     * 获取默认 API 基础 URL
     */
    public function getDefaultBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }

    /**
     * 获取 API 端点
     */
    public function getEndpoint(ProviderRequest $request): string
    {
        return '/chat/completions';
    }

    /**
     * 获取请求头
     */
    public function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * 构建请求体
     */
    public function buildRequestBody(ProviderRequest $request): array
    {
        return $request->toOpenAIFormat();
    }

    /**
     * 解析响应
     */
    public function parseResponse(array $response): ProviderResponse
    {
        return ProviderResponse::fromOpenAI($response);
    }

    /**
     * 解析流式响应块
     */
    public function parseStreamChunk(string $rawChunk): ?ProviderStreamChunk
    {
        return ProviderStreamChunk::fromOpenAI($rawChunk);
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
        return 'openai';
    }
}
