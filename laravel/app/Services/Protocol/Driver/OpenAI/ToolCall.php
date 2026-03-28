<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;
use App\Services\Shared\DTO\ToolCall as SharedToolCall;
use App\Services\Shared\Enums\ToolType;

/**
 * OpenAI 工具调用结构体
 *
 * 用于响应中的 tool_calls 字段
 *
 * @see https://platform.openai.com/docs/api-reference/chat/object#chat/object-choices-message-tool_calls
 */
class ToolCall
{
    use JsonSerializiable;

    /**
     * @param  string  $id  工具调用ID
     * @param  string  $type  类型（默认 function）
     * @param  ToolCallFunction|null  $function  函数调用详情
     * @param  int|null  $index  索引（流式响应中使用）
     */
    public function __construct(
        public string $id = '',
        public string $type = 'function',
        public ?ToolCallFunction $function = null,
        public ?int $index = null,
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'id' => 'required|string',
            'type' => 'required|string',
            'function' => 'required|array',
            'index' => 'nullable|integer',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        $function = null;
        if (isset($data['function']) && is_array($data['function'])) {
            $function = ToolCallFunction::fromArray($data['function']);
        }

        return new self(
            id: $data['id'] ?? '',
            type: $data['type'] ?? 'function',
            function: $function,
            index: $data['index'] ?? null,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
            'type' => $this->type,
        ];

        if ($this->function !== null) {
            $result['function'] = $this->function->toArray();
        }

        // 流式响应中包含 index
        if ($this->index !== null) {
            $result['index'] = $this->index;
        }

        return $result;
    }

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(SharedToolCall $dto): static
    {
        return new self(
            id: $dto->id ?? '',
            type: $dto->type->value ?? 'function',
            function: new ToolCallFunction(
                name: $dto->name ?? '',
                arguments: $dto->arguments ?? '',
            ),
            index: $dto->index,
        );
    }

    /**
     * 转换为 Shared\DTO
     */
    public function toSharedDTO(): SharedToolCall
    {
        $dto = new SharedToolCall;
        $dto->id = $this->id;
        $dto->type = ToolType::from($this->type);
        $dto->name = $this->function?->name;
        $dto->arguments = $this->function?->arguments;
        $dto->index = $this->index;

        return $dto;
    }
}
