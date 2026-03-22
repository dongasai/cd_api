<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;

/**
 * OpenAI 函数调用结构体（已废弃，但仍可能出现在旧模型响应中）
 *
 * @see https://platform.openai.com/docs/api-reference/chat/object#chat/object-choices-message-function_call
 * @deprecated 使用 tool_calls 替代
 */
class FunctionCall
{
    use JsonSerializiable;

    /**
     * @param  string|null  $name  函数名称
     * @param  string|null  $arguments  函数参数（JSON字符串）
     */
    public function __construct(
        public ?string $name = null,
        public ?string $arguments = null,
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'name' => 'nullable|string',
            'arguments' => 'nullable|string',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        return new self(
            name: $data['name'] ?? null,
            arguments: $data['arguments'] ?? null,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [];

        if ($this->name !== null) {
            $result['name'] = $this->name;
        }

        if ($this->arguments !== null) {
            $result['arguments'] = $this->arguments;
        }

        return $result;
    }

    /**
     * 解析参数为关联数组
     */
    public function parseArguments(): ?array
    {
        if ($this->arguments === null) {
            return null;
        }

        $decoded = json_decode($this->arguments, true);

        return is_array($decoded) ? $decoded : null;
    }
}
