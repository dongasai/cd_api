<?php

namespace App\Services\Provider\DTO;

use Illuminate\Support\Facades\Log;

/**
 * 供应商流式响应块数据传输对象
 *
 * 用于封装 AI 供应商流式响应中的单个数据块
 */
class ProviderStreamChunk
{
    public function __construct(
        public string $event = '',
        public array $data = [],
        public string $delta = '',
        public ?string $id = null,
        public ?string $model = null,
        public ?string $finishReason = null,
        public ?TokenUsage $usage = null,
        public ?array $toolCalls = null,
        // 推理内容增量（DeepSeek、Kimi 等思考模型）
        public ?string $reasoningDelta = null,
    ) {}

    /**
     * 从 OpenAI 格式创建实例
     *
     * @param  string  $rawEvent  原始 SSE 事件数据
     */
    public static function fromOpenAI(string $rawEvent): ?self
    {
        // Log::debug("fromOpenAI ".$rawEvent);
        $lines = explode("\n", trim($rawEvent));
        $event = '';
        $data = '';

        // 解析 SSE 格式
        foreach ($lines as $line) {
            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data = trim(substr($line, 5));
            }
        }

        // 跳过空数据和结束标记
        if (empty($data) || $data === '[DONE]') {
            return null;
        }

        $parsed = json_decode($data, true);
        if ($parsed === null) {
            return null;
        }

        $id = $parsed['id'] ?? null;
        $model = $parsed['model'] ?? null;
        $delta = '';
        $finishReason = null;
        $usage = null;
        $toolCalls = null;
        $reasoningDelta = null;

        $choices = $parsed['choices'] ?? [];
        $choice = $choices[0] ?? [];

        // 提取内容增量
        if (isset($choice['delta'])) {
            $delta = $choice['delta']['content'] ?? '';
            // 提取推理内容增量（DeepSeek、Kimi 等思考模型）
            $reasoningDelta = $choice['delta']['reasoning_content'] ?? null;
            // 提取工具调用
            if (isset($choice['delta']['tool_calls'])) {
                $toolCalls = $choice['delta']['tool_calls'];
            }
        }

        if (isset($choice['finish_reason'])) {
            $finishReason = $choice['finish_reason'];
        }

        if (isset($parsed['usage'])) {
            $usage = TokenUsage::fromOpenAI($parsed['usage']);
        }

        return new self(
            event: $event,
            data: $parsed,
            delta: $delta,
            id: $id,
            model: $model,
            finishReason: $finishReason,
            usage: $usage,
            toolCalls: $toolCalls,
            reasoningDelta: $reasoningDelta,
        );
    }

    /**
     * 从 Anthropic 格式创建实例
     *
     * @param  string  $rawEvent  原始 SSE 事件数据
     */
    public static function fromAnthropic(string $rawEvent): ?self
    {
        Log::debug('fromAnthropic渠道 :'.$rawEvent);
        $lines = explode("\n", trim($rawEvent));
        $event = '';
        $data = '';

        // 解析 SSE 格式
        foreach ($lines as $line) {
            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data = trim(substr($line, 5));
            }
        }

        if (empty($data)) {
            return null;
        }

        $parsed = json_decode($data, true);
        if ($parsed === null) {
            return null;
        }

        $delta = '';
        $id = null;
        $model = null;
        $finishReason = null;
        $usage = null;
        $reasoningDelta = null;
        $toolCalls = null;
        $isToolCallDelta = false; // 标记是否为工具调用增量

        // 根据事件类型解析数据
        switch ($event) {
            case 'message_start':
                $message = $parsed['message'] ?? [];
                $id = $message['id'] ?? null;
                $model = $message['model'] ?? null;
                if (isset($message['usage'])) {
                    $usage = TokenUsage::fromAnthropic($message['usage']);
                }
                break;

            case 'ping':
                // ping 事件用于保持连接活跃，需要透传
                // 将原始数据保存在 data 中
                $data = $parsed;
                break;

            case 'content_block_start':
                // 处理工具调用开始
                $contentBlock = $parsed['content_block'] ?? [];
                if (($contentBlock['type'] ?? '') === 'tool_use') {
                    $toolCalls = [[
                        'id' => $contentBlock['id'] ?? '',
                        'type' => 'function',
                        'function' => [
                            'name' => $contentBlock['name'] ?? '',
                            'arguments' => '',
                        ],
                        'index' => $parsed['index'] ?? 0,
                    ]];
                }
                break;

            case 'content_block_delta':
                // 先获取 delta 类型
                $deltaType = $parsed['delta']['type'] ?? '';

                // 处理文本增量 (text_delta)
                if ($deltaType === 'text_delta' && isset($parsed['delta']['text'])) {
                    $delta = $parsed['delta']['text'];
                }
                // 处理思考增量 (thinking_delta)
                if ($deltaType === 'thinking_delta' && isset($parsed['delta']['thinking'])) {
                    $reasoningDelta = $parsed['delta']['thinking'];
                }
                // 处理工具调用参数增量 (input_json_delta)
                if ($deltaType === 'input_json_delta' && isset($parsed['delta']['partial_json'])) {
                    $toolCalls = [[
                        'index' => $parsed['index'] ?? 0,
                        'function' => [
                            'arguments' => $parsed['delta']['partial_json'],
                        ],
                    ]];
                    $isToolCallDelta = true;
                }
                break;

            case 'message_delta':
                $usage = isset($parsed['usage'])
                    ? TokenUsage::fromAnthropic($parsed['usage'])
                    : null;
                $finishReason = $parsed['delta']['stop_reason'] ?? null;
                break;

            case 'content_block_stop':
            case 'message_stop':
                break;
        }

        return new self(
            event: $event,
            data: $parsed,
            delta: $delta,
            id: $id,
            model: $model,
            finishReason: $finishReason,
            usage: $usage,
            toolCalls: $toolCalls,
            reasoningDelta: $reasoningDelta,
        );
    }

    /**
     * 转换为 OpenAI 流式块格式
     */
    public function toOpenAIChunk(string $id, string $model): string
    {
        $delta = [];

        if ($this->delta) {
            $delta['content'] = $this->delta;
        }

        // 添加推理内容增量（DeepSeek、Kimi 等思考模型）
        if ($this->reasoningDelta !== null) {
            $delta['reasoning_content'] = $this->reasoningDelta;
        }

        $chunk = [
            'id' => $id ?: 'chatcmpl-'.uniqid(),
            'object' => 'chat.completion.chunk',
            'created' => time(),
            'model' => $model,
            'choices' => [
                [
                    'index' => 0,
                    'delta' => $delta,
                    'finish_reason' => $this->finishReason,
                ],
            ],
        ];

        if ($this->usage !== null) {
            $chunk['usage'] = $this->usage->toOpenAI();
        }

        return 'data: '.json_encode($chunk, JSON_UNESCAPED_UNICODE)."\n\n";
    }

    /**
     * 转换为 Anthropic 流式事件格式
     */
    public function toAnthropicEvent(): string
    {
        $output = "event: {$this->event}\n";
        $output .= 'data: '.json_encode($this->data, JSON_UNESCAPED_UNICODE)."\n\n";

        return $output;
    }

    /**
     * 是否为空数据块
     *
     * 注意: ping、content_block_start、content_block_stop 等控制事件
     * 虽然 delta 为空,但它们有重要的控制意义,不应被视为空事件
     */
    public function isEmpty(): bool
    {
        // 有事件类型的不是空事件(包括 ping、content_block_start 等)
        if (! empty($this->event)) {
            return false;
        }

        // 有推理内容的不是空事件
        if (! empty($this->reasoningDelta)) {
            return false;
        }

        // 有工具调用的不是空事件
        if (! empty($this->toolCalls)) {
            return false;
        }

        // 其他情况:检查是否有实际内容
        return empty($this->delta) && empty($this->finishReason) && empty($this->usage);
    }

    /**
     * 是否为结束数据块
     */
    public function isDone(): bool
    {
        return $this->event === 'message_stop' || $this->finishReason !== null;
    }

    /**
     * 转换为数组格式
     */
    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'data' => $this->data,
            'delta' => $this->delta,
            'id' => $this->id,
            'model' => $this->model,
            'finish_reason' => $this->finishReason,
            'usage' => $this->usage?->toArray(),
        ];
    }
}
