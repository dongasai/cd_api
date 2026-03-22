<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;

/**
 * OpenAI 工具调用函数结构体
 *
 * @see https://platform.openai.com/docs/api-reference/chat/object#chat/object-choices-message-tool_calls-function
 */
class ToolCallFunction
{
    use JsonSerializiable;

    /**
     * @param  string  $name  函数名称
     * @param  string  $arguments  函数参数（JSON字符串）
     */
    public function __construct(
        public string $name = '',
        public string $arguments = '',
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'name' => 'required|string',
            'arguments' => 'required|string',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        return new self(
            name: $data['name'] ?? '',
            arguments: $data['arguments'] ?? '',
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];
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
