<?php

namespace App\Services\Provider\Driver;

use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\Contracts\ProtocolResponse;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionRequest;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionResponse;
use App\Services\Shared\DTO\StreamChunk;
use App\Services\Shared\DTO\Usage;
use App\Services\Shared\Enums\FinishReason;
use Illuminate\Support\Facades\Log;

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
            return $request->toArray();
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

    /**
     * 解析 OpenAI 流式响应块
     */
    protected function parseOpenAIStreamChunk(string $rawChunk): ?StreamChunk
    {
        Log::debug("parseOpenAIStreamChunk \n".$rawChunk);
        // 处理 "data: " 前缀
        if (str_starts_with($rawChunk, 'data: ')) {
            $rawChunk = substr($rawChunk, 6);
        }

        // 跳过空行和 "[DONE]"
        if (trim($rawChunk) === '' || trim($rawChunk) === '[DONE]') {
            return null;
        }

        $data = json_decode($rawChunk, true);
        if ($data === null) {
            return null;
        }

        $id = $data['id'] ?? '';
        $model = $data['model'] ?? '';
        $choices = $data['choices'] ?? [];
        $choice = $choices[0] ?? [];

        $delta = $choice['delta'] ?? [];
        $finishReason = isset($choice['finish_reason']) && $choice['finish_reason'] !== null
            ? FinishReason::fromOpenAI($choice['finish_reason'])
            : null;

        $contentDelta = $delta['content'] ?? null;
        $reasoningDelta = $delta['reasoning_content'] ?? null;
        $toolCalls = $delta['tool_calls'] ?? null;

        $usage = null;
        if (isset($data['usage'])) {
            $usage = Usage::fromOpenAI($data['usage']);
        }

        return new StreamChunk(
            id: $id,
            model: $model,
            contentDelta: $contentDelta,
            finishReason: $finishReason,
            index: $choice['index'] ?? 0,
            usage: $usage,
            event: '',
            data: $data,
            delta: $contentDelta ?? '',
            toolCalls: $toolCalls,
            reasoningDelta: $reasoningDelta,
        );
    }
}
