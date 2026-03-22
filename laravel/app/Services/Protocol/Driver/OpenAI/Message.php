<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\Convertible;
use App\Services\Protocol\Driver\Concerns\JsonSerializiable;
use App\Services\Shared\DTO\Message as SharedMessage;
use App\Services\Shared\Enums\MessageRole;

/**
 * OpenAI 消息结构体
 *
 * @see https://platform.openai.com/docs/api-reference/chat/create#chat-create-messages
 */
class Message
{
    use Convertible;
    use JsonSerializiable;

    /**
     * @param  string  $role  角色（system|user|assistant|tool）
     * @param  string|ContentPart[]|null  $content  内容（字符串或 ContentPart 数组）
     * @param  array|null  $tool_calls  工具调用
     * @param  string|null  $tool_call_id  工具调用ID（tool 角色时必需）
     * @param  string|null  $name  名称
     */
    public function __construct(
        public string $role,
        public string|array|null $content = null,
        public ?array $tool_calls = null,
        public ?string $tool_call_id = null,
        public ?string $name = null,
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'role' => 'required|string|in:system,user,assistant,tool',
            'content' => 'nullable',
            'tool_calls' => 'nullable|array',
            'tool_call_id' => 'required_if:role,tool|nullable|string',
            'name' => 'nullable|string',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        // 解析 content
        $content = $data['content'] ?? null;
        if (is_array($content)) {
            // 多模态内容，转换为 ContentPart 对象数组
            $content = array_map(
                fn ($part) => is_array($part) ? ContentPart::fromArray($part) : $part,
                $content
            );
        }

        return new self(
            role: $data['role'] ?? 'user',
            content: $content,
            tool_calls: $data['tool_calls'] ?? null,
            tool_call_id: $data['tool_call_id'] ?? null,
            name: $data['name'] ?? null,
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

        if ($this->content !== null) {
            if (is_array($this->content)) {
                // ContentPart 数组转数组
                $result['content'] = array_map(
                    fn ($part) => $part instanceof ContentPart ? $part->toArray() : $part,
                    $this->content
                );
            } else {
                $result['content'] = $this->content;
            }
        }

        if ($this->tool_calls !== null) {
            $result['tool_calls'] = $this->tool_calls;
        }

        if ($this->tool_call_id !== null) {
            $result['tool_call_id'] = $this->tool_call_id;
        }

        if ($this->name !== null) {
            $result['name'] = $this->name;
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
            // ContentPart 数组转换为 ContentBlock 数组
            $contentBlocks = [];
            foreach ($this->content as $part) {
                if ($part instanceof ContentPart) {
                    $contentBlocks[] = $part->toSharedDTO();
                } elseif (is_array($part)) {
                    $contentBlocks[] = \App\Services\Shared\DTO\ContentBlock::fromOpenAI($part);
                }
            }
        }

        return new SharedMessage(
            role: MessageRole::from($this->role),
            content: $content,
            toolCalls: $this->tool_calls,
            toolCallId: $this->tool_call_id,
            contentBlocks: $contentBlocks,
            name: $this->name,
        );
    }

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(object $dto): static
    {
        // 处理 content
        $content = null;

        if ($dto->content !== null) {
            $content = $dto->content;
        } elseif ($dto->contentBlocks !== null) {
            // 从 ContentBlock 转换为 ContentPart 数组
            $content = array_map(
                fn ($block) => ContentPart::fromSharedDTO($block),
                $dto->contentBlocks
            );
        }

        return new self(
            role: $dto->role->value,
            content: $content,
            tool_calls: $dto->toolCalls,
            tool_call_id: $dto->toolCallId,
            name: $dto->name ?? null,
        );
    }
}
