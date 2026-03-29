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
     * @param  array|null  $stream_options  流式选项（如 include_usage）
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
        public ?array $stream_options = null,
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
            'stream_options' => 'nullable|array',
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
            'model', 'messages', 'temperature', 'top_p', 'n', 'stream', 'stream_options', 'stop',
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
            stream_options: $data['stream_options'] ?? null,
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
        if ($this->stream_options !== null) {
            $result['stream_options'] = $this->stream_options;
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
        if (! empty($this->tools)) {
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

        // 转换 tools 为 SharedDTO\Tool 对象数组
        $sharedTools = null;
        if ($this->tools !== null) {
            $sharedTools = array_map(fn (Tool $t) => $t->toSharedDTO(), $this->tools);
        }

        $dto = new SharedRequest;
        $dto->model = $this->model;
        $dto->messages = $filteredMessages;
        $dto->maxTokens = $this->max_tokens ?? $this->max_completion_tokens;
        $dto->temperature = $this->temperature;
        $dto->topP = $this->top_p;
        $dto->stream = $this->stream ?? false;
        $dto->streamOptions = $this->stream_options;
        $dto->stopSequences = $this->stop;
        $dto->system = $system;
        $dto->tools = $sharedTools;
        $dto->toolChoice = $this->tool_choice;
        $dto->user = $this->user;
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
            // 检查消息是否包含 tool_result 内容块
            $hasToolResult = false;
            if ($msg->contentBlocks !== null) {
                foreach ($msg->contentBlocks as $block) {
                    if ($block->type === 'tool_result') {
                        $hasToolResult = true;
                        break;
                    }
                }
            }

            // 如果包含 tool_result，需要拆分为多条消息
            if ($hasToolResult) {
                // 分离 tool_result 和其他内容块
                $toolResults = [];
                $otherBlocks = [];

                foreach ($msg->contentBlocks as $block) {
                    if ($block->type === 'tool_result') {
                        $toolResults[] = $block;
                    } else {
                        $otherBlocks[] = $block;
                    }
                }

                // 如果有其他内容块（如 text），保留为原角色的消息
                if (! empty($otherBlocks)) {
                    $userMsg = new \App\Services\Shared\DTO\Message;
                    $userMsg->role = $msg->role;
                    $userMsg->contentBlocks = $otherBlocks;
                    $messages[] = Message::fromSharedDTO($userMsg);
                }

                // 每个 tool_result 转换为独立的 tool 消息
                foreach ($toolResults as $toolResult) {
                    $messages[] = new Message(
                        role: 'tool',
                        content: $toolResult->toolResultContent ?? '',
                        toolCallId: $toolResult->toolResultId ?? '',
                    );
                }
            } else {
                // 没有 tool_result，保持原有转换逻辑
                $messages[] = Message::fromSharedDTO($msg);
            }
        }

        // 如果有 system 字段，添加到 messages 开头
        if ($dto->system !== null) {
            // 处理 system 内容
            $systemContent = null;
            if (is_string($dto->system)) {
                $systemContent = $dto->system;
            } elseif (is_array($dto->system)) {
                // 如果是数组，尝试转换为 ContentPart 数组
                $systemContent = [];
                foreach ($dto->system as $block) {
                    if (is_array($block)) {
                        // 检查是否是 content block 格式
                        if (isset($block['type'])) {
                            $contentBlock = \App\Services\Shared\DTO\ContentBlock::fromArray($block);
                            $systemContent[] = ContentPart::fromSharedDTO($contentBlock);
                        } else {
                            // 普通数组，转为 JSON 字符串
                            $systemContent = json_encode($dto->system);
                            break;
                        }
                    } else {
                        // 包含非数组元素，转为 JSON 字符串
                        $systemContent = json_encode($dto->system);
                        break;
                    }
                }
                // 如果转换后的数组为空，使用 JSON 字符串
                if (empty($systemContent)) {
                    $systemContent = json_encode($dto->system);
                }
            } else {
                $systemContent = json_encode($dto->system);
            }

            array_unshift($messages, new Message(
                role: 'system',
                content: $systemContent,
            ));
        }

        // 转换 tools（SharedDTO\Tool 对象数组）为 OpenAI Tool 对象
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
            max_tokens: $dto->maxTokens,
            temperature: $dto->temperature,
            top_p: $dto->topP,
            stream: $dto->stream,
            stream_options: $dto->streamOptions,
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
