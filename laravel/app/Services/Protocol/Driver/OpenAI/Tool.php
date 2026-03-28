<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;
use App\Services\Shared\DTO\Tool as SharedTool;

/**
 * OpenAI 工具定义结构体
 *
 * @see https://platform.openai.com/docs/api-reference/chat/create#chat-create-tools
 */
class Tool
{
    use JsonSerializiable;

    /**
     * @param  string  $type  工具类型（默认 function）
     * @param  FunctionDef|null  $function  函数定义
     */
    public function __construct(
        public string $type = 'function',
        public ?FunctionDef $function = null,
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'type' => 'required|string|in:function',
            'function' => 'required_if:type,function|array',
        ];
    }

    /**
     * 从数组创建（仅处理 OpenAI 格式）
     */
    public static function fromArray(array $data): static
    {
        $function = null;
        if (isset($data['function']) && is_array($data['function'])) {
            $function = FunctionDef::fromArray($data['function']);
        }

        return new self(
            type: $data['type'] ?? 'function',
            function: $function,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
        ];

        if ($this->function !== null) {
            $result['function'] = $this->function->toArray();
        }

        return $result;
    }

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(SharedTool $dto): static
    {
        return new self(
            type: 'function',
            function: new FunctionDef(
                name: $dto->name,
                description: $dto->description,
                parameters: $dto->parameters,
            ),
        );
    }

    /**
     * 转换为 Shared\DTO
     */
    public function toSharedDTO(): SharedTool
    {
        $dto = new SharedTool;
        $dto->name = $this->function?->name ?? '';
        $dto->parameters = $this->function?->parameters ?? [];
        $dto->description = $this->function?->description;

        return $dto;
    }
}
