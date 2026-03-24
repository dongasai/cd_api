<?php

namespace App\Services\Shared\DTO;

/**
 * 工具调用 DTO
 *
 * 纯数据容器，不包含业务逻辑
 */
class ToolCall
{
    /**
     * 工具调用 ID
     */
    public ?string $id = null;

    /**
     * 工具调用类型
     */
    public string $type = 'function';

    /**
     * 工具名称
     */
    public ?string $name = null;

    /**
     * 工具参数（字符串形式）
     */
    public ?string $arguments = null;

    /**
     * 流式场景中的索引
     */
    public ?int $index = null;

    /**
     * Anthropic tool_use_id
     */
    public ?string $callId = null;

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
}
