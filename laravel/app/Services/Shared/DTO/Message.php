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
    /**
     * 消息角色
     */
    public MessageRole $role;

    /**
     * 消息内容
     */
    public ?string $content = null;

    /**
     * 工具调用列表
     *
     * @var array|null ToolCall[]
     */
    public ?array $toolCalls = null;

    /**
     * 工具调用 ID
     */
    public ?string $toolCallId = null;

    /**
     * 内容块列表 (Anthropic)
     *
     * @var array|null ContentBlock[]
     */
    public ?array $contentBlocks = null;

    /**
     * 消息作者名称 (OpenAI)
     */
    public ?string $name = null;

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
}
