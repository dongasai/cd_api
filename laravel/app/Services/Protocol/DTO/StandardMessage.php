<?php

namespace App\Services\Protocol\DTO;

/**
 * 标准消息 DTO
 */
class StandardMessage
{
    public function __construct(
        // 角色: system, user, assistant, tool
        public string $role,

        // 文本内容
        public string $content = '',

        // 多模态内容块
        /** @var ContentBlock[]|null */
        public ?array $contentBlocks = null,

        // 工具调用 (assistant 消息)
        /** @var StandardToolCall[]|null */
        public ?array $toolCalls = null,

        // 工具调用结果 (tool 消息)
        public ?string $toolCallId = null,

        // 名称 (可选)
        public ?string $name = null,
    ) {}

    /**
     * 从 OpenAI 格式创建
     */
    public static function fromOpenAI(array $message): self
    {
        $role = $message['role'];
        $contentBlocks = null;
        $content = '';
        $toolCalls = null;

        // 处理内容
        if (is_array($message['content'] ?? null)) {
            // 多模态内容
            $contentBlocks = array_map(
                fn ($block) => ContentBlock::fromOpenAI($block),
                $message['content']
            );
            $content = self::extractTextFromBlocks($contentBlocks);
        } else {
            $content = $message['content'] ?? '';
        }

        // 处理工具调用
        if (isset($message['tool_calls'])) {
            $toolCalls = array_map(
                fn ($tc, $index) => StandardToolCall::fromOpenAI($tc, $index),
                $message['tool_calls'],
                array_keys($message['tool_calls'])
            );
        }

        return new self(
            role: $role,
            content: $content,
            contentBlocks: $contentBlocks,
            toolCalls: $toolCalls,
            toolCallId: $message['tool_call_id'] ?? null,
            name: $message['name'] ?? null,
        );
    }

    /**
     * 从 Anthropic 格式创建
     */
    public static function fromAnthropic(array $message): self
    {
        $role = $message['role'];
        $contentBlocks = null;
        $content = '';
        $toolCalls = null;

        // 处理内容
        if (is_array($message['content'] ?? null)) {
            $contentBlocks = array_map(
                fn ($block) => ContentBlock::fromAnthropic($block),
                $message['content']
            );
            $content = self::extractTextFromBlocks($contentBlocks);
        } else {
            $content = $message['content'] ?? '';
        }

        // 处理工具调用 (在 content blocks 中)
        if ($contentBlocks !== null) {
            $toolUseBlocks = array_filter(
                $contentBlocks,
                fn ($block) => $block->type === 'tool_use'
            );
            if (! empty($toolUseBlocks)) {
                // 使用 array_values 重新索引，确保索引从 0 开始连续
                $toolCalls = array_values(array_map(
                    fn ($block) => StandardToolCall::fromAnthropic($block->toArray()),
                    $toolUseBlocks
                ));
            }
        }

        return new self(
            role: $role,
            content: $content,
            contentBlocks: $contentBlocks,
            toolCalls: $toolCalls,
        );
    }

    /**
     * 转换为 OpenAI 格式
     */
    public function toOpenAI(): array
    {
        $result = ['role' => $this->role];

        // 处理内容
        if ($this->role === 'tool') {
            // tool 消息
            $result['content'] = $this->content;
            $result['tool_call_id'] = $this->toolCallId;
        } elseif ($this->contentBlocks !== null) {
            // 多模态内容 - 过滤掉 tool_use 和 tool_result 块
            $nonToolBlocks = array_filter(
                $this->contentBlocks,
                fn ($block) => ! in_array($block->type, ['tool_use', 'tool_result'])
            );
            if (! empty($nonToolBlocks)) {
                $result['content'] = array_map(
                    fn ($block) => $block->toOpenAI(),
                    $nonToolBlocks
                );
            } else {
                // 如果只有 tool_use/tool_result 块，content 设为 null
                $result['content'] = null;
            }
        } else {
            // 纯文本
            $result['content'] = $this->content ?: null;
        }

        // 处理工具调用 (只有非空时才添加)
        if ($this->toolCalls !== null && count($this->toolCalls) > 0) {
            $result['tool_calls'] = array_map(
                fn ($tc) => $tc->toOpenAI(),
                $this->toolCalls
            );
        }

        // 处理名称
        if ($this->name !== null) {
            $result['name'] = $this->name;
        }

        return $result;
    }

    /**
     * 转换为 Anthropic 格式
     *
     * @param  bool  $includeCacheControl  是否包含 cache_control 字段
     * @param  bool  $filterThinking  是否过滤响应中的 thinking 内容块
     * @param  bool  $filterRequestThinking  是否过滤请求中的 thinking 内容块
     */
    public function toAnthropic(bool $includeCacheControl = true, bool $filterThinking = true, bool $filterRequestThinking = false): array
    {
        // tool 角色需要转换为 user 角色的 tool_result content block
        if ($this->role === 'tool') {
            // 从 contentBlocks 中提取完整信息
            if ($this->contentBlocks !== null) {
                foreach ($this->contentBlocks as $block) {
                    if ($block->type === 'tool_result') {
                        // 使用 ContentBlock 的 toAnthropic 方法，保证字段顺序和 is_error 正确输出
                        $contentBlock = $block->toAnthropic($includeCacheControl);
                        break;
                    }
                }
            }

            // 如果没有找到 contentBlock，手动构建
            if (! isset($contentBlock)) {
                $contentBlock = [
                    'type' => 'tool_result',
                    'tool_use_id' => $this->toolCallId,
                    'content' => $this->content,
                ];
            }

            return [
                'role' => 'user',
                'content' => [$contentBlock],
            ];
        }

        $result = ['role' => $this->role];

        // 处理内容
        if ($this->contentBlocks !== null) {
            $filteredBlocks = $this->contentBlocks;

            // filterRequestThinking: 过滤转发请求中的所有 thinking 块（无论哪个角色）
            // filterThinking: 仅在过滤响应时使用（由上层响应处理逻辑控制）
            if ($filterRequestThinking) {
                $filteredBlocks = array_filter(
                    $filteredBlocks,
                    fn ($block) => $block->type !== 'thinking'
                );
                // 重新索引数组
                $filteredBlocks = array_values($filteredBlocks);
            }

            if (! empty($filteredBlocks)) {
                $result['content'] = array_map(
                    fn ($block) => $block->toAnthropic($includeCacheControl),
                    $filteredBlocks
                );
            } else {
                // 如果过滤后为空，使用空字符串
                $result['content'] = '';
            }
        } elseif ($this->toolCalls !== null) {
            // 工具调用转为 content blocks
            $blocks = [];
            if ($this->content) {
                $blocks[] = ['type' => 'text', 'text' => $this->content];
            }
            foreach ($this->toolCalls as $tc) {
                $blocks[] = $tc->toAnthropic();
            }
            $result['content'] = $blocks;
        } else {
            $result['content'] = $this->content;
        }

        return $result;
    }

    /**
     * 是否包含多模态内容
     */
    public function isMultimodal(): bool
    {
        return $this->contentBlocks !== null && count($this->contentBlocks) > 0;
    }

    /**
     * 获取纯文本内容
     */
    public function getTextContent(): string
    {
        if ($this->contentBlocks === null) {
            return $this->content;
        }

        return self::extractTextFromBlocks($this->contentBlocks);
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = ['role' => $this->role];

        if ($this->contentBlocks !== null) {
            $result['content_blocks'] = array_map(fn ($b) => $b->toArray(), $this->contentBlocks);
        } else {
            $result['content'] = $this->content;
        }

        if ($this->toolCalls !== null) {
            $result['tool_calls'] = array_map(fn ($t) => $t->toArray(), $this->toolCalls);
        }

        if ($this->toolCallId !== null) {
            $result['tool_call_id'] = $this->toolCallId;
        }

        if ($this->name !== null) {
            $result['name'] = $this->name;
        }

        return $result;
    }

    /**
     * 从内容块提取文本
     */
    private static function extractTextFromBlocks(array $blocks): string
    {
        $text = '';
        foreach ($blocks as $block) {
            if ($block->type === 'text') {
                $text .= $block->text ?? '';
            }
        }

        return $text;
    }
}
