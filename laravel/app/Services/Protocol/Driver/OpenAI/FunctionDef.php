<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;

/**
 * OpenAI 函数定义结构体
 *
 * @see https://platform.openai.com/docs/api-reference/chat/create#chat-create-tools
 */
class FunctionDef
{
    use JsonSerializiable;

    /**
     * @param  string  $name  函数名称（最长64字符）
     * @param  string|null  $description  函数描述
     * @param  array|null  $parameters  参数 JSON Schema
     * @param  bool|null  $strict  是否启用严格模式
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?array $parameters = null,
        public ?bool $strict = null,
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'name' => 'required|string|max:64',
            'description' => 'nullable|string',
            'parameters' => 'nullable|array',
            'strict' => 'nullable|boolean',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        return new self(
            name: $data['name'] ?? '',
            description: $data['description'] ?? null,
            parameters: $data['parameters'] ?? null,
            strict: $data['strict'] ?? null,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'name' => $this->name,
        ];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->parameters !== null) {
            $result['parameters'] = $this->parameters;
        }

        if ($this->strict !== null) {
            $result['strict'] = $this->strict;
        }

        return $result;
    }
}
