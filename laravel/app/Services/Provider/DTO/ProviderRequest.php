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
        $systemContent = null;
        $filteredMessages = [];

        foreach ($this->messages as $message) {
            if (isset($message['role']) && $message['role'] === 'system') {
                $content = $message['content'] ?? '';
                if (is_array($content)) {
                    $text = '';
                    foreach ($content as $block) {
                        if (is_string($block)) {
                            $text .= $block;
                        } elseif (isset($block['text'])) {
                            $text .= $block['text'];
                        }
                    }
                    $content = $text;
                }
                $systemContent = ($systemContent ? $systemContent."\n\n" : '').$content;
            } else {
                $content = $message['content'] ?? '';
                if (is_array($content)) {
                    $filteredContent = [];
                    foreach ($content as $block) {
                        $type = $block['type'] ?? 'text';
                        if ($type !== 'thinking') {
                            $filteredContent[] = $block;
                        }
                    }
                    $message['content'] = $filteredContent;
                }
                $filteredMessages[] = $message;
            }
        }

        if ($this->systemPrompt !== null) {
            if (is_array($this->systemPrompt)) {
                $text = '';
                foreach ($this->systemPrompt as $block) {
                    if (is_string($block)) {
                        $text .= $block;
                    } elseif (isset($block['text'])) {
                        $text .= $block['text'];
                    }
                }
                $systemContent = $text;
            } else {
                $systemContent = $this->systemPrompt;
            }
        }

        // 只包含 Anthropic API 支持的参数，过滤掉不支持的参数
        // 不支持的参数: reasoning_effort, stream_options, frequency_penalty, presence_penalty, logit_bias, n 等
        // 注意: max_tokens 是必需参数，但如果客户端没有提供，让上游 API 自己决定默认值
        $request = [
            'model' => $this->model,
            'messages' => $filteredMessages,
        ];

        // max_tokens 对于 Anthropic API 是必需的，但如果为 null 则不设置
        // 让上游 API 使用其默认值
        if ($this->maxTokens !== null) {
            $request['max_tokens'] = $this->maxTokens;
        }

        if ($systemContent !== null && $systemContent !== '') {
            $request['system'] = $systemContent;
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
}
