<?php

namespace App\Services\Shared\DTO;

/**
 * 统一响应 DTO
 *
 * 纯数据容器，不包含业务逻辑
 */
class Response
{
    /**
     * 响应 ID
     */
    public string $id;

    /**
     * 模型名称
     */
    public string $model;

    /**
     * 选择列表
     *
     * @var array Choice[]
     */
    public array $choices;

    /**
     * 使用量
     */
    public ?Usage $usage = null;

    /**
     * 结束原因
     */
    public ?string $finishReason = null;

    /**
     * 系统指纹
     */
    public ?string $systemFingerprint = null;

    /**
     * 创建时间
     */
    public int $created = 0;

    /**
     * 工具调用列表
     *
     * @var array|null ToolCall[]
     */
    public ?array $toolCalls = null;

    /**
     * 容器信息 (Anthropic)
     */
    public ?array $container = null;

    /**
     * 原始响应
     */
    public ?array $rawResponse = null;

    /**
     * 获取第一个选择的内容
     */
    public function getContent(): ?string
    {
        $choice = $this->choices[0] ?? null;
        if ($choice === null) {
            return null;
        }

        return $choice['message']['content'] ?? null;
    }

    /**
     * 获取第一个选择的工具调用
     */
    public function getToolCalls(): ?array
    {
        $choice = $this->choices[0] ?? null;
        if ($choice === null) {
            return null;
        }

        return $choice['message']['tool_calls'] ?? null;
    }
}
