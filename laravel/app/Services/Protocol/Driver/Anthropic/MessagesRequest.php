<?php

namespace App\Services\Protocol\Driver\Anthropic;

use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\Driver\Concerns\Convertible;
use App\Services\Protocol\Driver\Concerns\JsonSerializiable;
use App\Services\Protocol\Driver\Concerns\Validatable;
use App\Services\Shared\DTO\Request as SharedRequest;

/**
 * Anthropic Messages API 请求结构体
 *
 * @see https://docs.anthropic.com/en/api/messages
 */
class MessagesRequest implements ProtocolRequest
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
     * @param  string|null  $container  容器标识符
     * @param  string|null  $inference_geo  推理地理位置
     * @param  string|null  $service_tier  服务层级
     * @param  array|null  $output_config  输出配置
     * @param  array|null  $cache_control  缓存控制
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
        public ?string $container = null,
        public ?string $inference_geo = null,
        public ?string $service_tier = null,
        public ?array $output_config = null,
        public ?array $cache_control = null,
        public array $additionalParams = [],
        // Body 透传：原始请求体字符串
        public ?string $rawBodyString = null,
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
            'container' => 'nullable|string',
            'inference_geo' => 'nullable|string',
            'service_tier' => 'nullable|string|in:priority,standard,batch',
            'output_config' => 'nullable|array',
            'cache_control' => 'nullable|array',
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
            'container', 'inference_geo', 'service_tier', 'output_config', 'cache_control',
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
            container: $data['container'] ?? null,
            inference_geo: $data['inference_geo'] ?? null,
            service_tier: $data['service_tier'] ?? null,
            output_config: $data['output_config'] ?? null,
            cache_control: $data['cache_control'] ?? null,
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
        if ($this->container !== null) {
            $result['container'] = $this->container;
        }
        if ($this->inference_geo !== null) {
            $result['inference_geo'] = $this->inference_geo;
        }
        if ($this->service_tier !== null) {
            $result['service_tier'] = $this->service_tier;
        }
        if ($this->output_config !== null) {
            $result['output_config'] = $this->output_config;
        }
        if ($this->cache_control !== null) {
            $result['cache_control'] = $this->cache_control;
        }

        // Body 透传：如果设置了原始请求体，返回特殊格式
        if ($this->rawBodyString !== null) {
            return ['rawBodyString' => $this->rawBodyString];
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

        // 转换 tools 为 SharedDTO\Tool 对象数组
        $sharedTools = null;
        if ($this->tools !== null) {
            $sharedTools = array_map(fn (Tool $t) => $t->toSharedDTO(), $this->tools);
        }

        $dto = new SharedRequest;
        $dto->model = $this->model;
        $dto->messages = $sharedMessages;
        $dto->maxTokens = $this->max_tokens;
        $dto->temperature = $this->temperature;
        $dto->topP = $this->top_p;
        $dto->topK = $this->top_k;
        $dto->stream = $this->stream ?? false;
        $dto->stopSequences = $this->stop_sequences;
        $dto->system = $this->system;
        $dto->tools = $sharedTools;
        $dto->toolChoice = $this->tool_choice;
        $dto->thinking = $this->thinking;
        $dto->metadata = $this->metadata;
        $dto->rawRequest = $this->toArray();

        return $dto;
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

        // 转换 tools（SharedDTO\Tool 对象数组）为 Anthropic Tool 对象
        $tools = null;
        if ($dto->tools !== null) {
            $tools = [];
            foreach ($dto->tools as $tool) {
                $tools[] = Tool::fromSharedDTO($tool);
            }
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
            tools: $tools,
            tool_choice: $dto->toolChoice,
            thinking: $dto->thinking,
            metadata: $dto->metadata,
        );
    }

    /**
     * 获取模型名称
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * 设置模型名称
     */
    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * 是否流式请求
     */
    public function isStream(): bool
    {
        return $this->stream ?? false;
    }

    /**
     * 设置流式标志
     */
    public function setStream(bool $stream): static
    {
        $this->stream = $stream;

        return $this;
    }

    /**
     * 过滤请求中的 thinking 内容块
     *
     * @param  bool  $filter  是否过滤
     */
    public function filterRequestThinking(bool $filter = true): static
    {
        if (! $filter) {
            return $this;
        }

        // 过滤每条消息中的 thinking 内容块
        foreach ($this->messages as $message) {
            if (is_array($message->content)) {
                $message->content = array_values(
                    array_filter(
                        $message->content,
                        fn ($block) => ! ($block instanceof ContentBlock && $block->type === 'thinking')
                    )
                );
            }
        }

        return $this;
    }

    /**
     * 设置原始请求体（用于 body_passthrough）
     */
    public function setRawBodyString(string $rawBody): static
    {
        $this->rawBodyString = $rawBody;

        return $this;
    }
}
