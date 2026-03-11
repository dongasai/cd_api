<?php

namespace App\Services\Protocol\DTO;

/**
 * 标准请求 DTO
 */
class StandardRequest
{
    public function __construct(
        // 模型名称
        public string $model,

        // 消息列表
        /** @var StandardMessage[] */
        public array $messages,

        // 系统提示 (可以是字符串或数组，Anthropic支持复杂格式)
        public string|array|null $systemPrompt = null,

        // 采样参数
        public ?float $temperature = null,
        public ?float $topP = null,
        public ?int $topK = null,

        // 输出限制
        public ?int $maxTokens = null,
        public ?int $maxOutputTokens = null,

        // 停止序列
        /** @var string[]|null */
        public ?array $stopSequences = null,

        // 流式
        public bool $stream = false,

        // 工具调用
        /** @var array|null */
        public ?array $tools = null,
        public string|array|null $toolChoice = null,

        // 其他参数
        public array $additionalParams = [],

        // 用户标识
        public ?string $user = null,

        // 多模态内容
        public bool $hasImages = false,
        public bool $hasAudio = false,

        // 原始请求
        public ?array $rawRequest = null,
    ) {}

    /**
     * 从 OpenAI 格式创建
     */
    public static function fromOpenAI(array $request): self
    {
        $messages = self::parseOpenAIMessages($request['messages'] ?? []);
        $systemPrompt = self::extractSystemPrompt($messages);

        // 过滤掉 system 消息
        $messages = array_filter($messages, fn ($m) => $m->role !== 'system');
        $messages = array_values($messages);

        // 解析工具
        $tools = self::parseOpenAITools($request['tools'] ?? null);
        $toolChoice = $request['tool_choice'] ?? null;

        return new self(
            model: $request['model'] ?? '',
            messages: $messages,
            systemPrompt: $systemPrompt,
            temperature: $request['temperature'] ?? null,
            topP: $request['top_p'] ?? null,
            maxTokens: $request['max_tokens'] ?? null,
            stopSequences: $request['stop'] ?? null,
            stream: $request['stream'] ?? false,
            tools: $tools,
            toolChoice: $toolChoice,
            additionalParams: self::extractAdditionalParams($request, [
                'model', 'messages', 'temperature', 'top_p', 'max_tokens',
                'stop', 'stream', 'tools', 'tool_choice', 'user',
                'presence_penalty', 'frequency_penalty', 'logit_bias', 'n',
                'response_format', 'seed',
            ]),
            user: $request['user'] ?? null,
            hasImages: self::hasImageContent($request['messages'] ?? []),
            hasAudio: self::hasAudioContent($request['messages'] ?? []),
            rawRequest: $request,
        );
    }

    /**
     * 从 Anthropic 格式创建
     */
    public static function fromAnthropic(array $request): self
    {
        $messages = self::parseAnthropicMessages($request['messages'] ?? []);

        // 解析工具
        $tools = self::parseAnthropicTools($request['tools'] ?? null);
        $toolChoice = self::parseAnthropicToolChoice($request['tool_choice'] ?? null);

        // 提取 metadata 中的其他字段（除了 user_id）
        $metadata = $request['metadata'] ?? null;
        $metadataExtra = null;
        if ($metadata !== null) {
            $metadataExtra = $metadata;
            unset($metadataExtra['user_id']);
            if (empty($metadataExtra)) {
                $metadataExtra = null;
            }
        }

        // 构建额外参数，保留 Anthropic 特有参数
        $additionalParams = self::extractAdditionalParams($request, [
            'model', 'messages', 'system', 'temperature', 'top_p', 'top_k',
            'max_tokens', 'stop_sequences', 'stream', 'tools', 'tool_choice',
            'metadata',
        ]);

        // 手动添加 thinking, output_config, beta 到 additionalParams
        if (isset($request['thinking'])) {
            $additionalParams['thinking'] = $request['thinking'];
        }
        if (isset($request['output_config'])) {
            $additionalParams['output_config'] = $request['output_config'];
        }
        if (isset($request['beta'])) {
            $additionalParams['beta'] = $request['beta'];
        }
        // 如果 metadata 有其他字段，也保留
        if ($metadataExtra !== null) {
            $additionalParams['metadata_extra'] = $metadataExtra;
        }
        // 保留原始 metadata 容器（用于 Anthropic to Anthropic 转发）
        if ($metadata !== null) {
            $additionalParams['metadata_container'] = $metadata;
        }

        return new self(
            model: $request['model'] ?? '',
            messages: $messages,
            systemPrompt: $request['system'] ?? null,
            temperature: $request['temperature'] ?? null,
            topP: $request['top_p'] ?? null,
            topK: $request['top_k'] ?? null,
            maxTokens: $request['max_tokens'] ?? null,
            stopSequences: $request['stop_sequences'] ?? null,
            stream: $request['stream'] ?? false,
            tools: $tools,
            toolChoice: $toolChoice,
            additionalParams: $additionalParams,
            user: $request['metadata']['user_id'] ?? null,
            hasImages: self::hasAnthropicImageContent($request['messages'] ?? []),
            rawRequest: $request,
        );
    }

    /**
     * 转换为 OpenAI 格式
     */
    public function toOpenAI(): array
    {
        $request = [
            'model' => $this->model,
            'messages' => $this->buildOpenAIMessages(),
        ];

        // 添加可选参数
        if ($this->temperature !== null) {
            $request['temperature'] = $this->temperature;
        }
        if ($this->topP !== null) {
            $request['top_p'] = $this->topP;
        }
        if ($this->maxTokens !== null) {
            $request['max_tokens'] = $this->maxTokens;
        }
        if ($this->stopSequences !== null) {
            $request['stop'] = $this->stopSequences;
        }
        if ($this->stream) {
            $request['stream'] = true;
        }
        if ($this->tools !== null) {
            $request['tools'] = $this->buildOpenAITools();
        }
        if ($this->toolChoice !== null) {
            $request['tool_choice'] = $this->buildOpenAIToolChoice();
        }
        if ($this->user !== null) {
            $request['user'] = $this->user;
        }

        // 合并额外参数
        return array_merge($this->additionalParams, $request);
    }

    /**
     * 转换为 Anthropic 格式
     *
     * @param  bool  $preserveCacheControl  是否保留 cache_control 字段（用于 Anthropic to Anthropic 转发）
     */
    public function toAnthropic(bool $preserveCacheControl = true): array
    {
        $request = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens ?? 4096,
            'messages' => $this->buildAnthropicMessages($preserveCacheControl),
        ];

        // 系统提示
        if ($this->hasSystemPrompt()) {
            $request['system'] = $this->systemPrompt;
        }

        // 添加可选参数
        if ($this->temperature !== null) {
            $request['temperature'] = $this->temperature;
        }
        if ($this->topP !== null) {
            $request['top_p'] = $this->topP;
        }
        if ($this->topK !== null) {
            $request['top_k'] = $this->topK;
        }
        if ($this->stopSequences !== null) {
            $request['stop_sequences'] = $this->stopSequences;
        }
        if ($this->stream) {
            $request['stream'] = true;
        }
        if ($this->tools !== null) {
            $request['tools'] = $this->buildAnthropicTools();
        }
        if ($this->toolChoice !== null) {
            $request['tool_choice'] = $this->buildAnthropicToolChoice();
        }

        // 先处理 metadata_container，恢复完整的 metadata（用于 Anthropic to Anthropic 转发）
        if (isset($this->additionalParams['metadata_container']) && is_array($this->additionalParams['metadata_container'])) {
            $request['metadata'] = $this->additionalParams['metadata_container'];
        }

        // 如果 metadata 不存在，使用 user 字段构建
        if (! isset($request['metadata']) && $this->user !== null) {
            $request['metadata']['user_id'] = $this->user;
        }

        // 处理 metadata_extra，添加额外的 metadata 字段
        if (isset($this->additionalParams['metadata_extra']) && is_array($this->additionalParams['metadata_extra'])) {
            foreach ($this->additionalParams['metadata_extra'] as $key => $value) {
                $request['metadata'][$key] = $value;
            }
        }

        // 过滤参数，保留 Anthropic 特有参数
        $allowedParams = $this->filterAllowedParams($this->additionalParams);

        // 如果有原始请求，按照原始请求的字段顺序构建最终请求
        if ($this->rawRequest !== null) {
            return $this->buildOrderedRequest($request, $allowedParams);
        }

        // 否则，直接合并（标准字段在前，特有字段在后）
        return array_merge($request, $allowedParams);
    }

    /**
     * 按照原始请求的字段顺序构建最终请求
     */
    private function buildOrderedRequest(array $request, array $allowedParams): array
    {
        $ordered = [];

        // 遍历原始请求的所有字段
        foreach (array_keys($this->rawRequest) as $key) {
            // 如果这个字段在标准请求中有转换后的值，使用转换后的值
            if (array_key_exists($key, $request)) {
                $ordered[$key] = $request[$key];
                // 从 request 中移除，避免后续重复添加
                unset($request[$key]);
            }
            // 如果这个字段在允许的额外参数中，使用原始值
            elseif (array_key_exists($key, $allowedParams)) {
                $ordered[$key] = $allowedParams[$key];
                // 从 allowedParams 中移除，避免后续重复添加
                unset($allowedParams[$key]);
            }
        }

        // 添加原始请求中没有的标准字段（如转换后新增的字段）
        foreach ($request as $key => $value) {
            $ordered[$key] = $value;
        }

        // 添加原始请求中没有的额外参数
        foreach ($allowedParams as $key => $value) {
            $ordered[$key] = $value;
        }

        return $ordered;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'messages' => array_map(fn ($m) => $m->toArray(), $this->messages),
            'system_prompt' => $this->systemPrompt,
            'temperature' => $this->temperature,
            'top_p' => $this->topP,
            'top_k' => $this->topK,
            'max_tokens' => $this->maxTokens,
            'max_output_tokens' => $this->maxOutputTokens,
            'stop_sequences' => $this->stopSequences,
            'stream' => $this->stream,
            'tools' => $this->tools,
            'tool_choice' => $this->toolChoice,
            'additional_params' => $this->additionalParams,
            'user' => $this->user,
            'has_images' => $this->hasImages,
            'has_audio' => $this->hasAudio,
        ];
    }

    /**
     * 估算 Token 数量 (粗略估算)
     */
    public function estimateTokens(): int
    {
        $text = '';

        // 处理系统提示
        if (is_string($this->systemPrompt)) {
            $text = $this->systemPrompt;
        } elseif (is_array($this->systemPrompt)) {
            foreach ($this->systemPrompt as $block) {
                if (is_string($block)) {
                    $text .= $block;
                } elseif (isset($block['text'])) {
                    $text .= $block['text'];
                }
            }
        }

        foreach ($this->messages as $message) {
            $text .= ' '.$message->content;
        }

        // 粗略估算: 4字符约等于1个token
        return (int) ceil(strlen($text) / 4);
    }

    /**
     * 获取消息数量
     */
    public function getMessageCount(): int
    {
        return count($this->messages);
    }

    /**
     * 是否有系统提示
     */
    public function hasSystemPrompt(): bool
    {
        if ($this->systemPrompt === null) {
            return false;
        }

        if (is_string($this->systemPrompt)) {
            return $this->systemPrompt !== '';
        }

        if (is_array($this->systemPrompt)) {
            return ! empty($this->systemPrompt);
        }

        return false;
    }

    /**
     * 是否有工具定义
     */
    public function hasTools(): bool
    {
        return $this->tools !== null && count($this->tools) > 0;
    }

    // ==================== 私有方法 ====================

    /**
     * 解析 OpenAI 消息
     */
    private static function parseOpenAIMessages(array $messages): array
    {
        return array_map(
            fn ($msg) => StandardMessage::fromOpenAI($msg),
            $messages
        );
    }

    /**
     * 解析 Anthropic 消息
     * 注意: Anthropic 格式中 tool_result 是 user 消息的内容块
     *       需要转换为 OpenAI 格式的独立 tool 消息
     */
    private static function parseAnthropicMessages(array $messages): array
    {
        $result = [];
        foreach ($messages as $msg) {
            $parsed = self::parseAnthropicMessage($msg);
            foreach ($parsed as $message) {
                $result[] = $message;
            }
        }

        return $result;
    }

    /**
     * 解析单条 Anthropic 消息
     * 可能返回多条消息（当包含 tool_result 时）
     *
     * 注意：对于包含 tool_result 的消息，我们保持原始结构不变
     * 不再拆分为多条消息，以保持 content blocks 的顺序
     */
    private static function parseAnthropicMessage(array $msg): array
    {
        $role = $msg['role'] ?? 'user';
        $content = $msg['content'] ?? null;

        if (! is_array($content)) {
            return [StandardMessage::fromAnthropic($msg)];
        }

        // 检查是否包含 tool_result
        $hasToolResult = false;
        foreach ($content as $block) {
            if (($block['type'] ?? '') === 'tool_result') {
                $hasToolResult = true;
                break;
            }
        }

        // 如果没有 tool_result，直接解析
        if (! $hasToolResult) {
            return [StandardMessage::fromAnthropic($msg)];
        }

        // 如果包含 tool_result，我们需要将整条消息作为一个特殊的 user 消息
        // 保留所有 content blocks 的原始顺序
        // 同时提取所有 tool_result 的信息以便后续处理

        // 为了保持向后兼容，我们仍然需要分离出 tool 消息
        // 但要记录原始消息的信息，以便在转换时恢复

        $messages = [];

        $toolResultBlocks = array_filter(
            $content,
            fn ($block) => ($block['type'] ?? '') === 'tool_result'
        );

        $nonToolResultBlocks = array_filter(
            $content,
            fn ($block) => ($block['type'] ?? '') !== 'tool_result'
        );

        // 先添加 tool 消息（保持原始顺序）
        foreach ($toolResultBlocks as $block) {
            $toolContent = $block['content'] ?? '';
            if (is_array($toolContent)) {
                $toolContent = json_encode($toolContent, JSON_UNESCAPED_UNICODE);
            }

            // 解析 tool_result 的 content blocks（可能包含 cache_control）
            $contentBlocks = [ContentBlock::fromAnthropic($block)];

            $messages[] = new StandardMessage(
                role: 'tool',
                content: $toolContent,
                contentBlocks: $contentBlocks,
                toolCallId: $block['tool_use_id'] ?? null,
            );
        }

        // 再添加非 tool_result 的内容作为 user 消息
        if (! empty($nonToolResultBlocks)) {
            $userMsg = $msg;
            $userMsg['content'] = array_values($nonToolResultBlocks);
            $messages[] = StandardMessage::fromAnthropic($userMsg);
        }

        return $messages;
    }

    /**
     * 提取系统提示
     */
    private static function extractSystemPrompt(array $messages): ?string
    {
        foreach ($messages as $message) {
            if ($message->role === 'system') {
                return $message->content;
            }
        }

        return null;
    }

    /**
     * 构建 OpenAI 消息数组
     */
    private function buildOpenAIMessages(): array
    {
        $messages = [];

        // 添加系统消息
        if ($this->systemPrompt !== null) {
            if (is_string($this->systemPrompt)) {
                $messages[] = [
                    'role' => 'system',
                    'content' => $this->systemPrompt,
                ];
            } elseif (is_array($this->systemPrompt)) {
                // Anthropic 复杂格式转换为 OpenAI 格式
                $systemContent = '';
                foreach ($this->systemPrompt as $block) {
                    if (is_string($block)) {
                        $systemContent .= $block;
                    } elseif (isset($block['text'])) {
                        $systemContent .= $block['text'];
                    }
                }
                if ($systemContent !== '') {
                    $messages[] = [
                        'role' => 'system',
                        'content' => $systemContent,
                    ];
                }
            }
        }

        // 添加其他消息
        foreach ($this->messages as $message) {
            $messages[] = $message->toOpenAI();
        }

        return $messages;
    }

    /**
     * 构建 Anthropic 消息数组
     *
     * @param  bool  $preserveCacheControl  是否保留 cache_control 字段
     */
    private function buildAnthropicMessages(bool $preserveCacheControl = true): array
    {
        // Anthropic 没有 system 消息在 messages 中
        return array_map(
            fn ($msg) => $msg->toAnthropic($preserveCacheControl),
            $this->messages
        );
    }

    /**
     * 解析 OpenAI 工具
     */
    private static function parseOpenAITools(?array $tools): ?array
    {
        if ($tools === null) {
            return null;
        }

        return array_map(function ($tool) {
            if ($tool['type'] === 'function') {
                return [
                    'type' => 'function',
                    'function' => $tool['function'],
                ];
            }

            return $tool;
        }, $tools);
    }

    /**
     * 解析 Anthropic 工具
     */
    private static function parseAnthropicTools(?array $tools): ?array
    {
        if ($tools === null) {
            return null;
        }

        return array_map(function ($tool) {
            return [
                'name' => $tool['name'],
                'description' => $tool['description'] ?? '',
                'input_schema' => $tool['input_schema'],
            ];
        }, $tools);
    }

    /**
     * 解析 Anthropic tool_choice
     */
    private static function parseAnthropicToolChoice(mixed $toolChoice): mixed
    {
        if ($toolChoice === null) {
            return null;
        }

        if (is_string($toolChoice)) {
            return $toolChoice; // auto, any, none
        }

        if (is_array($toolChoice) && isset($toolChoice['type'])) {
            return $toolChoice;
        }

        return null;
    }

    /**
     * 构建 OpenAI 工具
     */
    private function buildOpenAITools(): array
    {
        if ($this->tools === null) {
            return [];
        }

        return array_map(function ($tool) {
            if (isset($tool['function'])) {
                return $tool;
            }

            // Anthropic 格式转换
            return [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'] ?? '',
                    'parameters' => $tool['input_schema'],
                ],
            ];
        }, $this->tools);
    }

    /**
     * 构建 Anthropic 工具
     */
    private function buildAnthropicTools(): array
    {
        if ($this->tools === null) {
            return [];
        }

        return array_map(function ($tool) {
            if (isset($tool['input_schema'])) {
                // Anthropic 格式，直接返回，不做修改
                // 注意：不再修复 additionalProperties，保持原始值
                return $tool;
            }

            // OpenAI 格式转换
            $inputSchema = $tool['function']['parameters'];

            return [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'] ?? '',
                'input_schema' => $inputSchema,
            ];
        }, $this->tools);
    }

    /**
     * 修复 input_schema 中无效的 additionalProperties
     *
     * JSON Schema 规范要求 additionalProperties 应该是 boolean 或 object
     * 但有些客户端可能传入空数组 []，需要修复
     */
    private static function fixInputSchema(array $schema): array
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
                    $schema['properties'][$key] = self::fixInputSchema($property);
                }
            }
        }

        // 处理 items（数组类型的元素）
        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = self::fixInputSchema($schema['items']);
        }

        return $schema;
    }

    /**
     * 构建 OpenAI tool_choice
     */
    private function buildOpenAIToolChoice(): mixed
    {
        if (is_string($this->toolChoice)) {
            return match ($this->toolChoice) {
                'auto', 'any' => 'auto',
                'none' => 'none',
                default => $this->toolChoice,
            };
        }

        if (is_array($this->toolChoice) && isset($this->toolChoice['name'])) {
            // Anthropic 格式转换
            return [
                'type' => 'function',
                'function' => ['name' => $this->toolChoice['name']],
            ];
        }

        return $this->toolChoice;
    }

    /**
     * 构建 Anthropic tool_choice
     */
    private function buildAnthropicToolChoice(): mixed
    {
        if (is_string($this->toolChoice)) {
            return $this->toolChoice;
        }

        if (is_array($this->toolChoice) && isset($this->toolChoice['function'])) {
            // OpenAI 格式转换
            return [
                'type' => 'tool',
                'name' => $this->toolChoice['function']['name'],
            ];
        }

        return $this->toolChoice;
    }

    /**
     * 提取额外参数
     */
    private static function extractAdditionalParams(array $request, array $excludeKeys): array
    {
        return array_filter(
            $request,
            fn ($key) => ! in_array($key, $excludeKeys),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * 检查 OpenAI 消息是否包含图片
     */
    private static function hasImageContent(array $messages): bool
    {
        foreach ($messages as $message) {
            $content = $message['content'] ?? null;
            if (is_array($content)) {
                foreach ($content as $block) {
                    if (($block['type'] ?? '') === 'image_url') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * 检查 OpenAI 消息是否包含音频
     */
    private static function hasAudioContent(array $messages): bool
    {
        foreach ($messages as $message) {
            $content = $message['content'] ?? null;
            if (is_array($content)) {
                foreach ($content as $block) {
                    if (($block['type'] ?? '') === 'input_audio') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * 检查 Anthropic 消息是否包含图片
     */
    private static function hasAnthropicImageContent(array $messages): bool
    {
        foreach ($messages as $message) {
            $content = $message['content'] ?? null;
            if (is_array($content)) {
                foreach ($content as $block) {
                    if (($block['type'] ?? '') === 'image') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * 过滤允许的额外参数
     *
     * 过滤掉不支持的供应商特有参数，避免发送到上游 API
     * thinking, output_config, beta 等是特定供应商的参数，需要谨慎处理
     */
    private function filterAllowedParams(array $params): array
    {
        // 定义允许的参数列表（通用参数）
        $allowed = [
            // 可以在这里添加需要保留的通用参数
            // 默认过滤掉供应商特有参数
        ];

        // 保留所有参数，包括 Anthropic 特有参数
        // thinking, output_config, beta 等参数在 Anthropic to Anthropic 转发时需要保留
        $filtered = [];
        foreach ($params as $key => $value) {
            // 过滤掉内部使用的临时字段
            if ($key !== 'metadata_extra' && $key !== 'metadata_container') {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }
}
