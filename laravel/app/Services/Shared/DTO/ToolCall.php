<?php

namespace App\Services\Shared\DTO;

use App\Services\Shared\Enums\ToolType;

/**
 * 工具调用 DTO
 *
 * 纯数据容器，不包含业务逻辑
 */
class ToolCall
{
    public function __construct(
        public ?string $id = null,
        public ToolType $type = ToolType::Function,
        public ?string $name = null,
        public ?string $arguments = null,         // 字符串形式
        public ?int $index = null,                // 流式场景中的索引
        public ?string $callId = null,            // Anthropic uses tool_use_id
    ) {}

    /**
     * 从 OpenAI 格式创建
     */
    public static function fromOpenAI(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            type: ToolType::from($data['type'] ?? 'function'),
            name: $data['function']['name'] ?? null,
            arguments: $data['function']['arguments'] ?? null,
            index: $data['index'] ?? null,
        );
    }

    /**
     * 从 Anthropic 格式创建
     */
    public static function fromAnthropic(array $data): self
    {
        $arguments = $data['input'] ?? [];
        if (is_array($arguments)) {
            $arguments = json_encode($arguments, JSON_UNESCAPED_UNICODE);
        }

        return new self(
            id: $data['id'] ?? null,
            type: ToolType::Function, // Anthropic 的 tool_use 对应 function 类型
            name: $data['name'] ?? null,
            arguments: $arguments,
            callId: $data['id'] ?? null,
        );
    }

    /**
     * 获取解析后的参数
     */
    public function getParsedArguments(): array
    {
        if ($this->arguments === null) {
            return [];
        }

        if (is_array($this->arguments)) {
            return $this->arguments;
        }

        return json_decode($this->arguments, true) ?? [];
    }

    /**
     * 转换为 OpenAI 格式
     */
    public function toOpenAI(): array
    {
        return [
            'id' => $this->id ?? '',
            'type' => $this->type->value,
            'function' => [
                'name' => $this->name ?? '',
                'arguments' => $this->arguments ?? '',
            ],
        ];
    }

    /**
     * 转换为 Anthropic 格式
     */
    public function toAnthropic(): array
    {
        $input = $this->getParsedArguments();

        return [
            'type' => 'tool_use',
            'id' => $this->id ?? $this->callId ?? '',
            'name' => $this->name ?? '',
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
            'type' => $this->type->value,
            'name' => $this->name,
            'arguments' => $this->arguments,
            'index' => $this->index,
            'call_id' => $this->callId,
        ];
    }
}
