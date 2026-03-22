<?php

namespace App\Services\Protocol\Driver\Anthropic;

use App\Services\Protocol\Driver\Concerns\Convertible;
use App\Services\Protocol\Driver\Concerns\JsonSerializiable;
use App\Services\Shared\DTO\Message as SharedMessage;
use App\Services\Shared\Enums\MessageRole;

/**
 * Anthropic 消息参数结构体
 *
 * 注意：此类对应官方 SDK 的 MessageParam（请求消息参数）
 * 官方 SDK 的 Message 类对应本项目中的 MessagesResponse（响应消息）
 *
 * @see https://docs.anthropic.com/en/api/messages#request-body-messages
 * @see \Anthropic\Messages\MessageParam 官方 SDK 对应类
 */
class Message
{
    use Convertible;
    use JsonSerializiable;

    /**
     * @param  string  $role  角色（user|assistant）
     * @param  ContentBlock[]|string  $content  内容块数组或字符串
     */
    public function __construct(
        public string $role = 'user',
        public array|string $content = '',
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'role' => 'required|string|in:user,assistant',
            'content' => 'required',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        $content = $data['content'] ?? '';

        // 如果 content 是数组，转换为 ContentBlock 对象
        if (is_array($content)) {
            $content = array_map(
                fn ($block) => is_array($block) ? ContentBlock::fromArray($block) : $block,
                $content
            );
        }

        return new self(
            role: $data['role'] ?? 'user',
            content: $content,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'role' => $this->role,
        ];

        if (is_array($this->content)) {
            $result['content'] = array_map(
                fn ($block) => $block instanceof ContentBlock ? $block->toArray() : $block,
                $this->content
            );
        } else {
            $result['content'] = $this->content;
        }

        return $result;
    }

    /**
     * 转换为 Shared\DTO
     */
    public function toSharedDTO(): SharedMessage
    {
        // 处理 content
        $content = null;
        $contentBlocks = null;

        if (is_string($this->content)) {
            $content = $this->content;
        } elseif (is_array($this->content)) {
            $contentBlocks = [];
            foreach ($this->content as $block) {
                if ($block instanceof ContentBlock) {
                    $contentBlocks[] = $block->toSharedDTO();
                } elseif (is_array($block)) {
                    $contentBlocks[] = \App\Services\Shared\DTO\ContentBlock::fromAnthropic($block);
                }
            }
        }

        return new SharedMessage(
            role: MessageRole::from($this->role),
            content: $content,
            contentBlocks: $contentBlocks,
        );
    }

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(object $dto): static
    {
        // 处理 content
        $content = '';

        if ($dto->content !== null) {
            $content = $dto->content;
        } elseif ($dto->contentBlocks !== null) {
            $content = array_map(
                fn ($block) => ContentBlock::fromSharedDTO($block),
                $dto->contentBlocks
            );
        }

        return new self(
            role: $dto->role->value,
            content: $content,
        );
    }
}
