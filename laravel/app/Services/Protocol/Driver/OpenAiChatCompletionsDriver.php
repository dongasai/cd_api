<?php

namespace App\Services\Protocol\Driver;

use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\Contracts\ProtocolResponse;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionRequest;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionResponse;
use App\Services\Shared\DTO\StreamChunk;

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
     * 解析原始请求为协议请求结构体
     *
     * 直接返回 ChatCompletionRequest，不再转换为 Shared\DTO
     */
    public function parseRequest(array $rawRequest): ProtocolRequest
    {
        return ChatCompletionRequest::fromArrayValidated($rawRequest);
    }

    /**
     * 从协议响应结构体构建 OpenAI 响应数组
     *
     * 直接使用协议响应结构体，不再从 Shared\DTO 转换
     */
    public function buildResponse(ProtocolResponse $response): array
    {
        // 如果是同协议响应，直接转数组
        if ($response instanceof ChatCompletionResponse) {
            return $response->toArray();
        }

        // 需要转换：通过 Shared\DTO 中间层
        $sharedDTO = $response->toSharedDTO();

        return ChatCompletionResponse::fromSharedDTO($sharedDTO)->toArray();
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
                    'finish_reason' => $chunk->finishReason?->value,
                ],
            ],
        ];

        // 添加 usage
        if ($chunk->usage !== null) {
            $result['usage'] = [
                'prompt_tokens' => $chunk->usage->inputTokens,
                'completion_tokens' => $chunk->usage->outputTokens,
                'total_tokens' => $chunk->usage->getTotalTokens(),
            ];
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
