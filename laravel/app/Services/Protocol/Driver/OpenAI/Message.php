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
     * @param  ToolCall[]|null  $toolCalls  工具调用列表（响应中使用）
     * @param  FunctionCall|null  $functionCall  函数调用（已废弃，但旧模型可能返回）
     * @param  string|null  $toolCallId  工具调用ID（tool 角色时必需）
     * @param  string|null  $name  名称
     * @param  string|null  $reasoningContent  推理内容（o1 等模型）
     * @param  Annotation[]|null  $annotations  注解列表（用于搜索引用）
     * @param  MessageAudio|null  $audio  音频输出
     * @param  MessageImage[]|null  $images  图片输出列表
     */
    public function __construct(
        public string $role,
        public string|array|null $content = null,
        public ?array $toolCalls = null,
        public ?FunctionCall $functionCall = null,
        public ?string $toolCallId = null,
        public ?string $name = null,
        public ?string $reasoningContent = null,
        public ?array $annotations = null,
        public ?MessageAudio $audio = null,
        public ?array $images = null,
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
            'function_call' => 'nullable|array',
            'tool_call_id' => 'required_if:role,tool|nullable|string',
            'name' => 'nullable|string',
            'reasoning_content' => 'nullable|string',
            'annotations' => 'nullable|array',
            'audio' => 'nullable|array',
            'images' => 'nullable|array',
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

        // 解析 tool_calls
        $toolCalls = null;
        if (isset($data['tool_calls']) && is_array($data['tool_calls'])) {
            $toolCalls = array_map(
                fn (array $tc) => ToolCall::fromArray($tc),
                $data['tool_calls']
            );
        }

        // 解析 function_call（已废弃字段）
        $functionCall = null;
        if (isset($data['function_call']) && is_array($data['function_call'])) {
            $functionCall = FunctionCall::fromArray($data['function_call']);
        }

        // 解析 annotations
        $annotations = null;
        if (isset($data['annotations']) && is_array($data['annotations'])) {
            $annotations = array_map(
                fn (array $ann) => Annotation::fromArray($ann),
                $data['annotations']
            );
        }

        // 解析 audio
        $audio = null;
        if (isset($data['audio']) && is_array($data['audio'])) {
            $audio = MessageAudio::fromArray($data['audio']);
        }

        // 解析 images
        $images = null;
        if (isset($data['images']) && is_array($data['images'])) {
            $images = array_map(
                fn (array $img) => MessageImage::fromArray($img),
                $data['images']
            );
        }

        return new self(
            role: $data['role'] ?? 'user',
            content: $content,
            toolCalls: $toolCalls,
            functionCall: $functionCall,
            toolCallId: $data['tool_call_id'] ?? null,
            name: $data['name'] ?? null,
            reasoningContent: $data['reasoning_content'] ?? null,
            annotations: $annotations,
            audio: $audio,
            images: $images,
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

        if ($this->toolCalls !== null) {
            $result['tool_calls'] = array_map(
                fn (ToolCall $tc) => $tc->toArray(),
                $this->toolCalls
            );
        }

        if ($this->functionCall !== null) {
            $result['function_call'] = $this->functionCall->toArray();
        }

        if ($this->toolCallId !== null) {
            $result['tool_call_id'] = $this->toolCallId;
        }

        if ($this->name !== null) {
            $result['name'] = $this->name;
        }

        if ($this->reasoningContent !== null) {
            $result['reasoning_content'] = $this->reasoningContent;
        }

        if ($this->annotations !== null && count($this->annotations) > 0) {
            $result['annotations'] = array_map(
                fn (Annotation $ann) => $ann->toArray(),
                $this->annotations
            );
        }

        if ($this->audio !== null) {
            $result['audio'] = $this->audio->toArray();
        }

        if ($this->images !== null && count($this->images) > 0) {
            $result['images'] = array_map(
                fn (MessageImage $img) => $img->toArray(),
                $this->images
            );
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
                    // 直接构建 ContentBlock，不调用 fromOpenAI
                    $contentBlocks[] = \App\Services\Shared\DTO\ContentBlock::fromArray($part);
                }
            }
        }

        // 转换 toolCalls
        $toolCalls = null;
        if ($this->toolCalls !== null) {
            $toolCalls = array_map(
                fn (ToolCall $tc) => $tc->toArray(),
                $this->toolCalls
            );
        }

        // 转换 functionCall（如果存在）
        if ($this->functionCall !== null) {
            // 将 function_call 转换为 tool_calls 格式
            $toolCalls = $toolCalls ?? [];
            $toolCalls[] = [
                'id' => 'call_'.uniqid(),
                'type' => 'function',
                'function' => $this->functionCall->toArray(),
            ];
        }

        $dto = new SharedMessage;
        $dto->role = MessageRole::from($this->role);
        $dto->content = $content;
        $dto->toolCalls = $toolCalls;
        $dto->toolCallId = $this->toolCallId;
        $dto->contentBlocks = $contentBlocks;
        $dto->name = $this->name;

        return $dto;
    }

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(object $dto): static
    {
        // 处理 content
        $content = null;
        $toolCalls = null;

        if ($dto->content !== null) {
            $content = $dto->content;
        } elseif ($dto->contentBlocks !== null) {
            // 从 ContentBlock 转换，需要分离 tool_use
            $contentParts = [];
            $toolCallsFromBlocks = [];

            foreach ($dto->contentBlocks as $block) {
                if ($block->type === 'tool_use') {
                    // tool_use 转换为 ToolCall，不在 content 中
                    $toolCallsFromBlocks[] = ToolCall::fromArray([
                        'id' => $block->toolId ?? '',
                        'type' => 'function',
                        'function' => [
                            'name' => $block->toolName ?? '',
                            'arguments' => json_encode($block->toolInput ?? []),
                        ],
                    ]);
                } elseif ($block->type === 'thinking') {
                    // thinking 内容暂时忽略，或可转为 reasoning_content
                    // 注意：OpenAI 某些模型支持 reasoning_content 字段
                    // 这里暂时跳过，避免污染 content
                    continue;
                } else {
                    // 其他类型正常转换为 ContentPart
                    $contentParts[] = ContentPart::fromSharedDTO($block);
                }
            }

            // 设置 content（如果有非 tool_use/thinking 内容）
            if (! empty($contentParts)) {
                $content = $contentParts;
            }

            // 合并 toolCalls（从 contentBlocks 和 dto->toolCalls）
            if (! empty($toolCallsFromBlocks)) {
                $toolCalls = $toolCallsFromBlocks;
            }
        }

        // 处理 dto->toolCalls（如果有）
        if ($dto->toolCalls !== null) {
            $toolCalls = $toolCalls ?? [];
            $toolCalls = array_merge(
                $toolCalls,
                array_map(
                    fn (array $tc) => ToolCall::fromArray($tc),
                    $dto->toolCalls
                )
            );
        }

        return new self(
            role: $dto->role->value,
            content: $content,
            toolCalls: $toolCalls,
            toolCallId: $dto->toolCallId,
            name: $dto->name ?? null,
        );
    }
}
