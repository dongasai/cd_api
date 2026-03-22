<?php

namespace App\Services\Protocol\Driver\Anthropic;

use App\Services\Protocol\Driver\Concerns\Convertible;
use App\Services\Protocol\Driver\Concerns\JsonSerializiable;
use App\Services\Protocol\Driver\Concerns\Validatable;
use App\Services\Shared\DTO\Request as SharedRequest;

/**
 * Anthropic Messages API 请求结构体
 *
 * @see https://docs.anthropic.com/en/api/messages
 */
class MessagesRequest
{
    use Convertible;
    use JsonSerializiable;
    use Validatable;

    /**
     * @param  string  $model  模型名称（必需）
     * @param  Message[]  $messages  消息列表（必需）
     * @param  int  $max_tokens  最大输出 token（必需）
     * @param  string|array|null  $system  系统提示（独立字段）
     * @param  float|null  $temperature  采样温度 (0-1)
     * @param  float|null  $top_p  核采样 (0-1)
     * @param  int|null  $top_k  Top-K 采样
     * @param  array|null  $stop_sequences  停止序列
     * @param  bool|null  $stream  是否流式
     * @param  Tool[]|null  $tools  工具列表
     * @param  mixed  $tool_choice  工具选择策略
     * @param  array|null  $metadata  元数据
     * @param  array|null  $thinking  推理参数
     * @param  array  $additionalParams  额外参数
     */
    public function __construct(
        public string $model = '',
        public array $messages = [],
        public int $max_tokens = 4096,
        public string|array|null $system = null,
        public ?float $temperature = null,
        public ?float $top_p = null,
        public ?int $top_k = null,
        public ?array $stop_sequences = null,
        public ?bool $stream = null,
        public ?array $tools = null,
        public mixed $tool_choice = null,
        public ?array $metadata = null,
        public ?array $thinking = null,
        public array $additionalParams = [],
    ) {}

    /**
     * 验证规则（符合 Anthropic API 规范）
     */
    public function validationRules(): array
    {
        return [
            'model' => 'required|string',
            'messages' => 'required|array|min:1',
            'max_tokens' => 'required|integer|min:1|max:128000',
            'system' => 'nullable',
            'temperature' => 'nullable|numeric|between:0,1',
            'top_p' => 'nullable|numeric|between:0,1',
            'top_k' => 'nullable|integer|min:0',
            'stop_sequences' => 'nullable|array',
            'stream' => 'nullable|boolean',
            'tools' => 'nullable|array',
            'tool_choice' => 'nullable',
            'metadata' => 'nullable|array',
            'thinking' => 'nullable|array',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        // 解析 messages
        $messages = [];
        foreach ($data['messages'] ?? [] as $msg) {
            if (is_array($msg)) {
                $messages[] = Message::fromArray($msg);
            }
        }

        // 解析 tools
        $tools = null;
        if (isset($data['tools']) && is_array($data['tools'])) {
            $tools = [];
            foreach ($data['tools'] as $tool) {
                $tools[] = Tool::fromArray($tool);
            }
        }

        // 收集已知参数
        $knownKeys = [
            'model', 'messages', 'max_tokens', 'system',
            'temperature', 'top_p', 'top_k', 'stop_sequences',
            'stream', 'tools', 'tool_choice', 'metadata', 'thinking',
        ];

        $additionalParams = array_diff_key($data, array_flip($knownKeys));

        return new self(
            model: $data['model'] ?? '',
            messages: $messages,
            max_tokens: $data['max_tokens'] ?? 4096,
            system: $data['system'] ?? null,
            temperature: $data['temperature'] ?? null,
            top_p: $data['top_p'] ?? null,
            top_k: $data['top_k'] ?? null,
            stop_sequences: $data['stop_sequences'] ?? null,
            stream: $data['stream'] ?? null,
            tools: $tools,
            tool_choice: $data['tool_choice'] ?? null,
            metadata: $data['metadata'] ?? null,
            thinking: $data['thinking'] ?? null,
            additionalParams: $additionalParams,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'model' => $this->model,
            'messages' => array_map(fn (Message $msg) => $msg->toArray(), $this->messages),
            'max_tokens' => $this->max_tokens,
        ];

        // 可选参数
        if ($this->system !== null) {
            $result['system'] = $this->system;
        }
        if ($this->temperature !== null) {
            $result['temperature'] = $this->temperature;
        }
        if ($this->top_p !== null) {
            $result['top_p'] = $this->top_p;
        }
        if ($this->top_k !== null) {
            $result['top_k'] = $this->top_k;
        }
        if ($this->stop_sequences !== null) {
            $result['stop_sequences'] = $this->stop_sequences;
        }
        if ($this->stream !== null) {
            $result['stream'] = $this->stream;
        }
        if ($this->tools !== null) {
            $result['tools'] = array_map(fn (Tool $tool) => $tool->toArray(), $this->tools);
        }
        if ($this->tool_choice !== null) {
            $result['tool_choice'] = $this->tool_choice;
        }
        if ($this->metadata !== null) {
            $result['metadata'] = $this->metadata;
        }
        if ($this->thinking !== null) {
            $result['thinking'] = $this->thinking;
        }

        // 合并额外参数
        return array_merge($result, $this->additionalParams);
    }

    /**
     * 转换为 Shared\DTO
     */
    public function toSharedDTO(): SharedRequest
    {
        // 转换消息
        $sharedMessages = array_map(
            fn (Message $msg) => $msg->toSharedDTO(),
            $this->messages
        );

        return new SharedRequest(
            model: $this->model,
            messages: $sharedMessages,
            maxTokens: $this->max_tokens,
            temperature: $this->temperature,
            topP: $this->top_p,
            topK: $this->top_k,
            stream: $this->stream ?? false,
            stopSequences: $this->stop_sequences,
            system: $this->system,
            tools: $this->tools ? array_map(fn (Tool $t) => $t->toArray(), $this->tools) : null,
            toolChoice: $this->tool_choice,
            thinking: $this->thinking,
            metadata: $this->metadata,
            rawRequest: $this->toArray(),
        );
    }

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(object $dto): static
    {
        // 转换消息
        $messages = [];
        foreach ($dto->messages as $msg) {
            $messages[] = Message::fromSharedDTO($msg);
        }

        return new self(
            model: $dto->model,
            messages: $messages,
            max_tokens: $dto->maxTokens ?? 4096,
            system: $dto->system,
            temperature: $dto->temperature,
            top_p: $dto->topP,
            top_k: $dto->topK,
            stream: $dto->stream,
            stop_sequences: $dto->stopSequences,
            tools: $dto->tools,
            tool_choice: $dto->toolChoice,
            thinking: $dto->thinking,
            metadata: $dto->metadata,
        );
    }
}
