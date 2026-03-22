<?php

namespace App\Services\Protocol\Driver\Anthropic;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;

/**
 * Anthropic 工具定义结构体
 *
 * @see https://docs.anthropic.com/en/api/messages#request-body-tools
 */
class Tool
{
    use JsonSerializiable;

    /**
     * @param  string  $name  工具名称
     * @param  string|null  $description  工具描述
     * @param  array  $input_schema  输入 JSON Schema
     */
    public function __construct(
        public string $name = '',
        public ?string $description = null,
        public array $input_schema = [],
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'name' => 'required|string',
            'description' => 'nullable|string',
            'input_schema' => 'required|array',
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
            input_schema: $data['input_schema'] ?? [],
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'name' => $this->name,
            'input_schema' => $this->input_schema,
        ];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        return $result;
    }
}
