<?php

namespace App\Services\Shared\DTO;

use App\Services\Shared\Enums\MessageRole;

/**
 * 消息 DTO
 *
 * 纯数据容器，不包含业务逻辑
 */
class Message
{
    public function __construct(
        public MessageRole $role,
        public ?string $content = null,
        public ?array $toolCalls = null, // ToolCall[]
        public ?string $toolCallId = null,
        public ?array $contentBlocks = null, // ContentBlock[] (Anthropic)
        public ?string $name = null, // 消息作者名称 (OpenAI)
    ) {}

    /**
     * 获取纯文本内容
     */
    public function getTextContent(): string
    {
        if ($this->contentBlocks !== null) {
            $text = '';
            foreach ($this->contentBlocks as $block) {
                if ($block->type === 'text') {
                    $text .= $block->text ?? '';
                }
            }

            return $text;
        }

        return $this->content ?? '';
    }

    /**
     * 是否包含多模态内容
     */
    public function isMultimodal(): bool
    {
        return $this->contentBlocks !== null && count($this->contentBlocks) > 0;
    }

    /**
     * 转换为 OpenAI 格式
     */
    public function toOpenAI(): array
    {
        $result = ['role' => $this->role->value];

        // 添加 name 字段（如果存在）
        if ($this->name !== null) {
            $result['name'] = $this->name;
        }

        // 处理内容
        if ($this->role === MessageRole::Tool) {
            $result['content'] = $this->content;
            $result['tool_call_id'] = $this->toolCallId;
        } elseif ($this->contentBlocks !== null) {
            // 多模态内容 - 过滤掉 tool_use 和 tool_result 块
            $nonToolBlocks = array_filter(
                $this->contentBlocks,
                fn ($block) => ! in_array($block->type, ['tool_use', 'tool_result'])
            );
            if (! empty($nonToolBlocks)) {
                // 重新索引数组,确保 JSON 编码为数组而不是对象
                $nonToolBlocks = array_values($nonToolBlocks);

                // 检查是否全是纯文本块，如果是则合并成单个字符串（兼容更多上游API）
                $allText = true;
                $textContent = '';
                foreach ($nonToolBlocks as $block) {
                    if ($block->type !== 'text') {
                        $allText = false;
                        break;
                    }
                    $textContent .= $block->text ?? '';
                }

                if ($allText) {
                    // 全是文本，直接使用合并后的字符串
                    $result['content'] = $textContent;
                } else {
                    // 包含非文本内容，使用数组格式
                    $result['content'] = array_map(
                        fn ($block) => $block->toOpenAI(),
                        $nonToolBlocks
                    );
                }
            } else {
                $result['content'] = null;
            }
        } else {
            $result['content'] = $this->content ?: null;
        }

        // 处理工具调用
        if ($this->toolCalls !== null && count($this->toolCalls) > 0) {
            $result['tool_calls'] = array_map(
                fn ($tc) => $tc->toOpenAI(),
                $this->toolCalls
            );
        }

        return $result;
    }

    /**
     * 转换为 Anthropic 格式
     */
    public function toAnthropic(bool $includeCacheControl = true, bool $filterThinking = true, bool $filterRequestThinking = false): array
    {
        // tool 角色需要转换为 user 角色的 tool_result content block
        if ($this->role === MessageRole::Tool) {
            $contentBlock = null;
            if ($this->contentBlocks !== null) {
                foreach ($this->contentBlocks as $block) {
                    if ($block->type === 'tool_result') {
                        $contentBlock = $block->toAnthropic($includeCacheControl);
                        break;
                    }
                }
            }

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

        $result = ['role' => $this->role->value];

        // 处理内容
        if ($this->contentBlocks !== null) {
            $filteredBlocks = $this->contentBlocks;

            if ($filterRequestThinking) {
                $filteredBlocks = array_filter(
                    $filteredBlocks,
                    fn ($block) => $block->type !== 'thinking'
                );
                $filteredBlocks = array_values($filteredBlocks);
            }

            if (! empty($filteredBlocks)) {
                $result['content'] = array_map(
                    fn ($block) => $block->toAnthropic($includeCacheControl),
                    $filteredBlocks
                );
            } else {
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
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = ['role' => $this->role->value];

        if ($this->name !== null) {
            $result['name'] = $this->name;
        }

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

        return $result;
    }
}
