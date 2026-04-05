<?php

namespace App\Services\Protocol\Driver\OpenAIResponses;

use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\Driver\Concerns\Convertible;
use App\Services\Protocol\Driver\Concerns\JsonSerializiable;
use App\Services\Protocol\Driver\Concerns\Validatable;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionRequest;
use App\Services\Protocol\Driver\OpenAI\Message;
use App\Services\Response\ResponseStateManager;
use App\Services\Shared\DTO\Request as SharedRequest;

/**
 * OpenAI Responses API 请求 DTO
 *
 * @see https://platform.openai.com/docs/api-reference/responses
 */
class OpenAIResponsesRequest implements ProtocolRequest
{
    use Convertible;
    use JsonSerializiable;
    use Validatable;

    /**
     * @param  string  $model  模型名称（必需）
     * @param  string|array  $input  输入内容（必需）
     * @param  string|null  $previousResponseId  上一次响应ID
     * @param  string|null  $instructions  系统指令
     * @param  int|null  $maxTokens  最大Token数
     * @param  float|null  $temperature  温度参数
     * @param  float|null  $topP  Top P
     * @param  bool|null  $stream  是否流式
     * @param  array|null  $tools  工具定义
     * @param  mixed  $toolChoice  工具选择
     * @param  array|null  $metadata  元数据
     * @param  int|null  $_apiKeyId  API Key ID（内部使用，用于状态管理）
     */
    public function __construct(
        public string $model = '',
        public string|array $input = '',
        public ?string $previousResponseId = null,
        public ?string $instructions = null,
        public ?int $maxTokens = null,
        public ?float $temperature = null,
        public ?float $topP = null,
        public ?bool $stream = false,
        public ?array $tools = null,
        public mixed $toolChoice = null,
        public ?array $metadata = null,
        public ?int $_apiKeyId = null,
    ) {}

    /**
     * 从数组创建实例
     */
    public static function fromArray(array $data): static
    {
        $request = new self;
        $request->model = $data['model'] ?? '';
        $request->input = $data['input'] ?? '';
        $request->previousResponseId = $data['previous_response_id'] ?? null;
        $request->instructions = $data['instructions'] ?? null;
        $request->maxTokens = $data['max_tokens'] ?? null;
        $request->temperature = $data['temperature'] ?? null;
        $request->topP = $data['top_p'] ?? null;
        $request->stream = $data['stream'] ?? false;
        $request->tools = $data['tools'] ?? null;
        $request->toolChoice = $data['tool_choice'] ?? null;
        $request->metadata = $data['metadata'] ?? null;
        $request->_apiKeyId = $data['_api_key_id'] ?? null;  // 提取 API Key ID

        return $request;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'model' => $this->model,
            'input' => $this->input,
        ];

        if ($this->previousResponseId !== null) {
            $result['previous_response_id'] = $this->previousResponseId;
        }

        if ($this->instructions !== null) {
            $result['instructions'] = $this->instructions;
        }

        if ($this->maxTokens !== null) {
            $result['max_tokens'] = $this->maxTokens;
        }

        if ($this->temperature !== null) {
            $result['temperature'] = $this->temperature;
        }

        if ($this->topP !== null) {
            $result['top_p'] = $this->topP;
        }

        if ($this->stream !== false) {
            $result['stream'] = $this->stream;
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

        return $result;
    }

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'model' => 'required|string',
            'input' => 'required',
            'previous_response_id' => 'nullable|string',
            'instructions' => 'nullable|string',
            'max_tokens' => 'nullable|integer|min:1',
            'temperature' => 'nullable|numeric|between:0,2',
            'top_p' => 'nullable|numeric|between:0,1',
            'stream' => 'nullable|boolean',
            'tools' => 'nullable|array',
            'tool_choice' => 'nullable',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * 从数组创建（带验证）
     */
    public static function fromArrayValidated(array $data): self
    {
        $request = self::fromArray($data);

        if (empty($request->model)) {
            throw new \InvalidArgumentException('model is required');
        }

        if (empty($request->input)) {
            throw new \InvalidArgumentException('input is required');
        }

        return $request;
    }

    /**
     * 转换为 Chat Completions 格式
     *
     * @param  array|null  $historyMessages  历史消息
     */
    public function toChatCompletions(?array $historyMessages = null): ChatCompletionRequest
    {
        $chatRequest = new ChatCompletionRequest;
        $chatRequest->model = $this->model;
        $chatRequest->max_tokens = $this->maxTokens;
        $chatRequest->temperature = $this->temperature;
        $chatRequest->top_p = $this->topP;
        $chatRequest->stream = $this->stream;
        $chatRequest->tools = $this->tools;
        $chatRequest->tool_choice = $this->toolChoice;

        // 构建消息数组
        $messageArrays = [];

        // 添加 instructions 作为 system message
        if ($this->instructions !== null && $this->instructions !== '') {
            $messageArrays[] = ['role' => 'system', 'content' => $this->instructions];
        }

        // 添加历史消息（需要转换 Responses API 格式为 Chat Completions 兼容格式）
        if ($historyMessages !== null) {
            $convertedHistory = $this->convertHistoryMessages($historyMessages);
            $messageArrays = array_merge($messageArrays, $convertedHistory);
        }

        // 添加当前 input
        $newMessages = $this->inputToMessages();
        $messageArrays = array_merge($messageArrays, $newMessages);

        // 转换为 Message 对象数组
        $chatRequest->messages = array_map(
            fn (array $msg) => Message::fromArray($msg),
            $messageArrays
        );

        return $chatRequest;
    }

    /**
     * 转换历史消息为 Chat Completions 兼容格式
     */
    private function convertHistoryMessages(array $historyMessages): array
    {
        $converted = [];

        foreach ($historyMessages as $msg) {
            if (! is_array($msg)) {
                continue;
            }

            $role = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';

            // 转换 role：developer -> user（硅基流动不支持 developer 角色）
            if ($role === 'developer') {
                $role = 'user';
            }

            // 转换 content 格式
            if (is_array($content)) {
                $content = $this->convertContentBlocksToChatFormat($content);
            }

            $converted[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        return $converted;
    }

    /**
     * 将内容块转换为 Chat Completions 兼容格式
     */
    private function convertContentBlocksToChatFormat(array $contentBlocks): string|array
    {
        $hasOnlyText = true;
        $textContent = '';
        $convertedBlocks = [];

        foreach ($contentBlocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? '';

            // Responses API 使用 input_text，需要转换为 text
            if ($type === 'input_text' || $type === 'text') {
                $text = $block['text'] ?? '';
                $textContent .= $text;
                $convertedBlocks[] = [
                    'type' => 'text',
                    'text' => $text,
                ];
            } elseif ($type === 'input_image' || $type === 'image') {
                $hasOnlyText = false;
                $convertedBlocks[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $block['source'] ?? $block['url'] ?? $block['image_url']['url'] ?? '',
                        'detail' => $block['detail'] ?? 'auto',
                    ],
                ];
            } elseif ($type === 'input_file' || $type === 'file') {
                // 文件类型无法直接支持，转为文本说明
                $textContent .= '[File: '.($block['filename'] ?? 'unknown').']';
                $convertedBlocks[] = [
                    'type' => 'text',
                    'text' => '[File: '.($block['filename'] ?? 'unknown').']',
                ];
            } elseif ($type === 'image_url') {
                $hasOnlyText = false;
                $convertedBlocks[] = $block;
            }
        }

        // 如果只有纯文本，返回简单字符串（硅基流动兼容）
        if ($hasOnlyText && $textContent !== '') {
            return $textContent;
        }

        return $convertedBlocks;
    }

    /**
     * input 转换为消息数组
     */
    private function inputToMessages(): array
    {
        // 字符串类型
        if (is_string($this->input)) {
            return [['role' => 'user', 'content' => $this->input]];
        }

        // 数组类型
        if (is_array($this->input)) {
            // 检查是否是单个内容块（可能是 function_call_output）
            if (isset($this->input['type'])) {
                return $this->convertSingleInputItem($this->input);
            }

            // 检查是否是内容块数组
            if ($this->isContentBlockArray($this->input)) {
                return $this->convertContentBlocksToMessages($this->input);
            }

            // 检查是否是 input_item 数组（Responses API 格式）
            if ($this->isInputItemArray($this->input)) {
                return $this->convertInputItemsToMessages($this->input);
            }

            // 已经是消息数组格式，转换 developer 角色
            return array_map(function ($msg) {
                if (isset($msg['role']) && $msg['role'] === 'developer') {
                    $msg['role'] = 'user';
                }

                return $msg;
            }, $this->input);
        }

        return [];
    }

    /**
     * 检查是否是 input_item 数组（Responses API 格式）
     */
    private function isInputItemArray(array $input): bool
    {
        if (empty($input)) {
            return false;
        }

        // input_item 格式：每个元素都有 type 字段
        $firstItem = $input[0] ?? null;
        if (! is_array($firstItem)) {
            return false;
        }

        $type = $firstItem['type'] ?? null;

        // Responses API input_item 类型
        return in_array($type, [
            'message',
            'function_call',
            'function_call_output',
            'input_text',
            'input_image',
            'input_file',
        ]);
    }

    /**
     * 转换单个 input item
     */
    private function convertSingleInputItem(array $item): array
    {
        $type = $item['type'] ?? '';

        // function_call_output：工具执行结果
        if ($type === 'function_call_output') {
            return [[
                'role' => 'tool',
                'tool_call_id' => $item['call_id'] ?? '',
                'content' => $item['output'] ?? '',
            ]];
        }

        // function_call：客户端要执行的工具调用（通常不会在 input 中出现）
        if ($type === 'function_call') {
            // 转换为 assistant 消息的 tool_calls
            return [[
                'role' => 'assistant',
                'tool_calls' => [[
                    'id' => $item['call_id'] ?? $item['id'] ?? '',
                    'type' => 'function',
                    'function' => [
                        'name' => $item['name'] ?? '',
                        'arguments' => $item['arguments'] ?? '',
                    ],
                ]],
            ]];
        }

        // message：普通消息
        if ($type === 'message') {
            $role = $item['role'] ?? 'user';
            $content = $item['content'] ?? '';

            // 转换 content 格式
            if (is_array($content)) {
                $content = $this->convertContentBlocksToChatFormat($content);
            }

            return [['role' => $role, 'content' => $content]];
        }

        // 其他类型当作用户消息
        return [['role' => 'user', 'content' => $item]];
    }

    /**
     * 转换 input_items 数组为消息数组
     *
     * Responses API 的 input_items 格式：
     * - function_call: 助手发起的工具调用（转换为 assistant 消息带 tool_calls）
     * - function_call_output: 工具执行结果（转换为 tool 消息）
     * - message: 普通消息
     *
     * 关键：当有 function_call_output 时，必须确保消息结构包含完整的对话上下文
     */
    private function convertInputItemsToMessages(array $items): array
    {
        $messages = [];

        // 收集所有 function_call 和 function_call_output
        $functionCalls = [];
        $functionCallOutputs = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = $item['type'] ?? '';

            if ($type === 'function_call') {
                $callId = $item['call_id'] ?? $item['id'] ?? '';
                $functionCalls[$callId] = $item;
            } elseif ($type === 'function_call_output') {
                $callId = $item['call_id'] ?? '';
                $functionCallOutputs[$callId] = $item;
            }
        }

        // 处理每个 item
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = $item['type'] ?? '';

            // function_call_output：工具执行结果
            if ($type === 'function_call_output') {
                $callId = $item['call_id'] ?? '';

                // 检查是否有对应的 function_call（在同一 input 数组中）
                if (isset($functionCalls[$callId])) {
                    // 先添加对应的 function_call（assistant 消息）
                    $fc = $functionCalls[$callId];
                    $messages[] = [
                        'role' => 'assistant',
                        'tool_calls' => [[
                            'id' => $fc['call_id'] ?? $fc['id'] ?? '',
                            'type' => 'function',
                            'function' => [
                                'name' => $fc['name'] ?? '',
                                'arguments' => $fc['arguments'] ?? '',
                            ],
                        ]],
                    ];
                    // 标记已处理
                    unset($functionCalls[$callId]);
                }

                // 然后添加 tool 消息（执行结果）
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $callId,
                    'content' => $item['output'] ?? '',
                ];
            }
            // function_call：助手发起的工具调用（如果还没有被 function_call_output 处理）
            elseif ($type === 'function_call') {
                $callId = $item['call_id'] ?? $item['id'] ?? '';

                // 如果还有对应的 function_call_output 未处理，这里跳过（等 function_call_output 处理）
                if (isset($functionCallOutputs[$callId])) {
                    continue;
                }

                // 否则，单独添加 assistant 消息
                $messages[] = [
                    'role' => 'assistant',
                    'tool_calls' => [[
                        'id' => $callId,
                        'type' => 'function',
                        'function' => [
                            'name' => $item['name'] ?? '',
                            'arguments' => $item['arguments'] ?? '',
                        ],
                    ]],
                ];
            }
            // message：普通消息
            elseif ($type === 'message') {
                $role = $item['role'] ?? 'user';
                $content = $item['content'] ?? '';

                if (is_array($content)) {
                    $content = $this->convertContentBlocksToChatFormat($content);
                }

                $messages[] = ['role' => $role, 'content' => $content];
            }
            // input_text：用户文本输入
            elseif ($type === 'input_text' || $type === 'text') {
                $messages[] = ['role' => 'user', 'content' => $item['text'] ?? ''];
            }
            // 其他类型
            else {
                // 尝试提取文本内容
                $text = $item['text'] ?? $item['content'] ?? '';
                if ($text !== '') {
                    $messages[] = ['role' => 'user', 'content' => $text];
                }
            }
        }

        // 验证消息结构：确保 tool 消息后有后续的用户消息
        // 如果最后一条消息是 tool，添加一个提示让模型继续处理
        $lastMessage = null;
        foreach ($messages as $msg) {
            $lastMessage = $msg;
        }

        // 如果最后一条消息是 tool，添加用户提示
        if ($lastMessage !== null && $lastMessage['role'] === 'tool') {
            $messages[] = ['role' => 'user', 'content' => '请继续处理工具执行结果。'];
        }

        return $messages;
    }

    /**
     * 检查是否是内容块数组
     */
    private function isContentBlockArray(array $input): bool
    {
        if (empty($input)) {
            return false;
        }

        $firstItem = $input[0] ?? null;
        if (! is_array($firstItem) || ! isset($firstItem['type'])) {
            return false;
        }

        $type = $firstItem['type'];

        // 内容块类型（不包含 message, function_call, function_call_output 等 input_item 类型）
        return in_array($type, ['text', 'image', 'file', 'input_text', 'input_image', 'input_file']);
    }

    /**
     * 转换内容块为消息格式
     */
    private function convertContentBlocksToMessages(array $contentBlocks): array
    {
        // 检查是否只有纯文本内容块
        $hasOnlyText = true;
        $textContent = '';

        foreach ($contentBlocks as $block) {
            if ($block['type'] === 'text') {
                $textContent .= $block['text'] ?? '';
            } elseif ($block['type'] === 'file') {
                $textContent .= '[File: '.($block['filename'] ?? 'unknown').']';
            } else {
                $hasOnlyText = false;
                break;
            }
        }

        // 如果只有纯文本，返回简单字符串格式
        if ($hasOnlyText && $textContent !== '') {
            return [['role' => 'user', 'content' => $textContent]];
        }

        // 否则，使用标准 OpenAI 格式（支持 image_url）
        $content = [];

        foreach ($contentBlocks as $block) {
            switch ($block['type']) {
                case 'text':
                    $content[] = [
                        'type' => 'text',
                        'text' => $block['text'] ?? '',
                    ];
                    break;

                case 'image':
                    $content[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $block['source'] ?? $block['url'] ?? '',
                            'detail' => $block['detail'] ?? 'auto',
                        ],
                    ];
                    break;

                case 'file':
                    $content[] = [
                        'type' => 'text',
                        'text' => '[File: '.($block['filename'] ?? 'unknown').']',
                    ];
                    break;
            }
        }

        return [['role' => 'user', 'content' => $content]];
    }

    /**
     * 转换为 Shared\DTO
     */
    public function toSharedDTO(): SharedRequest
    {
        // 构建当前消息
        $currentMessages = $this->inputToMessages();

        // 获取 apiKeyId（从构造函数注入，由 ProxyServer 传递）
        $apiKeyId = $this->_apiKeyId;

        // 状态转换：有状态 -> 无状态
        $fullMessages = $currentMessages;
        $previousResponseId = $this->previousResponseId;

        if ($previousResponseId !== null && $apiKeyId !== null) {
            $history = app(ResponseStateManager::class)->retrieve(
                $previousResponseId,
                $apiKeyId
            );

            if ($history !== null) {
                // 合并历史消息
                $fullMessages = array_merge($history, $currentMessages);
            } else {
                // 历史不存在或已过期，开始新对话
                $previousResponseId = null;
            }
        }

        // 添加 instructions 作为 system 消息
        $systemMessage = null;
        if ($this->instructions !== null && $this->instructions !== '') {
            $systemMessage = ['role' => 'system', 'content' => $this->instructions];
        }

        // 构建完整消息列表
        $allMessages = [];
        if ($systemMessage !== null) {
            $allMessages[] = $systemMessage;
        }
        $allMessages = array_merge($allMessages, $fullMessages);

        // 使用 fromArray 创建 DTO，确保消息正确转换为 Message 对象
        $dto = SharedRequest::fromArray([
            'model' => $this->model,
            'messages' => $allMessages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'top_p' => $this->topP,
            'stream' => $this->stream ?? false,
            'tools' => $this->tools,
            'tool_choice' => $this->toolChoice,
            'rawRequest' => $this->toArray(),
        ]);

        // 携带协议上下文（用于响应阶段存储状态）
        // 注意：使用 dto->messages，因为它们是 Message 对象
        $dto->protocolContext = new ResponsesContext(
            previousResponseId: $previousResponseId,
            fullMessages: $dto->messages,
            apiKeyId: $apiKeyId,
        );

        return $dto;
    }

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(object $dto): static
    {
        $request = new self;
        $request->model = $dto->model;
        $request->input = $dto->messages ?? '';
        $request->maxTokens = $dto->maxTokens;
        $request->temperature = $dto->temperature;
        $request->topP = $dto->topP;
        $request->stream = $dto->stream;
        $request->tools = $dto->tools;
        $request->toolChoice = $dto->toolChoice;

        return $request;
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
     * 设置原始请求体（用于 body_passthrough）
     */
    public function setRawBodyString(string $rawBody): static
    {
        // Responses API 暂不支持 body_passthrough

        return $this;
    }
}
