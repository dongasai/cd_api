<?php

namespace App\Services\Protocol\DTO;

/**
 * 标准响应 DTO
 */
class StandardResponse
{
    public function __construct(
        // 响应ID
        public string $id,

        // 模型名称
        public string $model,

        // 生成的内容
        public string $content = '',

        // 内容块 (多模态)
        /** @var ContentBlock[]|null */
        public ?array $contentBlocks = null,

        // 工具调用
        /** @var StandardToolCall[]|null */
        public ?array $toolCalls = null,

        // 结束原因
        public ?string $finishReason = null,

        // Token 使用量
        public ?StandardUsage $usage = null,

        // 创建时间戳
        public int $created = 0,

        // 原始响应 (用于调试)
        public ?array $rawResponse = null,
    ) {
        if ($this->created === 0) {
            $this->created = time();
        }
    }

    /**
     * 从 OpenAI 格式创建
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
        if (empty($content) && isset($message['reasoning_content'])) {
            $content = $message['reasoning_content'];
        }
        $finishReason = $choice['finish_reason'] ?? null;

        // 处理工具调用
        $toolCalls = null;
        if (isset($message['tool_calls'])) {
            $toolCalls = array_map(
                fn ($tc, $index) => StandardToolCall::fromOpenAI($tc, $index),
                $message['tool_calls'],
                array_keys($message['tool_calls'])
            );
        }

        // 处理 usage
        $usage = null;
        if (isset($response['usage'])) {
            $usage = StandardUsage::fromOpenAI($response['usage']);
        }

        return new self(
            id: $id,
            model: $model,
            content: $content ?? '',
            toolCalls: $toolCalls,
            finishReason: $finishReason,
            usage: $usage,
            created: $created,
            rawResponse: $response,
        );
    }

    /**
     * 从 Anthropic 格式创建
     */
    public static function fromAnthropic(array $response): self
    {
        $id = $response['id'] ?? '';
        $model = $response['model'] ?? '';
        $stopReason = $response['stop_reason'] ?? null;

        // 处理内容
        $content = '';
        $contentBlocks = null;
        $toolCalls = null;

        if (isset($response['content']) && is_array($response['content'])) {
            $contentBlocks = array_map(
                fn ($block) => ContentBlock::fromAnthropic($block),
                $response['content']
            );

            // 提取文本
            foreach ($contentBlocks as $block) {
                if ($block->type === 'text') {
                    $content .= $block->text ?? '';
                }
            }

            // 提取工具调用
            $toolUseBlocks = array_filter(
                $contentBlocks,
                fn ($block) => $block->type === 'tool_use'
            );
            if (! empty($toolUseBlocks)) {
                $toolCalls = array_map(
                    fn ($block) => StandardToolCall::fromAnthropic($block->toArray()),
                    $toolUseBlocks
                );
            }
        }

        // 处理 usage
        $usage = null;
        if (isset($response['usage'])) {
            $usage = StandardUsage::fromAnthropic($response['usage']);
        }

        return new self(
            id: $id,
            model: $model,
            content: $content,
            contentBlocks: $contentBlocks,
            toolCalls: $toolCalls,
            finishReason: self::mapStopReason($stopReason),
            usage: $usage,
            rawResponse: $response,
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
     * 是否是流式响应的最终块
     */
    public function isFinalChunk(): bool
    {
        return $this->finishReason !== null;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'model' => $this->model,
            'content' => $this->content,
            'content_blocks' => $this->contentBlocks ?
                array_map(fn ($b) => $b->toArray(), $this->contentBlocks) : null,
            'tool_calls' => $this->toolCalls ?
                array_map(fn ($t) => $t->toArray(), $this->toolCalls) : null,
            'finish_reason' => $this->finishReason,
            'usage' => $this->usage?->toArray(),
            'created' => $this->created,
        ];
    }

    /**
     * 构建 OpenAI message 对象
     */
    private function buildMessage(): array
    {
        $message = [
            'role' => 'assistant',
            'content' => $this->content ?: null,
        ];

        if ($this->toolCalls !== null) {
            $message['tool_calls'] = array_map(
                fn ($tc) => $tc->toOpenAI(),
                $this->toolCalls
            );
            if (empty($this->content)) {
                $message['content'] = null;
            }
        }

        return $message;
    }

    /**
     * 构建 Anthropic content blocks
     */
    private function buildContentBlocks(): array
    {
        if ($this->contentBlocks !== null) {
            return array_map(fn ($b) => $b->toAnthropic(), $this->contentBlocks);
        }

        $blocks = [];

        if ($this->content) {
            $blocks[] = [
                'type' => 'text',
                'text' => $this->content,
            ];
        }

        if ($this->toolCalls !== null) {
            foreach ($this->toolCalls as $tc) {
                $blocks[] = $tc->toAnthropic();
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
