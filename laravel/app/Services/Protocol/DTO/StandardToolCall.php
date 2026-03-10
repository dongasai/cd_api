<?php

namespace App\Services\Protocol\DTO;

/**
 * 标准工具调用 DTO
 */
class StandardToolCall
{
    public function __construct(
        // 工具调用ID
        public string $id,

        // 工具类型 (通常为 function)
        public string $type = 'function',

        // 函数名称
        public string $name = '',

        // 函数参数 (JSON字符串或数组)
        public string|array $arguments = [],

        // 索引 (流式响应中)
        public ?int $index = null,
    ) {}

    /**
     * 从 OpenAI 格式创建
     */
    public static function fromOpenAI(array $toolCall, ?int $index = null): self
    {
        $function = $toolCall['function'] ?? [];

        return new self(
            id: $toolCall['id'] ?? '',
            type: $toolCall['type'] ?? 'function',
            name: $function['name'] ?? '',
            arguments: $function['arguments'] ?? '',
            index: $index ?? $toolCall['index'] ?? null,
        );
    }

    /**
     * 从 Anthropic 格式创建
     * 支持两种格式:
     * - 原始 Anthropic 格式: {id, name, input}
     * - ContentBlock::toArray() 格式: {tool_id, tool_name, tool_input}
     */
    public static function fromAnthropic(array $block): self
    {
        // 支持两种字段名格式
        $id = $block['id'] ?? $block['tool_id'] ?? '';
        $name = $block['name'] ?? $block['tool_name'] ?? '';
        $input = $block['input'] ?? $block['tool_input'] ?? [];

        return new self(
            id: $id,
            type: 'function',
            name: $name,
            arguments: $input,
            index: $block['index'] ?? null,
        );
    }

    /**
     * 转换为 OpenAI 格式
     */
    public function toOpenAI(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'function' => [
                'name' => $this->name,
                'arguments' => is_array($this->arguments)
                    ? json_encode($this->arguments, JSON_UNESCAPED_UNICODE)
                    : $this->arguments,
            ],
        ];
    }

    /**
     * 转换为 Anthropic 格式
     */
    public function toAnthropic(): array
    {
        // 处理 arguments 字段
        $input = $this->arguments;
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if ($decoded !== null) {
                $input = $decoded;
            }
            // 如果 json_decode 失败，保持原始字符串
        }

        return [
            'type' => 'tool_use',
            'id' => $this->id,
            'name' => $this->name,
            'input' => $input,
        ];
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'arguments' => $this->arguments,
            'index' => $this->index,
        ];
    }

    /**
     * 获取解析后的参数
     */
    public function getParsedArguments(): array
    {
        if (is_array($this->arguments)) {
            return $this->arguments;
        }

        return json_decode($this->arguments, true) ?? [];
    }
}
