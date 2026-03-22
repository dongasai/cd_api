<?php

namespace App\Services\Provider\Driver;

use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\Contracts\ProtocolResponse;
use App\Services\Protocol\Driver\Anthropic\MessagesRequest;
use App\Services\Protocol\Driver\Anthropic\MessagesResponse;
use App\Services\Shared\DTO\StreamChunk;
use App\Services\Shared\DTO\Usage;
use App\Services\Shared\Enums\FinishReason;
use Illuminate\Support\Facades\Log;

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
     * Header 黑名单（不允许穿透到上游的 header）
     */
    protected array $headerBlacklist = [
        'x-api-key',
    ];

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
    public function getEndpoint(ProtocolRequest $request): string
    {
        return '/messages';
    }

    /**
     * 获取请求头
     */
    public function getHeaders(): array
    {
        $headers = [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->apiVersion,
            'Content-Type' => 'application/json',
        ];

        return $this->mergeForwardedHeaders($headers);
    }

    /**
     * 构建请求体
     *
     * 接收 MessagesRequest 协议结构体
     */
    public function buildRequestBody(ProtocolRequest $request): array
    {
        // 如果是 Anthropic 协议请求，直接转数组
        if ($request instanceof MessagesRequest) {
            return $request->toArray();
        }

        // 其他协议需要转换
        throw new \InvalidArgumentException('AnthropicProvider requires MessagesRequest');
    }

    /**
     * 解析响应
     *
     * 返回 MessagesResponse 协议结构体
     */
    public function parseResponse(array $response): ProtocolResponse
    {
        return MessagesResponse::fromArray($response);
    }

    /**
     * 解析流式响应块
     */
    public function parseStreamChunk(string $rawChunk): ?StreamChunk
    {
        return $this->parseAnthropicStreamChunk($rawChunk);
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

    /**
     * 解析 Anthropic 流式响应块
     */
    protected function parseAnthropicStreamChunk(string $rawEvent): ?StreamChunk
    {
        Log::debug("parseAnthropicStreamChunk \n".$rawEvent);

        $lines = explode("\n", trim($rawEvent));
        $event = '';
        $data = '';

        // 解析 SSE 格式
        foreach ($lines as $line) {
            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data = trim(substr($line, 5));
            }
        }

        if (empty($data)) {
            return null;
        }

        $parsed = json_decode($data, true);
        if ($parsed === null) {
            return null;
        }

        $delta = '';
        $id = null;
        $model = null;
        $finishReason = null;
        $usage = null;
        $reasoningDelta = null;
        $toolCalls = null;

        // 根据事件类型解析数据
        switch ($event) {
            case 'message_start':
                $message = $parsed['message'] ?? [];
                $id = $message['id'] ?? null;
                $model = $message['model'] ?? null;
                if (isset($message['usage'])) {
                    $usage = Usage::fromAnthropic($message['usage']);
                }
                break;

            case 'ping':
                // ping 事件用于保持连接活跃
                break;

            case 'content_block_start':
                // 处理工具调用开始
                $contentBlock = $parsed['content_block'] ?? [];
                if (($contentBlock['type'] ?? '') === 'tool_use') {
                    $toolCalls = [[
                        'id' => $contentBlock['id'] ?? '',
                        'type' => 'function',
                        'function' => [
                            'name' => $contentBlock['name'] ?? '',
                            'arguments' => '',
                        ],
                        'index' => $parsed['index'] ?? 0,
                    ]];
                }
                break;

            case 'content_block_delta':
                // 先获取 delta 类型
                $deltaType = $parsed['delta']['type'] ?? '';

                // 处理文本增量 (text_delta)
                if ($deltaType === 'text_delta' && isset($parsed['delta']['text'])) {
                    $delta = $parsed['delta']['text'];
                }
                // 处理思考增量 (thinking_delta)
                if ($deltaType === 'thinking_delta' && isset($parsed['delta']['thinking'])) {
                    $reasoningDelta = $parsed['delta']['thinking'];
                }
                // 处理工具调用参数增量 (input_json_delta)
                if ($deltaType === 'input_json_delta' && isset($parsed['delta']['partial_json'])) {
                    $toolCalls = [[
                        'index' => $parsed['index'] ?? 0,
                        'function' => [
                            'arguments' => $parsed['delta']['partial_json'],
                        ],
                    ]];
                }
                break;

            case 'message_delta':
                $usage = isset($parsed['usage'])
                    ? Usage::fromAnthropic($parsed['usage'])
                    : null;
                $stopReason = $parsed['delta']['stop_reason'] ?? null;
                if ($stopReason !== null) {
                    $finishReason = FinishReason::fromAnthropic($stopReason);
                }
                break;

            case 'content_block_stop':
            case 'message_stop':
                break;
        }

        return new StreamChunk(
            id: $id ?? '',
            model: $model ?? '',
            contentDelta: $delta !== '' ? $delta : null,
            finishReason: $finishReason,
            index: 0,
            usage: $usage,
            event: $event,
            data: $parsed,
            delta: $delta,
            toolCalls: $toolCalls,
            reasoningDelta: $reasoningDelta,
        );
    }
}
