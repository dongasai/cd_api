<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\Driver\Concerns\Convertible;
use App\Services\Protocol\Driver\Concerns\JsonSerializiable;
use App\Services\Protocol\Driver\Concerns\Validatable;
use App\Services\Shared\DTO\Request as SharedRequest;

/**
 * OpenAI Chat Completions API 请求结构体
 *
 * @see https://platform.openai.com/docs/api-reference/chat/create
 */
class ChatCompletionRequest implements ProtocolRequest
{
    use Convertible;
    use JsonSerializiable;
    use Validatable;

    /**
     * @param  string  $model  模型名称（必需）
     * @param  Message[]  $messages  消息列表（必需）
     * @param  float|null  $temperature  采样温度 (0-2)
     * @param  float|null  $top_p  核采样 (0-1)
     * @param  int|null  $n  生成数量
     * @param  bool|null  $stream  是否流式
     * @param  array|null  $stop  停止序列
     * @param  int|null  $max_tokens  最大输出 token
     * @param  int|null  $max_completion_tokens  最大完成 token
     * @param  float|null  $presence_penalty  存在惩罚 (-2 to 2)
     * @param  float|null  $frequency_penalty  频率惩罚 (-2 to 2)
     * @param  array|null  $logit_bias  token 偏置
     * @param  string|null  $logprobs  是否返回 logprobs
     * @param  int|null  $top_logprobs  返回的 top logprobs 数量
     * @param  string|null  $user  用户标识
     * @param  Tool[]|null  $tools  工具列表
     * @param  mixed  $tool_choice  工具选择策略
     * @param  bool|null  $parallel_tool_calls  是否并行工具调用
     * @param  mixed  $response_format  响应格式
     * @param  mixed  $seed  随机种子
     * @param  array  $additionalParams  额外参数
     */
    public function __construct(
        public string $model = '',
        public array $messages = [],
        public ?float $temperature = null,
        public ?float $top_p = null,
        public ?int $n = null,
        public ?bool $stream = null,
        public ?array $stop = null,
        public ?int $max_tokens = null,
        public ?int $max_completion_tokens = null,
        public ?float $presence_penalty = null,
        public ?float $frequency_penalty = null,
        public ?array $logit_bias = null,
        public ?string $logprobs = null,
        public ?int $top_logprobs = null,
        public ?string $user = null,
        public ?array $tools = null,
        public mixed $tool_choice = null,
        public ?bool $parallel_tool_calls = null,
        public mixed $response_format = null,
        public mixed $seed = null,
        public array $additionalParams = [],
        // Body 透传：原始请求体字符串
        public ?string $rawBodyString = null,
    ) {}

    /**
     * 验证规则（符合 OpenAI API 规范）
     */
    public function validationRules(): array
    {
        return [
            'model' => 'required|string',
            'messages' => 'required|array|min:1',
            'temperature' => 'nullable|numeric|between:0,2',
            'top_p' => 'nullable|numeric|between:0,1',
            'n' => 'nullable|integer|min:1|max:10',
            'stream' => 'nullable|boolean',
            'stop' => 'nullable|array',
            'max_tokens' => 'nullable|integer|min:1',
            'max_completion_tokens' => 'nullable|integer|min:1',
            'presence_penalty' => 'nullable|numeric|between:-2,2',
            'frequency_penalty' => 'nullable|numeric|between:-2,2',
            'logit_bias' => 'nullable|array',
            'logprobs' => 'nullable|string|in:true,false',
            'top_logprobs' => 'nullable|integer|min:0|max:20',
            'user' => 'nullable|string',
            'tools' => 'nullable|array',
            'tool_choice' => 'nullable',
            'parallel_tool_calls' => 'nullable|boolean',
            'response_format' => 'nullable',
            'seed' => 'nullable|integer',
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
            'model', 'messages', 'temperature', 'top_p', 'n', 'stream', 'stop',
            'max_tokens', 'max_completion_tokens', 'presence_penalty', 'frequency_penalty',
            'logit_bias', 'logprobs', 'top_logprobs', 'user', 'tools', 'tool_choice',
            'parallel_tool_calls', 'response_format', 'seed',
        ];

        $additionalParams = array_diff_key($data, array_flip($knownKeys));

        return new self(
            model: $data['model'] ?? '',
            messages: $messages,
            temperature: $data['temperature'] ?? null,
            top_p: $data['top_p'] ?? null,
            n: $data['n'] ?? null,
            stream: $data['stream'] ?? null,
            stop: $data['stop'] ?? null,
            max_tokens: $data['max_tokens'] ?? null,
            max_completion_tokens: $data['max_completion_tokens'] ?? null,
            presence_penalty: $data['presence_penalty'] ?? null,
            frequency_penalty: $data['frequency_penalty'] ?? null,
            logit_bias: $data['logit_bias'] ?? null,
            logprobs: $data['logprobs'] ?? null,
            top_logprobs: $data['top_logprobs'] ?? null,
            user: $data['user'] ?? null,
            tools: $tools,
            tool_choice: $data['tool_choice'] ?? null,
            parallel_tool_calls: $data['parallel_tool_calls'] ?? null,
            response_format: $data['response_format'] ?? null,
            seed: $data['seed'] ?? null,
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
        ];

        // 可选参数
        if ($this->temperature !== null) {
            $result['temperature'] = $this->temperature;
        }
        if ($this->top_p !== null) {
            $result['top_p'] = $this->top_p;
        }
        if ($this->n !== null) {
            $result['n'] = $this->n;
        }
        if ($this->stream !== null) {
            $result['stream'] = $this->stream;
        }
        if ($this->stop !== null) {
            $result['stop'] = $this->stop;
        }
        if ($this->max_tokens !== null) {
            $result['max_tokens'] = $this->max_tokens;
        }
        if ($this->max_completion_tokens !== null) {
            $result['max_completion_tokens'] = $this->max_completion_tokens;
        }
        if ($this->presence_penalty !== null) {
            $result['presence_penalty'] = $this->presence_penalty;
        }
        if ($this->frequency_penalty !== null) {
            $result['frequency_penalty'] = $this->frequency_penalty;
        }
        if ($this->logit_bias !== null) {
            $result['logit_bias'] = $this->logit_bias;
        }
        if ($this->logprobs !== null) {
            $result['logprobs'] = $this->logprobs;
        }
        if ($this->top_logprobs !== null) {
            $result['top_logprobs'] = $this->top_logprobs;
        }
        if ($this->user !== null) {
            $result['user'] = $this->user;
        }
        if ($this->tools !== null) {
            $result['tools'] = array_map(fn (Tool $tool) => $tool->toArray(), $this->tools);
        }
        if ($this->tool_choice !== null) {
            $result['tool_choice'] = $this->tool_choice;
        }
        if ($this->parallel_tool_calls !== null) {
            $result['parallel_tool_calls'] = $this->parallel_tool_calls;
        }
        if ($this->response_format !== null) {
            $result['response_format'] = $this->response_format;
        }
        if ($this->seed !== null) {
            $result['seed'] = $this->seed;
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

        // 提取 system 消息
        $system = null;
        $filteredMessages = [];
        foreach ($sharedMessages as $msg) {
            if ($msg->role->value === 'system') {
                $system = $msg->content;
            } else {
                $filteredMessages[] = $msg;
            }
        }

        return new SharedRequest(
            model: $this->model,
            messages: $filteredMessages,
            maxTokens: $this->max_tokens ?? $this->max_completion_tokens,
            temperature: $this->temperature,
            topP: $this->top_p,
            stream: $this->stream ?? false,
            stopSequences: $this->stop,
            system: $system,
            tools: $this->tools ? array_map(fn (Tool $t) => $t->toArray(), $this->tools) : null,
            toolChoice: $this->tool_choice,
            user: $this->user,
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

        // 如果有 system 字段，添加到 messages 开头
        if ($dto->system !== null) {
            array_unshift($messages, new Message(
                role: 'system',
                content: is_string($dto->system) ? $dto->system : json_encode($dto->system),
            ));
        }

        // 转换 tools 数组为 Tool 对象
        $tools = null;
        if ($dto->tools !== null) {
            $tools = [];
            foreach ($dto->tools as $tool) {
                $tools[] = Tool::fromArray($tool);
            }
        }

        return new self(
            model: $dto->model,
            messages: $messages,
            max_tokens: $dto->maxTokens,
            temperature: $dto->temperature,
            top_p: $dto->topP,
            stream: $dto->stream,
            stop: $dto->stopSequences,
            tools: $tools,
            tool_choice: $dto->toolChoice,
            user: $dto->user,
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
                        fn ($part) => ! ($part instanceof ContentPart && $part->type === 'thinking')
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
