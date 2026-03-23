<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;

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
     * 从数组创建
     *
     * 支持 OpenAI 格式和 Anthropic 格式：
     * - OpenAI: {type: 'function', function: {name, description, parameters}}
     * - Anthropic: {name, input_schema, description}
     */
    public static function fromArray(array $data): static
    {
        // 检测并转换 Anthropic 格式
        if (isset($data['input_schema']) && ! isset($data['function'])) {
            // Anthropic 格式转换为 OpenAI 格式
            $data = [
                'type' => $data['type'] ?? 'function',
                'function' => [
                    'name' => $data['name'] ?? '',
                    'description' => $data['description'] ?? '',
                    'parameters' => $data['input_schema'] ?? [],
                ],
            ];
        }

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
}
