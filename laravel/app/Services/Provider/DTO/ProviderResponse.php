<?php

namespace App\Services\Provider\DTO;

/**
 * 供应商响应数据传输对象
 *
 * 用于封装 AI 供应商返回的响应数据
 */
class ProviderResponse
{
    /**
     * 响应 ID
     */
    public string $id;

    /**
     * 模型名称
     */
    public string $model;

    /**
     * 生成的内容
     */
    public string $content = '';

    /**
     * 结束原因
     */
    public ?string $finishReason = null;

    /**
     * Token 使用量
     */
    public ?TokenUsage $usage = null;

    /**
     * 原始响应数据（用于调试）
     */
    public ?array $rawResponse = null;

    /**
     * 工具调用列表
     */
    public ?array $toolCalls = null;

    /**
     * 创建时间戳
     */
    public int $created = 0;

    /**
     * 推理内容（DeepSeek、Kimi 等思考模型的思考过程）
     */
    public ?string $reasoningContent = null;

    /**
     * 构造函数
     *
     * @param  string  $id  响应 ID
     * @param  string  $model  模型名称
     * @param  string  $content  生成内容
     * @param  string|null  $finishReason  结束原因
     * @param  TokenUsage|null  $usage  Token 使用量
     * @param  array|null  $rawResponse  原始响应
     * @param  array|null  $toolCalls  工具调用
     * @param  int  $created  创建时间戳
     * @param  string|null  $reasoningContent  推理内容
     */
    public function __construct(
        string $id,
        string $model,
        string $content = '',
        ?string $finishReason = null,
        ?TokenUsage $usage = null,
        ?array $rawResponse = null,
        ?array $toolCalls = null,
        int $created = 0,
        ?string $reasoningContent = null,
    ) {
        $this->id = $id;
        $this->model = $model;
        $this->content = $content;
        $this->finishReason = $finishReason;
        $this->usage = $usage;
        $this->rawResponse = $rawResponse;
        $this->toolCalls = $toolCalls;
        $this->created = $created ?: time();
        $this->reasoningContent = $reasoningContent;
    }

    /**
     * 从 OpenAI 格式创建实例
     *
     * @param  array  $response  OpenAI 响应数据
     */
    public static function fromOpenAI(array $response): self
    {
        $id = $response['id'] ?? '';
        $model = $response['model'] ?? '';
        $created = $response['created'] ?? time();

        $choices = $response['choices'] ?? [];
        $choice = $choices[0] ?? [];

        $message = $choice['message'] ?? [];
        $content = $message['content'] ?? '';
        $finishReason = $choice['finish_reason'] ?? null;

        // 兼容非标准格式：某些供应商直接返回 content 字段
        if (empty($content) && isset($response['content'])) {
            $content = $response['content'];
        }

        // 兼容非标准格式：某些供应商直接返回 finish_reason 字段
        if ($finishReason === null && isset($response['finish_reason'])) {
            $finishReason = $response['finish_reason'];
        }

        // 提取推理内容（DeepSeek、Kimi 等思考模型）
        $reasoningContent = $message['reasoning_content'] ?? null;
        // 兼容非标准格式：直接在顶层返回 reasoning_content
        if ($reasoningContent === null && isset($response['reasoning_content'])) {
            $reasoningContent = $response['reasoning_content'];
        }

        // 处理工具调用
        $toolCalls = null;
        if (isset($message['tool_calls'])) {
            $toolCalls = $message['tool_calls'];
        }
        // 兼容非标准格式：直接在顶层返回 tool_calls
        if ($toolCalls === null && isset($response['tool_calls'])) {
            $toolCalls = $response['tool_calls'];
        }

        // 处理 Token 使用量
        $usage = null;
        if (isset($response['usage'])) {
            $usage = TokenUsage::fromOpenAI($response['usage']);
        }

        return new self(
            id: $id,
            model: $model,
            content: $content ?? '',
            finishReason: $finishReason,
            usage: $usage,
            rawResponse: $response,
            toolCalls: $toolCalls,
            created: $created,
            reasoningContent: $reasoningContent,
        );
    }

    /**
     * 从 Anthropic 格式创建实例
     *
     * @param  array  $response  Anthropic 响应数据
     */
    public static function fromAnthropic(array $response): self
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
                    $toolCalls[] = [
                        'id' => $block['id'] ?? '',
                        'type' => 'function',
                        'function' => [
                            'name' => $block['name'] ?? '',
                            'arguments' => json_encode($block['input'] ?? []),
                        ],
                    ];
                }
            }
        }

        // 处理 Token 使用量
        $usage = null;
        if (isset($response['usage'])) {
            $usage = TokenUsage::fromAnthropic($response['usage']);
        }

        return new self(
            id: $id,
            model: $model,
            content: $content,
            finishReason: self::mapStopReason($stopReason),
            usage: $usage,
            rawResponse: $response,
            toolCalls: $toolCalls,
        );
    }

    /**
     * 转换为 OpenAI 格式
     */
    public function toOpenAI(): array
    {
        $response = [
            'id' => $this->id,
            'object' => 'chat.completion',
            'created' => $this->created,
            'model' => $this->model,
            'choices' => [
                [
                    'index' => 0,
                    'message' => $this->buildMessage(),
                    'finish_reason' => $this->finishReason,
                ],
            ],
        ];

        if ($this->usage !== null) {
            $response['usage'] = $this->usage->toOpenAI();
        }

        return $response;
    }

    /**
     * 转换为 Anthropic 格式
     */
    public function toAnthropic(): array
    {
        $response = [
            'id' => $this->id,
            'type' => 'message',
            'role' => 'assistant',
            'model' => $this->model,
            'content' => $this->buildContentBlocks(),
            'stop_reason' => $this->mapFinishReason($this->finishReason),
            'stop_sequence' => null,
        ];

        if ($this->usage !== null) {
            $response['usage'] = $this->usage->toAnthropic();
        }

        return $response;
    }

    /**
     * 是否包含工具调用
     */
    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== null && count($this->toolCalls) > 0;
    }

    /**
     * 转换为数组格式
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'model' => $this->model,
            'content' => $this->content,
            'finish_reason' => $this->finishReason,
            'usage' => $this->usage?->toArray(),
            'tool_calls' => $this->toolCalls,
            'created' => $this->created,
        ];
    }

    /**
     * 构建 OpenAI 消息对象
     */
    private function buildMessage(): array
    {
        $message = [
            'role' => 'assistant',
            'content' => $this->content ?: null,
        ];

        // 添加推理内容（DeepSeek、Kimi 等思考模型）
        if ($this->reasoningContent !== null) {
            $message['reasoning_content'] = $this->reasoningContent;
        }

        if ($this->toolCalls !== null) {
            $message['tool_calls'] = $this->toolCalls;
            if (empty($this->content)) {
                $message['content'] = null;
            }
        }

        return $message;
    }

    /**
     * 构建 Anthropic 内容块
     */
    private function buildContentBlocks(): array
    {
        $blocks = [];

        if ($this->content) {
            $blocks[] = [
                'type' => 'text',
                'text' => $this->content,
            ];
        }

        if ($this->toolCalls !== null) {
            foreach ($this->toolCalls as $tc) {
                $blocks[] = [
                    'type' => 'tool_use',
                    'id' => $tc['id'] ?? '',
                    'name' => $tc['function']['name'] ?? '',
                    'input' => json_decode($tc['function']['arguments'] ?? '{}', true),
                ];
            }
        }

        return $blocks;
    }

    /**
     * 映射 Anthropic stop_reason 到标准格式
     */
    private static function mapStopReason(?string $stopReason): ?string
    {
        return match ($stopReason) {
            'end_turn' => 'stop',
            'max_tokens' => 'length',
            'stop_sequence' => 'stop',
            'tool_use' => 'tool_calls',
            null => null,
            default => $stopReason,
        };
    }

    /**
     * 映射标准 finish_reason 到 Anthropic 格式
     */
    private function mapFinishReason(?string $finishReason): ?string
    {
        return match ($finishReason) {
            'stop' => 'end_turn',
            'length' => 'max_tokens',
            'tool_calls' => 'tool_use',
            'content_filter' => 'end_turn',
            null => null,
            default => $finishReason,
        };
    }
}
