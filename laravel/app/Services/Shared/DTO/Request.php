<?php

namespace App\Services\Shared\DTO;

/**
 * 统一请求 DTO
 *
 * 纯数据容器，不包含业务逻辑
 */
class Request
{
    public function __construct(
        public string $model,
        public array $messages, // Message[]
        public ?int $maxTokens = null,
        public ?float $temperature = null,
        public ?float $topP = null,
        public ?int $topK = null,
        public ?bool $stream = false,
        public ?array $stopSequences = null,
        public string|array|null $system = null,
        public ?array $tools = null,
        public $toolChoice = null,
        public ?array $thinking = null,
        public ?array $metadata = null,
        public ?string $user = null,
        public array $additionalParams = [],
        public ?array $rawRequest = null,
        // Body 透传：原始请求体字符串
        public ?string $rawBodyString = null,
        public ?string $queryString = null,
    ) {}

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
     * 估算 Token 数量 (粗略估算)
     */
    public function estimateTokens(): int
    {
        $text = '';

        // 处理系统提示
        if (is_string($this->system)) {
            $text = $this->system;
        } elseif (is_array($this->system)) {
            foreach ($this->system as $block) {
                if (is_string($block)) {
                    $text .= $block;
                } elseif (isset($block['text'])) {
                    $text .= $block['text'];
                }
            }
        }

        foreach ($this->messages as $message) {
            $text .= ' '.$message->getTextContent();
        }

        // 粗略估算: 4字符约等于1个token
        return (int) ceil(strlen($text) / 4);
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'messages' => array_map(fn ($m) => $m->toArray(), $this->messages),
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'top_p' => $this->topP,
            'top_k' => $this->topK,
            'stream' => $this->stream,
            'stop_sequences' => $this->stopSequences,
            'system' => $this->system,
            'tools' => $this->tools,
            'tool_choice' => $this->toolChoice,
            'thinking' => $this->thinking,
            'metadata' => $this->metadata,
            'user' => $this->user,
            'additional_params' => $this->additionalParams,
        ];
    }

    /**
     * 转换为 OpenAI 格式数组
     */
    public function toOpenAI(): array
    {
        $result = [
            'model' => $this->model,
            'messages' => array_map(fn ($m) => $m->toOpenAI(), $this->messages),
        ];

        if ($this->maxTokens !== null) {
            $result['max_tokens'] = $this->maxTokens;
        }
        if ($this->temperature !== null) {
            $result['temperature'] = $this->temperature;
        }
        if ($this->topP !== null) {
            $result['top_p'] = $this->topP;
        }
        if ($this->topK !== null) {
            $result['top_k'] = $this->topK;
        }
        if ($this->stream) {
            $result['stream'] = true;
        }
        if ($this->stopSequences !== null) {
            $result['stop'] = $this->stopSequences;
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
        if ($this->metadata !== null) {
            $result['metadata'] = $this->metadata;
        }

        // 处理 system 提示：OpenAI 将 system 作为消息的第一条
        if ($this->system !== null) {
            $systemContent = $this->system;
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
                array_unshift($result['messages'], [
                    'role' => 'system',
                    'content' => $systemContent,
                ]);
            }
        }

        return array_merge($result, $this->additionalParams);
    }

    /**
     * 转换为 Anthropic 格式数组
     */
    public function toAnthropic(bool $includeCacheControl = true, bool $filterThinking = true, bool $filterRequestThinking = false): array
    {
        $messages = [];

        foreach ($this->messages as $message) {
            $messages[] = $message->toAnthropic($includeCacheControl, $filterThinking, $filterRequestThinking);
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

        // 构建标准字段
        $result = [
            'model' => $this->model,
            'messages' => $filteredMessages,
        ];

        // max_tokens 对于 Anthropic API 是必需的
        if ($this->maxTokens !== null) {
            $result['max_tokens'] = $this->maxTokens;
        }

        // system 字段：保持原始格式（数组或字符串），支持 cache_control
        if ($this->system !== null) {
            $result['system'] = $this->system;
        }
        if ($this->temperature !== null) {
            $result['temperature'] = $this->temperature;
        }
        if ($this->topP !== null) {
            $result['top_p'] = $this->topP;
        }
        if ($this->topK !== null) {
            $result['top_k'] = $this->topK;
        }
        if ($this->stopSequences !== null) {
            $result['stop_sequences'] = $this->stopSequences;
        }
        if ($this->stream) {
            $result['stream'] = true;
        }
        if ($this->tools !== null) {
            $result['tools'] = $this->tools;
        }
        if ($this->toolChoice !== null) {
            $result['tool_choice'] = $this->toolChoice;
        }
        if ($this->metadata !== null) {
            $result['metadata'] = $this->metadata;
        }
        if ($this->thinking !== null) {
            $result['thinking'] = $this->thinking;
        }

        return array_merge($result, $this->additionalParams);
    }

    /**
     * 获取 OpenAI 格式的请求体
     */
    public function toOpenAIFormat(): array
    {
        return $this->toOpenAI();
    }

    /**
     * 获取 Anthropic 格式的请求体
     */
    public function toAnthropicFormat(): array
    {
        return $this->toAnthropic();
    }

    /**
     * 从数组创建实例
     */
    public static function fromArray(array $data): self
    {
        $messages = [];
        foreach ($data['messages'] ?? [] as $msg) {
            if ($msg instanceof Message) {
                $messages[] = $msg;
            } elseif (is_array($msg)) {
                $messages[] = new Message(
                    role: \App\Services\Shared\Enums\MessageRole::from($msg['role'] ?? 'user'),
                    content: $msg['content'] ?? null,
                    toolCalls: $msg['tool_calls'] ?? null,
                    toolCallId: $msg['tool_call_id'] ?? null,
                    contentBlocks: $msg['content_blocks'] ?? null,
                );
            }
        }

        return new self(
            model: $data['model'] ?? '',
            messages: $messages,
            maxTokens: $data['max_tokens'] ?? null,
            temperature: $data['temperature'] ?? null,
            topP: $data['top_p'] ?? null,
            topK: $data['top_k'] ?? null,
            stream: $data['stream'] ?? false,
            stopSequences: $data['stop_sequences'] ?? $data['stop'] ?? null,
            system: $data['system'] ?? null,
            tools: $data['tools'] ?? null,
            toolChoice: $data['tool_choice'] ?? null,
            thinking: $data['thinking'] ?? null,
            metadata: $data['metadata'] ?? null,
            user: $data['user'] ?? null,
            additionalParams: $data['additional_params'] ?? [],
            rawRequest: $data['rawRequest'] ?? $data ?? null,
            rawBodyString: $data['rawBodyString'] ?? null,
            queryString: $data['queryString'] ?? null,
        );
    }
}
