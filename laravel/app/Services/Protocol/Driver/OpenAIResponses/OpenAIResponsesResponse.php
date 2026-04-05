<?php

namespace App\Services\Protocol\Driver\OpenAIResponses;

use App\Services\Protocol\Contracts\ProtocolResponse;
use App\Services\Protocol\Driver\Concerns\Convertible;
use App\Services\Protocol\Driver\Concerns\JsonSerializiable;
use App\Services\Protocol\Driver\Concerns\ProtocolResponseTrait;
use App\Services\Protocol\Driver\Concerns\Validatable;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionResponse;
use App\Services\Response\ResponseStateManager;
use App\Services\Shared\DTO\Response as SharedResponse;
use App\Services\Shared\DTO\StreamChunk;
use App\Services\Shared\Enums\FinishReason;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI Responses API 响应 DTO
 *
 * @see https://platform.openai.com/docs/api-reference/responses/object
 */
class OpenAIResponsesResponse implements ProtocolResponse
{
    use Convertible;
    use JsonSerializiable;
    use ProtocolResponseTrait;
    use Validatable;

    /**
     * @param  string  $id  响应ID
     * @param  string  $object  对象类型
     * @param  int  $created  创建时间戳
     * @param  string  $model  模型名称
     * @param  string|array  $output  输出内容
     * @param  array|null  $toolCalls  工具调用
     * @param  string|null  $stopReason  停止原因
     * @param  array|null  $usage  Token使用量
     * @param  string|null  $systemFingerprint  系统指纹
     */
    public function __construct(
        public string $id = '',
        public string $object = 'response',
        public int $created = 0,
        public string $model = '',
        public string|array $output = '',
        public ?array $toolCalls = null,
        public ?string $stopReason = null,
        public ?array $usage = null,
        public ?string $systemFingerprint = null,
    ) {}

    /**
     * 从数组创建实例
     */
    public static function fromArray(array $data): static
    {
        $response = new self;
        $response->id = $data['id'] ?? '';
        $response->object = $data['object'] ?? 'response';
        $response->created = $data['created'] ?? time();
        $response->model = $data['model'] ?? '';
        $response->output = $data['output'] ?? '';
        $response->toolCalls = $data['tool_calls'] ?? null;
        $response->stopReason = $data['stop_reason'] ?? null;
        $response->usage = $data['usage'] ?? null;
        $response->systemFingerprint = $data['system_fingerprint'] ?? null;

        return $response;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
            'object' => $this->object,
            'created' => $this->created,
            'created_at' => $this->created,
            'model' => $this->model,
            'output' => $this->output,
            'status' => 'completed',
            'tool_choice' => 'auto',
            'tools' => [],
            'parallel_tool_calls' => false,
            'store' => true,
            'metadata' => [],
        ];

        if ($this->toolCalls !== null) {
            $result['tool_calls'] = $this->toolCalls;
        }

        if ($this->stopReason !== null) {
            $result['stop_reason'] = $this->stopReason;
        }

        if ($this->usage !== null) {
            $result['usage'] = $this->usage;
        }

        if ($this->systemFingerprint !== null) {
            $result['system_fingerprint'] = $this->systemFingerprint;
        }

        return $result;
    }

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'id' => 'required|string',
            'object' => 'required|string',
            'created' => 'required|integer',
            'model' => 'required|string',
            'output' => 'required',
            'tool_calls' => 'nullable|array',
            'stop_reason' => 'nullable|string',
            'usage' => 'nullable|array',
            'system_fingerprint' => 'nullable|string',
        ];
    }

    /**
     * 从 Chat Completions 响应转换
     */
    public static function fromChatCompletions(ChatCompletionResponse $chat): self
    {
        $response = new self;
        $response->id = $chat->id ?? '';
        $response->model = $chat->model;
        $response->created = $chat->created;
        $response->object = 'response';
        $response->systemFingerprint = $chat->system_fingerprint ?? null;

        // choices[0].message → output 数组格式
        if (! empty($chat->choices)) {
            $choice = $chat->choices[0];
            $message = $choice->message ?? [];

            // 提取内容和工具调用
            $content = '';
            $toolCalls = null;

            if (is_object($message)) {
                $content = $message->content ?? '';
                // 工具调用
                if (isset($message->tool_calls)) {
                    $toolCalls = $message->tool_calls;
                }
            } else {
                $content = $message['content'] ?? '';
                // 工具调用
                if (isset($message['tool_calls'])) {
                    $toolCalls = $message['tool_calls'];
                }
            }

            // 停止原因映射
            $finishReason = $choice->finish_reason ?? null;
            if ($finishReason !== null) {
                $response->stopReason = self::mapFinishReason($finishReason);
            }

            // 构建 output 数组格式（OpenAI SDK 期望的格式）
            // 如果有工具调用，优先输出 function_call 格式
            if ($toolCalls !== null && ! empty($toolCalls)) {
                $response->toolCalls = $toolCalls;

                // Responses API 期望：每个 tool_call 作为独立的 output 项
                $response->output = [];
                foreach ($toolCalls as $toolCall) {
                    $callId = is_object($toolCall) ? ($toolCall->id ?? '') : ($toolCall['id'] ?? '');
                    $function = is_object($toolCall) ? ($toolCall->function ?? null) : ($toolCall['function'] ?? null);

                    $funcName = '';
                    $funcArgs = '';
                    if ($function !== null) {
                        $funcName = is_object($function) ? ($function->name ?? '') : ($function['name'] ?? '');
                        $funcArgs = is_object($function) ? ($function->arguments ?? '') : ($function['arguments'] ?? '');
                    }

                    $response->output[] = [
                        'type' => 'function_call',
                        'id' => $callId,
                        'call_id' => $callId,  // 必须字段：客户端用于识别和执行工具调用
                        'status' => 'completed',
                        'name' => $funcName,
                        'arguments' => $funcArgs,
                    ];
                }
            } elseif ($content !== '') {
                // 普通文本消息
                $response->output = [
                    [
                        'type' => 'message',
                        'id' => $chat->id.'_msg',
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => $content,
                            ],
                        ],
                    ],
                ];
            } else {
                $response->output = [];
            }
        } else {
            $response->output = [];
        }

        // usage 转换（修正字段名）
        if ($chat->usage !== null) {
            $response->usage = [
                'input_tokens' => $chat->usage->prompt_tokens ?? 0,
                'output_tokens' => $chat->usage->completion_tokens ?? 0,
                // 注意：Responses API 没有 total_tokens，需要前端自行计算
            ];
        }

        return $response;
    }

    /**
     * 映射 finish_reason → stop_reason
     */
    private static function mapFinishReason(string $finishReason): string
    {
        return match ($finishReason) {
            'stop' => 'end_turn',
            'length' => 'max_tokens',
            'tool_calls' => 'tool_use',
            'content_filter' => 'content_filter',
            default => $finishReason,
        };
    }

    /**
     * 转换为消息数组（用于存储到会话）
     */
    public function toMessageArray(): array
    {
        $message = [
            'role' => 'assistant',
            'content' => is_string($this->output) ? $this->output : '',
        ];

        if ($this->toolCalls !== null) {
            $message['tool_calls'] = $this->toolCalls;
        }

        return $message;
    }

    /**
     * 获取总 Token 数
     */
    public function getTotalTokens(): int
    {
        if ($this->usage === null) {
            return 0;
        }

        return ($this->usage['input_tokens'] ?? 0) + ($this->usage['output_tokens'] ?? 0);
    }

    /**
     * 转换为 Shared\DTO
     */
    public function toSharedDTO(): SharedResponse
    {
        $dto = new SharedResponse;
        $dto->id = $this->id;
        $dto->model = $this->model;
        $dto->content = is_string($this->output) ? $this->output : json_encode($this->output);

        // 将 stop_reason 字符串转换为 FinishReason 枚举
        if ($this->stopReason !== null) {
            $dto->finishReason = FinishReason::tryFrom($this->stopReason);
        }

        return $dto;
    }

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(object $dto): static
    {
        $response = new self;
        $response->id = $dto->id ?? '';
        $response->model = $dto->model ?? '';
        $response->stopReason = $dto->finishReason?->value ?? null;
        $response->created = time();

        // 将内容转换为数组格式的 output
        $content = '';
        if (isset($dto->choices) && ! empty($dto->choices)) {
            $choice = $dto->choices[0];
            $message = $choice['message'] ?? null;

            // message 可能是 Message DTO 对象或数组
            if ($message instanceof \App\Services\Shared\DTO\Message) {
                $content = $message->getTextContent();
            } elseif (is_array($message)) {
                $content = $message['content'] ?? '';
            } elseif (is_string($message)) {
                $content = $message;
            }
        } elseif (isset($dto->content)) {
            $content = $dto->content;
        }

        if ($content !== '') {
            $response->output = [
                [
                    'type' => 'message',
                    'id' => ($dto->id ?? '').'_msg',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => $content,
                            'annotations' => [],
                        ],
                    ],
                ],
            ];
        } else {
            $response->output = [];
        }

        // 转换 usage
        if ($dto->usage !== null) {
            $response->usage = [
                'input_tokens' => $dto->usage->inputTokens ?? 0,
                'output_tokens' => $dto->usage->outputTokens ?? 0,
                'total_tokens' => ($dto->usage->inputTokens ?? 0) + ($dto->usage->outputTokens ?? 0),
                'input_tokens_details' => [
                    'cached_tokens' => 0,
                ],
                'output_tokens_details' => [
                    'reasoning_tokens' => 0,
                ],
            ];
        }

        // 非流式响应：状态存储
        if ($dto->protocolContext instanceof ResponsesContext) {
            $response->storeState($dto->protocolContext, $content, $dto->usage);
        }

        return $response;
    }

    /**
     * 流式后处理：存储状态
     *
     * 覆盖 Trait 的默认空实现
     *
     * @param  array  $chunks  累积的流式块
     * @param  object|null  $context  协议上下文（从请求传递）
     */
    public function postStreamProcess(array $chunks, ?object $context = null): void
    {
        if (! $context instanceof ResponsesContext) {
            return;
        }

        // 从 chunks 提取完整内容
        $content = '';
        $usage = null;
        foreach ($chunks as $chunk) {
            if ($chunk instanceof StreamChunk) {
                $content .= $chunk->contentDelta ?? $chunk->delta ?? '';
                if ($chunk->usage !== null) {
                    $usage = $chunk->usage;
                }
            }
        }

        // 设置响应 ID（从第一个 chunk）
        foreach ($chunks as $chunk) {
            if ($chunk instanceof StreamChunk && ! empty($chunk->id)) {
                $this->id = $chunk->id;
                break;
            }
        }

        // 设置模型（从第一个 chunk）
        foreach ($chunks as $chunk) {
            if ($chunk instanceof StreamChunk && ! empty($chunk->model)) {
                $this->model = $chunk->model;
                break;
            }
        }

        // 存储状态
        $this->storeState($context, $content, $usage);
    }

    /**
     * 存储状态（共用逻辑）
     *
     * @param  ResponsesContext  $context  协议上下文
     * @param  string  $content  响应内容
     * @param  object|null  $usage  Token 使用量
     */
    private function storeState(ResponsesContext $context, string $content, ?object $usage): void
    {
        if (empty($context->fullMessages)) {
            return;
        }

        try {
            // 构建完整消息历史（请求历史 + 助手回复）
            // 将 Message 对象转换为简单数组格式（用于状态存储）
            $completeMessages = [];
            foreach ($context->fullMessages as $msg) {
                if ($msg instanceof \App\Services\Shared\DTO\Message) {
                    $completeMessages[] = [
                        'role' => $msg->role->value ?? $msg->role,
                        'content' => $msg->getTextContent(),
                    ];
                } elseif (is_array($msg)) {
                    $completeMessages[] = [
                        'role' => $msg['role'] ?? 'user',
                        'content' => $msg['content'] ?? '',
                    ];
                }
            }

            // 添加助手回复
            $completeMessages[] = [
                'role' => 'assistant',
                'content' => $content,
            ];

            // 计算总 Token
            $totalTokens = 0;
            if ($usage !== null) {
                $totalTokens = method_exists($usage, 'getTotalTokens')
                    ? $usage->getTotalTokens()
                    : ($usage->inputTokens ?? 0) + ($usage->outputTokens ?? 0);
            }

            // 存储状态
            app(ResponseStateManager::class)->store(
                responseId: $this->id,
                messages: $completeMessages,
                apiKeyId: $context->apiKeyId,
                model: $this->model,
                totalTokens: $totalTokens,
                previousResponseId: $context->previousResponseId,
            );
        } catch (\Exception $e) {
            Log::error('Failed to store response session', [
                'response_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            // 继续返回响应，不影响用户体验
        }
    }

    /**
     * 获取响应ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * 获取模型名称
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * 获取用量信息
     */
    public function getUsage(): ?object
    {
        if ($this->usage === null) {
            return null;
        }

        return (object) $this->usage;
    }

    /**
     * 过滤思考内容
     */
    public function filterThinking(bool $filter = true): static
    {
        // Responses API 暂不支持思考内容过滤，直接返回自身
        return $this;
    }
}
