<?php

namespace App\Services\Protocol\Driver;

use App\Services\Shared\DTO\Message;
use App\Services\Shared\DTO\Request;
use App\Services\Shared\DTO\Response;
use App\Services\Shared\DTO\StreamChunk;
use App\Services\Shared\Enums\MessageRole;

/**
 * Anthropic Messages 协议驱动
 */
class AnthropicMessagesDriver extends AbstractDriver
{
    /**
     * 协议名称
     */
    public const PROTOCOL_NAME = 'anthropic_messages';

    /**
     * 流式事件类型
     */
    public const EVENT_MESSAGE_START = 'message_start';

    public const EVENT_CONTENT_BLOCK_START = 'content_block_start';

    public const EVENT_CONTENT_BLOCK_DELTA = 'content_block_delta';

    public const EVENT_CONTENT_BLOCK_STOP = 'content_block_stop';

    public const EVENT_MESSAGE_DELTA = 'message_delta';

    public const EVENT_MESSAGE_STOP = 'message_stop';

    public const EVENT_ERROR = 'error';

    public const EVENT_PING = 'ping';

    /**
     * 当前内容块索引
     */
    private int $currentBlockIndex = 0;

    /**
     * 当前内容块类型（text/thinking）
     */
    private string $currentBlockType = 'text';

    /**
     * 是否已发送内容块开始事件
     */
    private bool $contentBlockStarted = false;

    /**
     * 当前工具调用索引（流式处理）
     */
    private ?int $currentToolCallIndex = null;

    /**
     * 是否已发送当前工具调用的开始事件
     */
    private bool $toolCallBlockStarted = false;

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
    public function parseRequest(array $rawRequest): Request
    {
        $messages = [];
        foreach ($rawRequest['messages'] ?? [] as $msg) {
            // 处理 content 字段：区分字符串和数组格式
            $content = null;
            $contentBlocks = null;
            if (isset($msg['content'])) {
                if (is_array($msg['content'])) {
                    // 将 Anthropic 格式的 content blocks 转换为 ContentBlock 对象
                    $contentBlocks = array_map(
                        fn ($block) => \App\Services\Shared\DTO\ContentBlock::fromAnthropic($block),
                        $msg['content']
                    );
                } else {
                    $content = $msg['content'];
                }
            }

            $messages[] = new Message(
                role: MessageRole::from($msg['role'] ?? 'user'),
                content: $content,
                toolCalls: $msg['tool_calls'] ?? null,
                toolCallId: $msg['tool_call_id'] ?? null,
                contentBlocks: $contentBlocks,
            );
        }

        return new Request(
            model: $rawRequest['model'] ?? '',
            messages: $messages,
            maxTokens: $rawRequest['max_tokens'] ?? null,
            temperature: $rawRequest['temperature'] ?? null,
            topP: $rawRequest['top_p'] ?? null,
            topK: $rawRequest['top_k'] ?? null,
            stream: $rawRequest['stream'] ?? false,
            stopSequences: $rawRequest['stop_sequences'] ?? null,
            system: $rawRequest['system'] ?? null,
            tools: $rawRequest['tools'] ?? null,
            toolChoice: $rawRequest['tool_choice'] ?? null,
            thinking: $rawRequest['thinking'] ?? null,
            metadata: $rawRequest['metadata'] ?? null,
            rawRequest: $rawRequest,
        );
    }

    /**
     * 从标准格式构建 Anthropic 响应
     */
    public function buildResponse(Response $response): array
    {
        return $response->toAnthropic();
    }

    /**
     * 从标准格式构建 Anthropic 流式块
     */
    public function buildStreamChunk(StreamChunk $chunk): string
    {
        // 根据事件类型选择构建方法
        if ($chunk->event === self::EVENT_MESSAGE_START) {
            return $this->buildMessageStartEvent($chunk);
        }

        if ($chunk->event === self::EVENT_PING) {
            // 使用原始数据，如果没有则使用默认格式
            $data = ! empty($chunk->data) ? json_encode($chunk->data, JSON_UNESCAPED_UNICODE) : '{}';

            return $this->buildSSEEvent(self::EVENT_PING, $data);
        }

        // 内容块开始事件 - 标记状态并透传
        if ($chunk->event === self::EVENT_CONTENT_BLOCK_START) {
            $this->contentBlockStarted = true;
            $this->currentBlockIndex = $chunk->index ?? $this->currentBlockIndex;

            // 从原始数据中提取块类型
            if (! empty($chunk->data['content_block']['type'])) {
                $this->currentBlockType = $chunk->data['content_block']['type'];
            }

            $data = ! empty($chunk->data) ? json_encode($chunk->data, JSON_UNESCAPED_UNICODE) : '{}';

            return $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_START, $data);
        }

        // 内容块结束事件 - 重置状态并透传
        if ($chunk->event === self::EVENT_CONTENT_BLOCK_STOP) {
            $this->contentBlockStarted = false;
            $this->currentBlockIndex++;

            $data = ! empty($chunk->data) ? json_encode($chunk->data, JSON_UNESCAPED_UNICODE) : '{}';

            return $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_STOP, $data);
        }

        // 内容块增量事件 - 直接透传
        if ($chunk->event === self::EVENT_CONTENT_BLOCK_DELTA) {
            $data = ! empty($chunk->data) ? json_encode($chunk->data, JSON_UNESCAPED_UNICODE) : '{}';

            return $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_DELTA, $data);
        }

        // 消息增量事件 - 直接透传
        if ($chunk->event === self::EVENT_MESSAGE_DELTA) {
            $data = ! empty($chunk->data) ? json_encode($chunk->data, JSON_UNESCAPED_UNICODE) : '{}';

            return $this->buildSSEEvent(self::EVENT_MESSAGE_DELTA, $data);
        }

        // 消息停止事件 - 直接透传
        if ($chunk->event === self::EVENT_MESSAGE_STOP) {
            $data = ! empty($chunk->data) ? json_encode($chunk->data, JSON_UNESCAPED_UNICODE) : '{}';

            return $this->buildSSEEvent(self::EVENT_MESSAGE_STOP, $data);
        }

        // 内容增量（来自标准转换）
        if ($chunk->delta !== '' || $chunk->contentDelta !== null) {
            return $this->buildContentBlockDeltaEvent($chunk);
        }

        // 推理内容增量（来自标准转换）
        if ($chunk->reasoningDelta !== null) {
            return $this->buildContentBlockDeltaEvent($chunk);
        }

        // 工具调用
        if ($chunk->toolCalls !== null || $chunk->toolCall !== null) {
            return $this->buildToolUseEvent($chunk);
        }

        // 结束（用于协议转换场景，如 OpenAI 转 Anthropic）
        if ($chunk->finishReason !== null) {
            return $this->buildMessageStopEvent($chunk);
        }

        return '';
    }

    /**
     * 构建流式结束标记
     */
    public function buildStreamDone(): string
    {
        return $this->buildSSEEvent(self::EVENT_MESSAGE_STOP, '{}');
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
            'type' => 'error',
            'error' => [
                'type' => $type,
                'message' => $message,
            ],
        ];
    }

    // ==================== 流式事件构建 ====================

    /**
     * 构建消息开始事件
     */
    private function buildMessageStartEvent(StreamChunk $chunk): string
    {
        $data = [
            'type' => self::EVENT_MESSAGE_START,
            'message' => [
                'id' => $chunk->id,
                'type' => 'message',
                'role' => 'assistant',
                'model' => $chunk->model,
                'content' => [],
                'stop_reason' => null,
                'stop_sequence' => null,
                'usage' => [
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                ],
            ],
        ];

        return $this->buildSSEEvent(self::EVENT_MESSAGE_START, $this->safeJsonEncode($data));
    }

    /**
     * 构建内容块增量事件
     */
    private function buildContentBlockDeltaEvent(StreamChunk $chunk): string
    {
        $output = '';

        // 判断是推理内容还是普通文本内容
        $isReasoning = $chunk->reasoningDelta !== null;
        $blockType = $isReasoning ? 'thinking' : 'text';
        $deltaType = $isReasoning ? 'thinking_delta' : 'text_delta';
        $content = $isReasoning ? $chunk->reasoningDelta : ($chunk->delta !== '' ? $chunk->delta : $chunk->contentDelta);

        // 如果内容为空，不发送任何事件
        if (empty($content)) {
            return '';
        }

        // 如果块类型发生变化，需要关闭旧块并开始新块
        if ($this->contentBlockStarted && $this->currentBlockType !== $blockType) {
            // 关闭旧块
            $blockStop = [
                'type' => self::EVENT_CONTENT_BLOCK_STOP,
                'index' => $this->currentBlockIndex,
            ];
            $output .= $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_STOP, $this->safeJsonEncode($blockStop));
            $this->contentBlockStarted = false;
            $this->currentBlockIndex++;
        }

        // 只在第一次发送内容块开始事件
        if (! $this->contentBlockStarted) {
            $blockStart = [
                'type' => self::EVENT_CONTENT_BLOCK_START,
                'index' => $this->currentBlockIndex,
                'content_block' => [
                    'type' => $blockType,
                    $blockType === 'thinking' ? 'thinking' : 'text' => '',
                ],
            ];
            $output .= $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_START, $this->safeJsonEncode($blockStart));
            $this->contentBlockStarted = true;
            $this->currentBlockType = $blockType;
        }

        // 内容增量
        $delta = [
            'type' => self::EVENT_CONTENT_BLOCK_DELTA,
            'index' => $this->currentBlockIndex,
            'delta' => [
                'type' => $deltaType,
                $isReasoning ? 'thinking' : 'text' => $content,
            ],
        ];
        $output .= $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_DELTA, $this->safeJsonEncode($delta));

        return $output;
    }

    /**
     * 构建工具使用事件
     */
    private function buildToolUseEvent(StreamChunk $chunk): string
    {
        $toolCall = $chunk->toolCall;
        $toolCalls = $chunk->toolCalls;

        // 处理兼容字段
        if ($toolCalls !== null && ! empty($toolCalls)) {
            $tc = $toolCalls[0];
            $toolCall = new \App\Services\Shared\DTO\ToolCall(
                id: $tc['id'] ?? '',
                type: \App\Services\Shared\Enums\ToolType::from($tc['type'] ?? 'function'),
                name: $tc['function']['name'] ?? '',
                arguments: $tc['function']['arguments'] ?? '',
                index: $tc['index'] ?? 0,
            );
        }

        if ($toolCall === null) {
            return '';
        }

        $output = '';
        $toolIndex = $toolCall->index ?? 0;

        // 判断是否需要发送开始事件
        $hasIdAndName = ! empty($toolCall->id) && ! empty($toolCall->name);
        $isFirstTime = $this->currentToolCallIndex !== $toolIndex;

        if ($hasIdAndName && $isFirstTime) {
            // 如果之前有内容块没关闭，先关闭
            if ($this->contentBlockStarted) {
                $blockStop = [
                    'type' => self::EVENT_CONTENT_BLOCK_STOP,
                    'index' => $this->currentBlockIndex,
                ];
                $output .= $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_STOP, $this->safeJsonEncode($blockStop));
                $this->contentBlockStarted = false;
                $this->currentBlockIndex++;
            }

            // 发送工具调用开始事件
            $blockStart = [
                'type' => self::EVENT_CONTENT_BLOCK_START,
                'index' => $this->currentBlockIndex,
                'content_block' => [
                    'type' => 'tool_use',
                    'id' => $toolCall->id,
                    'name' => $toolCall->name,
                    'input' => [],
                ],
            ];
            $output .= $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_START, $this->safeJsonEncode($blockStart));

            // 标记状态
            $this->currentToolCallIndex = $toolIndex;
            $this->toolCallBlockStarted = true;
            $this->contentBlockStarted = false;
        }

        // 发送参数增量事件
        if (! empty($toolCall->arguments)) {
            $blockDelta = [
                'type' => self::EVENT_CONTENT_BLOCK_DELTA,
                'index' => $this->currentBlockIndex,
                'delta' => [
                    'type' => 'input_json_delta',
                    'partial_json' => $toolCall->arguments,
                ],
            ];
            $output .= $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_DELTA, $this->safeJsonEncode($blockDelta));
        }

        return $output;
    }

    /**
     * 构建消息停止事件
     */
    private function buildMessageStopEvent(StreamChunk $chunk): string
    {
        $output = '';

        // 发送内容块停止事件（如果有未关闭的内容块或工具调用块）
        if ($this->contentBlockStarted || $this->toolCallBlockStarted) {
            $blockStop = [
                'type' => self::EVENT_CONTENT_BLOCK_STOP,
                'index' => $this->currentBlockIndex,
            ];
            $output .= $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_STOP, $this->safeJsonEncode($blockStop));
            $this->contentBlockStarted = false;
            $this->toolCallBlockStarted = false;
        }

        // 消息增量
        $delta = [
            'type' => self::EVENT_MESSAGE_DELTA,
            'delta' => [
                'stop_reason' => $chunk->finishReason?->toAnthropic(),
            ],
            'usage' => $chunk->usage ? $chunk->usage->toAnthropic() : ['output_tokens' => 0],
        ];
        $output .= $this->buildSSEEvent(self::EVENT_MESSAGE_DELTA, $this->safeJsonEncode($delta));

        // 消息停止
        $output .= $this->buildSSEEvent(self::EVENT_MESSAGE_STOP, '{}');

        return $output;
    }
}
