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
                $toolCalls = array_map(
                    fn ($block) => StandardToolCall::fromAnthropic($block->toArray()),
                    $toolUseBlocks
                );
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
            // 多模态内容
            $result['content'] = array_map(
                fn ($block) => $block->toOpenAI(),
                $this->contentBlocks
            );
        } else {
            // 纯文本
            $result['content'] = $this->content ?: null;
        }

        // 处理工具调用
        if ($this->toolCalls !== null) {
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
     */
    public function toAnthropic(): array
    {
        $result = ['role' => $this->role];

        // 处理内容
        if ($this->contentBlocks !== null) {
            $result['content'] = array_map(
                fn ($block) => $block->toAnthropic(),
                $this->contentBlocks
            );
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
