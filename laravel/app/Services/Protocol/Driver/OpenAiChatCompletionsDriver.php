<?php

namespace App\Services\Protocol\Driver;

use App\Services\Protocol\DTO\StandardRequest;
use App\Services\Protocol\DTO\StandardResponse;
use App\Services\Protocol\DTO\StandardStreamEvent;
use App\Services\Protocol\DTO\StandardToolCall;

/**
 * OpenAI Chat Completions 协议驱动
 */
class OpenAiChatCompletionsDriver extends AbstractDriver
{
    /**
     * 协议名称
     */
    public const PROTOCOL_NAME = 'openai_chat_completions';

    /**
     * 支持的结束原因
     */
    public const FINISH_REASONS = ['stop', 'length', 'tool_calls', 'content_filter'];

    /**
     * 获取协议名称
     */
    public function getProtocolName(): string
    {
        return self::PROTOCOL_NAME;
    }

    /**
     * 解析原始请求为标准格式
     */
    public function parseRequest(array $rawRequest): StandardRequest
    {
        return StandardRequest::fromOpenAI($rawRequest);
    }

    /**
     * 从标准格式构建 OpenAI 响应
     */
    public function buildResponse(StandardResponse $standardResponse): array
    {
        return $standardResponse->toOpenAI();
    }

    /**
     * 解析 OpenAI 流式事件
     */
    public function parseStreamEvent(string $rawEvent): ?StandardStreamEvent
    {
        // 解析 SSE 格式: data: {...}
        if (! str_starts_with($rawEvent, 'data: ')) {
            return null;
        }

        $data = trim(substr($rawEvent, 6));

        // 处理结束标记
        if ($data === '[DONE]') {
            return null;
        }

        $parsed = $this->safeJsonDecode($data);
        if ($parsed === null) {
            return null;
        }

        return $this->parseStreamChunk($parsed);
    }

    /**
     * 从标准格式构建 OpenAI 流式块
     */
    public function buildStreamChunk(StandardStreamEvent $event): string
    {
        $chunk = $this->buildStreamChunkData($event);

        return 'data: '.$this->safeJsonEncode($chunk)."\n\n";
    }

    /**
     * 构建流式结束标记
     */
    public function buildStreamDone(): string
    {
        return "data: [DONE]\n\n";
    }

    /**
     * 获取请求中的模型名称
     */
    public function extractModel(array $rawRequest): string
    {
        return $rawRequest['model'] ?? '';
    }

    /**
     * 构建错误响应
     */
    public function buildErrorResponse(string $message, string $type = 'error', int $code = 500): array
    {
        return [
            'error' => [
                'message' => $message,
                'type' => $type,
                'code' => $code,
            ],
        ];
    }

    /**
     * 从标准请求构建上游请求
     */
    public function buildUpstreamRequest(StandardRequest $standardRequest): array
    {
        return $standardRequest->toOpenAI();
    }

    /**
     * 从上游响应解析标准响应
     */
    public function parseUpstreamResponse(array $response): StandardResponse
    {
        return StandardResponse::fromOpenAI($response);
    }

    // ==================== 私有方法 ====================

    /**
     * 解析流式块
     */
    private function parseStreamChunk(array $chunk): ?StandardStreamEvent
    {
        $id = $chunk['id'] ?? '';
        $choices = $chunk['choices'] ?? [];

        if (empty($choices)) {
            return null;
        }

        $choice = $choices[0];
        $delta = $choice['delta'] ?? [];
        $finishReason = $choice['finish_reason'] ?? null;

        // 开始事件
        if (isset($delta['role'])) {
            return StandardStreamEvent::start(
                id: $id,
                model: $chunk['model'] ?? '',
                role: $delta['role']
            );
        }

        // 结束事件
        if ($finishReason !== null) {
            return StandardStreamEvent::finish(
                id: $id,
                finishReason: $finishReason
            );
        }

        // 推理内容增量（DeepSeek、Kimi 等思考模型）
        if (isset($delta['reasoning_content']) && $delta['reasoning_content'] !== '') {
            return StandardStreamEvent::reasoningDelta(
                id: $id,
                reasoning: $delta['reasoning_content']
            );
        }

        // 内容增量
        if (isset($delta['content']) && $delta['content'] !== '') {
            return StandardStreamEvent::delta(
                id: $id,
                content: $delta['content']
            );
        }

        // 工具调用增量
        if (isset($delta['tool_calls'])) {
            return $this->parseToolCallDelta($id, $delta['tool_calls']);
        }

        return null;
    }

    /**
     * 解析工具调用增量
     */
    private function parseToolCallDelta(string $id, array $toolCalls): ?StandardStreamEvent
    {
        // 取第一个工具调用增量
        $toolCallDelta = $toolCalls[0] ?? null;
        if ($toolCallDelta === null) {
            return null;
        }

        $toolCall = new StandardToolCall(
            id: $toolCallDelta['id'] ?? '',
            type: $toolCallDelta['type'] ?? 'function',
            name: $toolCallDelta['function']['name'] ?? '',
            arguments: $toolCallDelta['function']['arguments'] ?? '',
            index: $toolCallDelta['index'] ?? 0,
        );

        return StandardStreamEvent::toolUse($id, $toolCall);
    }

    /**
     * 构建流式块数据
     */
    private function buildStreamChunkData(StandardStreamEvent $event): array
    {
        $chunk = [
            'id' => $event->id,
            'object' => 'chat.completion.chunk',
            'created' => $event->created ?: time(),
            'model' => $event->model ?? '',
            'choices' => [
                [
                    'index' => 0,
                    'delta' => [],
                    'finish_reason' => null,
                ],
            ],
        ];

        switch ($event->type) {
            case StandardStreamEvent::TYPE_START:
                $chunk['choices'][0]['delta']['role'] = $event->role ?? 'assistant';
                break;

            case StandardStreamEvent::TYPE_CONTENT_DELTA:
                $chunk['choices'][0]['delta']['content'] = $event->contentDelta;
                break;

            case StandardStreamEvent::TYPE_REASONING_DELTA:
                $chunk['choices'][0]['delta']['reasoning_content'] = $event->reasoningDelta;
                break;

            case StandardStreamEvent::TYPE_TOOL_USE:
                if ($event->toolCall !== null) {
                    $chunk['choices'][0]['delta']['tool_calls'] = [
                        [
                            'index' => $event->toolCall->index ?? 0,
                            'id' => $event->toolCall->id,
                            'type' => $event->toolCall->type,
                            'function' => [
                                'name' => $event->toolCall->name,
                                'arguments' => $event->toolCall->arguments,
                            ],
                        ],
                    ];
                }
                break;

            case StandardStreamEvent::TYPE_FINISH:
                $chunk['choices'][0]['finish_reason'] = $event->finishReason;
                break;

            case StandardStreamEvent::TYPE_ERROR:
                return [
                    'error' => [
                        'message' => $event->errorMessage,
                        'type' => $event->errorType ?? 'error',
                    ],
                ];
        }

        return $chunk;
    }
}
