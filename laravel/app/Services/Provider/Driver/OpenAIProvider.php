<?php

namespace App\Services\Provider\Driver;

use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\Contracts\ProtocolResponse;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionRequest;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionResponse;
use App\Services\Shared\DTO\StreamChunk;

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
    public function getEndpoint(ProtocolRequest $request): string
    {
        return '/chat/completions';
    }

    /**
     * 获取请求头
     */
    public function getHeaders(): array
    {
        $headers = [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ];

        return $this->mergeForwardedHeaders($headers);
    }

    /**
     * 构建请求体
     *
     * 接收 ChatCompletionRequest 协议结构体
     */
    public function buildRequestBody(ProtocolRequest $request): array
    {
        // 如果是 OpenAI 协议请求，直接转数组
        if ($request instanceof ChatCompletionRequest) {
            $body = $request->toArray();

            // 检查渠道配置：是否强制附加 stream_options
            $forceStreamOptions = $this->config['force_stream_options'] ?? false;

            // 流式请求时，根据配置决定是否附加 stream_options
            if ($forceStreamOptions && ($body['stream'] ?? false) === true) {
                if (! isset($body['stream_options'])) {
                    $body['stream_options'] = ['include_usage' => true];
                } elseif (! isset($body['stream_options']['include_usage'])) {
                    $body['stream_options']['include_usage'] = true;
                }
            }

            return $body;
        }

        // 其他协议需要转换
        throw new \InvalidArgumentException('OpenAIProvider requires ChatCompletionRequest');
    }

    /**
     * 解析响应
     *
     * 返回 ChatCompletionResponse 协议结构体
     */
    public function parseResponse(array $response): ProtocolResponse
    {
        return ChatCompletionResponse::fromArray($response);
    }

    /**
     * 解析流式响应块
     */
    public function parseStreamChunk(string $rawChunk): ?StreamChunk
    {
        return $this->parseOpenAIStreamChunk($rawChunk);
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
