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
            // 跳过 system 消息（Anthropic API 不允许 messages 中有 system 角色）
            if ($message->role === \App\Services\Shared\Enums\MessageRole::System) {
                continue;
            }
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

        // max_tokens 对于 Anthropic API 是必需的，如果没有提供则使用默认值
        $result['max_tokens'] = $this->maxTokens ?? 4096;

        // system 字段：转换为数组格式（阿里云 Coding 要求）
        if ($this->system !== null) {
            // 如果是字符串，转换为数组格式
            if (is_string($this->system)) {
                $result['system'] = [
                    [
                        'type' => 'text',
                        'text' => $this->system,
                    ],
                ];
            } else {
                // 已经是数组格式，保持不变
                $result['system'] = $this->system;
            }
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

        // 合并 additionalParams，但过滤掉 OpenAI 特有的字段
        $openaiSpecificFields = ['stream_options', 'response_format', 'seed', 'logprobs', 'top_logprobs', 'n'];
        $filteredAdditionalParams = array_filter(
            $this->additionalParams,
            fn ($key) => !in_array($key, $openaiSpecificFields),
            ARRAY_FILTER_USE_KEY
        );

        return array_merge($result, $filteredAdditionalParams);
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
        $systemContent = null;

        foreach ($data['messages'] ?? [] as $msg) {
            if ($msg instanceof Message) {
                $messages[] = $msg;
            } elseif (is_array($msg)) {
                // 提取 system 消息（OpenAI 格式中 system 消息在 messages 数组中）
                if (($msg['role'] ?? '') === 'system') {
                    // 提取 system 内容
                    if (is_string($msg['content'] ?? null)) {
                        $systemContent = $msg['content'];
                    } elseif (is_array($msg['content'])) {
                        // 多模态 system 消息，提取文本
                        $texts = [];
                        foreach ($msg['content'] as $block) {
                            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                                $texts[] = $block['text'] ?? '';
                            }
                        }
                        $systemContent = implode("\n", $texts);
                    }

                    // 不添加到 messages 数组，跳过 system 消息
                    continue;
                }

                // 处理 content 字段：可能是字符串或数组（多模态）
                $content = null;
                $contentBlocks = null;

                if (isset($msg['content'])) {
                    if (is_array($msg['content'])) {
                        // 多模态内容：转换为 ContentBlock 数组
                        $contentBlocks = [];
                        foreach ($msg['content'] as $block) {
                            if (is_array($block)) {
                                $contentBlocks[] = \App\Services\Shared\DTO\ContentBlock::fromOpenAI($block);
                            }
                        }
                    } else {
                        // 纯文本内容
                        $content = (string) $msg['content'];
                    }
                }

                // 优先使用已有的 content_blocks
                if (isset($msg['content_blocks'])) {
                    $contentBlocks = $msg['content_blocks'];
                }

                $messages[] = new Message(
                    role: \App\Services\Shared\Enums\MessageRole::from($msg['role'] ?? 'user'),
                    content: $content,
                    toolCalls: $msg['tool_calls'] ?? null,
                    toolCallId: $msg['tool_call_id'] ?? null,
                    contentBlocks: $contentBlocks,
                );
            }
        }

        // 优先使用独立 system 字段（如果存在），否则使用提取的 system 消息
        $systemField = $data['system'] ?? $systemContent;

        // 收集未识别的字段到 additionalParams
        $knownFields = [
            'model', 'messages', 'max_tokens', 'temperature', 'top_p', 'top_k',
            'stream', 'stop_sequences', 'stop', 'system', 'tools', 'tool_choice',
            'thinking', 'metadata', 'user', 'additional_params', 'rawRequest',
            'rawBodyString', 'queryString', 'content_blocks',
        ];
        $additionalParams = $data['additional_params'] ?? [];
        foreach ($data as $key => $value) {
            if (!in_array($key, $knownFields) && !isset($additionalParams[$key])) {
                $additionalParams[$key] = $value;
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
            system: $systemField,
            tools: $data['tools'] ?? null,
            toolChoice: $data['tool_choice'] ?? null,
            thinking: $data['thinking'] ?? null,
            metadata: $data['metadata'] ?? null,
            user: $data['user'] ?? null,
            additionalParams: $additionalParams,
            rawRequest: $data['rawRequest'] ?? $data ?? null,
            rawBodyString: $data['rawBodyString'] ?? null,
            queryString: $data['queryString'] ?? null,
        );
    }
}
