<?php

namespace App\Services\Provider\DTO;

/**
 * 供应商请求数据传输对象
 *
 * 用于封装发送给 AI 供应商的请求数据
 */
class ProviderRequest
{
    public function __construct(
        public string $model = '',
        public array $messages = [],
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public bool $stream = false,
        public array $parameters = [],
        public string|array|null $systemPrompt = null,
        public ?float $topP = null,
        public ?array $stop = null,
        public ?array $tools = null,
        public mixed $toolChoice = null,
        public ?string $user = null,
    ) {}

    /**
     * 从数组创建实例
     *
     * @param  array  $data  请求数据
     */
    public static function fromArray(array $data): self
    {
        // 已知的参数键名
        $knownKeys = [
            'model', 'messages', 'temperature', 'max_tokens', 'maxTokens',
            'stream', 'parameters', 'system', 'systemPrompt', 'top_p', 'topP',
            'top_k', 'topK', 'stop', 'stop_sequences', 'tools', 'tool_choice',
            'toolChoice', 'user', 'metadata',
        ];

        // 收集未知参数
        $unknownParams = [];
        foreach ($data as $key => $value) {
            if (! in_array($key, $knownKeys)) {
                $unknownParams[$key] = $value;
            }
        }

        return new self(
            model: $data['model'] ?? '',
            messages: $data['messages'] ?? [],
            temperature: $data['temperature'] ?? null,
            maxTokens: $data['max_tokens'] ?? $data['maxTokens'] ?? null,
            stream: $data['stream'] ?? false,
            parameters: array_merge($data['parameters'] ?? [], $unknownParams),
            systemPrompt: $data['system'] ?? $data['systemPrompt'] ?? null,
            topP: $data['top_p'] ?? $data['topP'] ?? null,
            stop: $data['stop'] ?? $data['stop_sequences'] ?? null,
            tools: $data['tools'] ?? null,
            toolChoice: $data['tool_choice'] ?? $data['toolChoice'] ?? null,
            user: $data['user'] ?? ($data['metadata']['user_id'] ?? null),
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

        if ($this->systemPrompt !== null) {
            $systemContent = $this->systemPrompt;
            if (is_array($systemContent)) {
                $text = '';
                foreach ($systemContent as $block) {
                    if (is_string($block)) {
                        $text .= $block;
                    } elseif (isset($block['text'])) {
                        $text .= $block['text'];
                    }
                }
                $systemContent = $text;
            }
            if ($systemContent !== '') {
                array_unshift($request['messages'], [
                    'role' => 'system',
                    'content' => $systemContent,
                ]);
            }
            unset($request['system']);
        }

        return $request;
    }

    /**
     * 转换为 Anthropic 格式
     */
    public function toAnthropicFormat(): array
    {
        $messages = [];

        foreach ($this->messages as $message) {
            $role = $message['role'] ?? 'user';

            // system 消息应该放到 system 字段，不处理
            if ($role === 'system') {
                continue;
            }

            // tool 消息需要转换为 user 消息中的 tool_result 内容块
            if ($role === 'tool') {
                $toolCallId = $message['tool_call_id'] ?? null;
                $toolContent = $message['content'] ?? '';

                // Anthropic 格式：tool_result 作为 user 消息的内容块
                $messages[] = [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'tool_result',
                            'tool_use_id' => $toolCallId,
                            'content' => $toolContent,
                        ],
                    ],
                ];

                continue;
            }

            $messages[] = $message;
        }

        // 合并连续的 user 消息（Anthropic 要求 tool_result 和其他内容在同一个 user 消息中）
        $filteredMessages = [];
        $previousUserMessage = null;

        foreach ($messages as $message) {
            if ($message['role'] === 'user') {
                if ($previousUserMessage !== null) {
                    // 合并到前一个 user 消息
                    $content = $message['content'] ?? [];
                    if (is_array($content)) {
                        $previousContent = $previousUserMessage['content'] ?? [];
                        if (is_array($previousContent)) {
                            $previousContent = array_merge($previousContent, $content);
                        } else {
                            $previousContent = $content;
                        }
                        $previousUserMessage['content'] = $previousContent;
                    }
                } else {
                    // 第一个 user 消息，直接添加
                    $previousUserMessage = $message;
                }
            } else {
                // 非 user 消息，先保存之前的 user 消息，然后添加当前消息
                if ($previousUserMessage !== null) {
                    $filteredMessages[] = $previousUserMessage;
                    $previousUserMessage = null;
                }
                $filteredMessages[] = $message;
            }
        }

        // 添加最后一个 user 消息（如果有）
        if ($previousUserMessage !== null) {
            $filteredMessages[] = $previousUserMessage;
        }

        // 构建基础请求，保留 parameters 中的未知参数（如 thinking, output_config, beta 等）
        $request = array_merge($this->parameters, [
            'model' => $this->model,
            'messages' => $filteredMessages,
        ]);

        // max_tokens 对于 Anthropic API 是必需的，但如果为 null 则不设置
        if ($this->maxTokens !== null) {
            $request['max_tokens'] = $this->maxTokens;
        }

        // system 字段：保持原始格式（数组或字符串），支持 cache_control
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
            // 直接传递 tools，不做修改，保持原始格式
            $request['tools'] = $this->tools;
        }
        if ($this->toolChoice !== null) {
            $request['tool_choice'] = $this->toolChoice;
        }
        if ($this->user !== null) {
            $request['metadata']['user_id'] = $this->user;
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

    /**
     * 修复工具定义以兼容 Anthropic API
     *
     * JSON Schema 规范要求 additionalProperties 应该是 boolean 或 object
     * 但有些客户端可能传入空数组 []，需要修复
     */
    private function fixToolsForAnthropic(array $tools): array
    {
        return array_map(function ($tool) {
            if (isset($tool['input_schema'])) {
                $tool['input_schema'] = $this->fixInputSchema($tool['input_schema']);
            }

            return $tool;
        }, $tools);
    }

    /**
     * 修复 input_schema 中无效的 additionalProperties
     */
    private function fixInputSchema(array $schema): array
    {
        if (isset($schema['additionalProperties'])) {
            $ap = $schema['additionalProperties'];
            // 如果是空数组，转换为 false
            if (is_array($ap) && empty($ap)) {
                $schema['additionalProperties'] = false;
            }
        }

        // 递归处理嵌套的 properties
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $key => $property) {
                if (is_array($property)) {
                    $schema['properties'][$key] = $this->fixInputSchema($property);
                }
            }
        }

        // 处理 items（数组类型的元素）
        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->fixInputSchema($schema['items']);
        }

        return $schema;
    }
}
