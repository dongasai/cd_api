<?php

namespace App\Services\Provider\Driver;

use App\Services\Shared\DTO\Request;
use App\Services\Shared\DTO\Response;
use App\Services\Shared\DTO\StreamChunk;
use App\Services\Shared\DTO\ToolCall;
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
    public function getEndpoint(Request $request): string
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
     */
    public function buildRequestBody(Request $request): array
    {
        return $this->toAnthropicFormat($request);
    }

    /**
     * 解析响应
     */
    public function parseResponse(array $response): Response
    {
        return $this->parseAnthropicResponse($response);
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
     * 将 Request 转换为 Anthropic 格式
     */
    protected function toAnthropicFormat(Request $request): array
    {
        $messages = [];

        foreach ($request->messages as $message) {
            $role = $message->role->value;

            // system 消息应该放到 system 字段，不处理
            if ($role === 'system') {
                continue;
            }

            // tool 消息需要转换为 user 消息中的 tool_result 内容块
            if ($role === 'tool') {
                $toolCallId = $message->toolCallId;
                $toolContent = $message->content ?? '';

                // Anthropic 格式：tool_result 作为 user 消息的内容块
                $messages[] = [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'tool_result',
                            'tool_use_id' => $toolCallId,
                            'content' => $toolContent,
                        ],
                    ],
                ];

                continue;
            }

            $messages[] = $message->toArray();
        }

        // 合并连续的 user 消息（Anthropic 要求 tool_result 和其他内容在同一个 user 消息中）
        $filteredMessages = [];
        $previousUserMessage = null;

        foreach ($messages as $message) {
            if ($message['role'] === 'user') {
                if ($previousUserMessage !== null) {
                    // 合并到前一个 user 消息
                    $content = $message['content'] ?? [];
                    if (is_array($content)) {
                        $previousContent = $previousUserMessage['content'] ?? [];
                        if (is_array($previousContent)) {
                            $previousContent = array_merge($previousContent, $content);
                        } else {
                            $previousContent = $content;
                        }
                        $previousUserMessage['content'] = $previousContent;
                    }
                } else {
                    // 第一个 user 消息，直接添加
                    $previousUserMessage = $message;
                }
            } else {
                // 非 user 消息，先保存之前的 user 消息，然后添加当前消息
                if ($previousUserMessage !== null) {
                    $filteredMessages[] = $previousUserMessage;
                    $previousUserMessage = null;
                }
                $filteredMessages[] = $message;
            }
        }

        // 添加最后一个 user 消息（如果有）
        if ($previousUserMessage !== null) {
            $filteredMessages[] = $previousUserMessage;
        }

        // 构建标准字段
        $result = [
            'model' => $request->model,
            'messages' => $filteredMessages,
        ];

        // max_tokens 对于 Anthropic API 是必需的
        if ($request->maxTokens !== null) {
            $result['max_tokens'] = $request->maxTokens;
        }

        // system 字段：转换为数组格式（阿里云 Coding 要求）
        if ($request->system !== null) {
            // 如果是字符串，转换为数组格式
            if (is_string($request->system)) {
                $result['system'] = [
                    [
                        'type' => 'text',
                        'text' => $request->system,
                    ],
                ];
            } else {
                // 已经是数组格式，保持不变
                $result['system'] = $request->system;
            }
        }
        if ($request->temperature !== null) {
            $result['temperature'] = $request->temperature;
        }
        if ($request->topP !== null) {
            $result['top_p'] = $request->topP;
        }
        if ($request->topK !== null) {
            $result['top_k'] = $request->topK;
        }
        if ($request->stopSequences !== null) {
            $result['stop_sequences'] = $request->stopSequences;
        }
        if ($request->stream) {
            $result['stream'] = true;
        }
        if ($request->tools !== null) {
            $result['tools'] = $request->tools;
        }
        if ($request->toolChoice !== null) {
            $result['tool_choice'] = $request->toolChoice;
        }
        if ($request->metadata !== null) {
            $result['metadata'] = $request->metadata;
        }
        if ($request->thinking !== null) {
            $result['thinking'] = $request->thinking;
        }

        // 合并 additionalParams，但过滤掉 OpenAI 特有的字段
        $openaiSpecificFields = ['stream_options', 'response_format', 'seed', 'logprobs', 'top_logprobs', 'n'];
        $filteredAdditionalParams = array_filter(
            $request->additionalParams,
            fn ($key) => ! in_array($key, $openaiSpecificFields),
            ARRAY_FILTER_USE_KEY
        );

        return array_merge($result, $filteredAdditionalParams);
    }

    /**
     * 解析 Anthropic 响应为 Response
     */
    protected function parseAnthropicResponse(array $response): Response
    {
        $id = $response['id'] ?? '';
        $model = $response['model'] ?? '';
        $stopReason = $response['stop_reason'] ?? null;

        $content = '';
        $toolCalls = null;

        // 解析内容块
        if (isset($response['content']) && is_array($response['content'])) {
            foreach ($response['content'] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $content .= $block['text'] ?? '';
                } elseif (($block['type'] ?? '') === 'tool_use') {
                    $toolCalls[] = ToolCall::fromAnthropic($block);
                }
            }
        }

        // 处理 Token 使用量
        $usage = null;
        if (isset($response['usage'])) {
            $usage = Usage::fromAnthropic($response['usage']);
        }

        $finishReason = $stopReason !== null
            ? FinishReason::fromAnthropic($stopReason)
            : null;

        return new Response(
            id: $id,
            model: $model,
            choices: [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => $content,
                    'tool_calls' => $toolCalls ? array_map(fn ($tc) => $tc->toOpenAI(), $toolCalls) : null,
                ],
                'finish_reason' => $stopReason,
            ]],
            usage: $usage,
            finishReason: $finishReason,
            toolCalls: $toolCalls,
            rawResponse: $response,
        );
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
