<?php

namespace App\Services\Shared\DTO;

/**
 * 统一请求 DTO
 *
 * 纯数据容器，不包含业务逻辑
 */
class Request
{
    /**
     * 模型名称
     */
    public string $model;

    /**
     * 消息列表
     *
     * @var array Message[]
     */
    public array $messages;

    /**
     * 最大 Token 数量
     */
    public ?int $maxTokens = null;

    /**
     * 温度参数
     */
    public ?float $temperature = null;

    /**
     * Top P 参数
     */
    public ?float $topP = null;

    /**
     * Top K 参数
     */
    public ?int $topK = null;

    /**
     * 是否流式输出
     */
    public ?bool $stream = false;

    /**
     * 停止序列
     */
    public ?array $stopSequences = null;

    /**
     * 系统提示
     */
    public string|array|null $system = null;

    /**
     * 工具列表
     *
     * @var array|null Tool[]
     */
    public ?array $tools = null;

    /**
     * 工具选择
     *
     * @var mixed
     */
    public $toolChoice = null;

    /**
     * 思考配置
     */
    public ?array $thinking = null;

    /**
     * 元数据
     */
    public ?array $metadata = null;

    /**
     * 用户标识
     */
    public ?string $user = null;

    /**
     * 额外参数
     */
    public array $additionalParams = [];

    /**
     * 原始请求
     */
    public ?array $rawRequest = null;

    /**
     * 原始请求体字符串（Body 透传）
     */
    public ?string $rawBodyString = null;

    /**
     * 查询字符串
     */
    public ?string $queryString = null;

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
                                $contentBlocks[] = ContentBlock::fromArray($block);
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

                $message = new Message;
                $message->role = \App\Services\Shared\Enums\MessageRole::from($msg['role'] ?? 'user');
                $message->content = $content;
                $message->toolCalls = $msg['tool_calls'] ?? null;
                $message->toolCallId = $msg['tool_call_id'] ?? null;
                $message->contentBlocks = $contentBlocks;
                $messages[] = $message;
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
            if (! in_array($key, $knownFields) && ! isset($additionalParams[$key])) {
                $additionalParams[$key] = $value;
            }
        }

        $request = new self;
        $request->model = $data['model'] ?? '';
        $request->messages = $messages;
        $request->maxTokens = $data['max_tokens'] ?? null;
        $request->temperature = $data['temperature'] ?? null;
        $request->topP = $data['top_p'] ?? null;
        $request->topK = $data['top_k'] ?? null;
        $request->stream = $data['stream'] ?? false;
        $request->stopSequences = $data['stop_sequences'] ?? $data['stop'] ?? null;
        $request->system = $systemField;
        $request->tools = $data['tools'] ?? null;
        $request->toolChoice = $data['tool_choice'] ?? null;
        $request->thinking = $data['thinking'] ?? null;
        $request->metadata = $data['metadata'] ?? null;
        $request->user = $data['user'] ?? null;
        $request->additionalParams = $additionalParams;
        $request->rawRequest = $data['rawRequest'] ?? $data ?? null;
        $request->rawBodyString = $data['rawBodyString'] ?? null;
        $request->queryString = $data['queryString'] ?? null;

        return $request;
    }
}
