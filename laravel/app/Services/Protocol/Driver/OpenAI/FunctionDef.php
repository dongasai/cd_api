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
            // 递归修复 JSON Schema 中的所有无效字段
            $parameters = $this->fixJsonSchema($this->parameters);
            $result['parameters'] = $parameters;
        }

        if ($this->strict !== null) {
            $result['strict'] = $this->strict;
        }

        return $result;
    }

    /**
     * 递归修复 JSON Schema 中的无效字段
     *
     * @param  array  $schema  JSON Schema 定义
     * @return array 修复后的 Schema
     */
    private function fixJsonSchema(array $schema): array
    {
        // 修复空的 properties 字段：[] -> {}
        if (isset($schema['properties']) && is_array($schema['properties']) && empty($schema['properties'])) {
            $schema['properties'] = new \stdClass;
        }

        // 修复空的 additionalProperties 字段：[] -> false
        if (isset($schema['additionalProperties']) && is_array($schema['additionalProperties']) && empty($schema['additionalProperties'])) {
            $schema['additionalProperties'] = false;
        }

        // 递归处理嵌套的 properties
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $key => $value) {
                if (is_array($value)) {
                    $schema['properties'][$key] = $this->fixJsonSchema($value);
                }
            }
        }

        // 递归处理 items（数组项定义）
        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->fixJsonSchema($schema['items']);
        }

        // 递归处理其他可能嵌套 schema 的字段
        foreach (['anyOf', 'allOf', 'oneOf', 'not'] as $keyword) {
            if (isset($schema[$keyword]) && is_array($schema[$keyword])) {
                foreach ($schema[$keyword] as $i => $value) {
                    if (is_array($value)) {
                        $schema[$keyword][$i] = $this->fixJsonSchema($value);
                    }
                }
            }
        }

        return $schema;
    }
}
