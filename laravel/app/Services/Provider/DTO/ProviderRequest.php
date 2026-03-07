<?php

namespace App\Services\Provider\DTO;

/**
 * 供应商请求数据传输对象
 *
 * 用于封装发送给 AI 供应商的请求数据
 */
class ProviderRequest
{
    /**
     * 模型名称
     */
    public string $model;

    /**
     * 消息列表
     */
    public array $messages;

    /**
     * 采样温度参数
     */
    public ?float $temperature = null;

    /**
     * 最大输出 Token 数
     */
    public ?int $maxTokens = null;

    /**
     * 是否启用流式响应
     */
    public bool $stream = false;

    /**
     * 其他参数
     */
    public array $parameters = [];

    /**
     * 系统提示
     */
    public ?string $systemPrompt = null;

    /**
     * Top-P 采样参数
     */
    public ?float $topP = null;

    /**
     * 停止序列
     */
    public ?array $stop = null;

    /**
     * 工具定义
     */
    public ?array $tools = null;

    /**
     * 工具选择策略
     */
    public mixed $toolChoice = null;

    /**
     * 用户标识
     */
    public ?string $user = null;

    /**
     * 从数组创建实例
     *
     * @param  array  $data  请求数据
     */
    public static function fromArray(array $data): self
    {
        return new self(
            model: $data['model'] ?? '',
            messages: $data['messages'] ?? [],
            temperature: $data['temperature'] ?? null,
            maxTokens: $data['max_tokens'] ?? $data['maxTokens'] ?? null,
            stream: $data['stream'] ?? false,
            parameters: $data['parameters'] ?? [],
            systemPrompt: $data['system'] ?? $data['systemPrompt'] ?? null,
            topP: $data['top_p'] ?? $data['topP'] ?? null,
            stop: $data['stop'] ?? null,
            tools: $data['tools'] ?? null,
            toolChoice: $data['tool_choice'] ?? $data['toolChoice'] ?? null,
            user: $data['user'] ?? null,
        );
    }

    /**
     * 转换为数组格式
     */
    public function toArray(): array
    {
        $result = [
            'model' => $this->model,
            'messages' => $this->messages,
        ];

        if ($this->temperature !== null) {
            $result['temperature'] = $this->temperature;
        }
        if ($this->maxTokens !== null) {
            $result['max_tokens'] = $this->maxTokens;
        }
        if ($this->stream) {
            $result['stream'] = true;
        }
        if ($this->systemPrompt !== null) {
            $result['system'] = $this->systemPrompt;
        }
        if ($this->topP !== null) {
            $result['top_p'] = $this->topP;
        }
        if ($this->stop !== null) {
            $result['stop'] = $this->stop;
        }
        if ($this->tools !== null) {
            $result['tools'] = $this->tools;
        }
        if ($this->toolChoice !== null) {
            $result['tool_choice'] = $this->toolChoice;
        }
        if ($this->user !== null) {
            $result['user'] = $this->user;
        }

        return array_merge($this->parameters, $result);
    }

    /**
     * 转换为 OpenAI 格式
     */
    public function toOpenAIFormat(): array
    {
        $request = $this->toArray();

        // OpenAI 格式将 system 提示放在 messages 数组的开头
        if ($this->systemPrompt !== null) {
            array_unshift($request['messages'], [
                'role' => 'system',
                'content' => $this->systemPrompt,
            ]);
            unset($request['system']);
        }

        return $request;
    }

    /**
     * 转换为 Anthropic 格式
     */
    public function toAnthropicFormat(): array
    {
        $request = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens ?? 4096,
            'messages' => $this->messages,
        ];

        // Anthropic 格式使用独立的 system 字段
        if ($this->systemPrompt !== null) {
            $request['system'] = $this->systemPrompt;
        }
        if ($this->temperature !== null) {
            $request['temperature'] = $this->temperature;
        }
        if ($this->topP !== null) {
            $request['top_p'] = $this->topP;
        }
        if ($this->stop !== null) {
            $request['stop_sequences'] = $this->stop;
        }
        if ($this->stream) {
            $request['stream'] = true;
        }
        if ($this->tools !== null) {
            $request['tools'] = $this->tools;
        }
        if ($this->toolChoice !== null) {
            $request['tool_choice'] = $this->toolChoice;
        }

        return $request;
    }

    /**
     * 获取消息数量
     */
    public function getMessageCount(): int
    {
        return count($this->messages);
    }

    /**
     * 是否包含工具定义
     */
    public function hasTools(): bool
    {
        return $this->tools !== null && count($this->tools) > 0;
    }
}
