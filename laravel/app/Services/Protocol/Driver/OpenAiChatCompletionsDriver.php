<?php

namespace App\Services\Protocol\Driver;

use App\Services\Shared\DTO\Message;
use App\Services\Shared\DTO\Request;
use App\Services\Shared\DTO\Response;
use App\Services\Shared\DTO\StreamChunk;
use App\Services\Shared\Enums\MessageRole;

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
            // 处理 content 字段：可能是字符串或数组（多模态）
            $content = null;
            $contentBlocks = null;

            if (isset($msg['content'])) {
                if (is_array($msg['content'])) {
                    // 多模态内容：转换为 ContentBlock 数组
                    $contentBlocks = [];
                    foreach ($msg['content'] as $block) {
                        if (is_array($block)) {
                            $contentBlocks[] = \App\Services\Shared\DTO\ContentBlock::fromOpenAI($block);
                        }
                    }
                } else {
                    // 纯文本内容
                    $content = (string) $msg['content'];
                }
            }

            $messages[] = new Message(
                role: MessageRole::from($msg['role'] ?? 'user'),
                content: $content,
                contentBlocks: $contentBlocks,
                toolCalls: $msg['tool_calls'] ?? null,
                toolCallId: $msg['tool_call_id'] ?? null,
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
            stopSequences: $rawRequest['stop'] ?? null,
            system: $rawRequest['system'] ?? null,
            tools: $rawRequest['tools'] ?? null,
            toolChoice: $rawRequest['tool_choice'] ?? null,
            metadata: $rawRequest['metadata'] ?? null,
            user: $rawRequest['user'] ?? null,
            rawRequest: $rawRequest,
        );
    }

    /**
     * 从标准格式构建 OpenAI 响应
     */
    public function buildResponse(Response $response): array
    {
        return $response->toOpenAI();
    }

    /**
     * 从标准格式构建 OpenAI 流式块
     */
    public function buildStreamChunk(StreamChunk $chunk): string
    {
        $delta = [];

        // 兼容旧字段
        if ($chunk->delta !== '') {
            $delta['content'] = $chunk->delta;
        } elseif ($chunk->contentDelta !== null) {
            $delta['content'] = $chunk->contentDelta;
        }

        // 推理内容增量（DeepSeek、Kimi 等思考模型）
        if ($chunk->reasoningDelta !== null) {
            $delta['reasoning_content'] = $chunk->reasoningDelta;
        }

        $result = [
            'id' => $chunk->id ?: 'chatcmpl-'.uniqid(),
            'object' => 'chat.completion.chunk',
            'created' => time(),
            'model' => $chunk->model,
            'choices' => [
                [
                    'index' => $chunk->index ?? 0,
                    'delta' => $delta,
                    'finish_reason' => $chunk->finishReason?->toOpenAI(),
                ],
            ],
        ];

        // 添加 usage
        if ($chunk->usage !== null) {
            $result['usage'] = $chunk->usage->toOpenAI();
        }

        // 处理工具调用
        if ($chunk->toolCalls !== null) {
            $result['choices'][0]['delta']['tool_calls'] = $chunk->toolCalls;
        } elseif ($chunk->toolCall !== null) {
            $result['choices'][0]['delta']['tool_calls'] = [
                [
                    'index' => $chunk->toolCall->index ?? 0,
                    'id' => $chunk->toolCall->id,
                    'type' => $chunk->toolCall->type->value,
                    'function' => [
                        'name' => $chunk->toolCall->name,
                        'arguments' => $chunk->toolCall->arguments,
                    ],
                ],
            ];
        }

        // 处理错误
        if ($chunk->error !== null) {
            return $this->safeJsonEncode([
                'error' => [
                    'message' => $chunk->error,
                    'type' => $chunk->errorType?->value ?? 'error',
                ],
            ]);
        }

        return 'data: '.$this->safeJsonEncode($result)."\n\n";
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
}
