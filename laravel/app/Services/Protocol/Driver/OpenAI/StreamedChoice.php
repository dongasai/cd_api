<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;

/**
 * OpenAI 流式响应选择结构体
 *
 * 专用于流式响应，使用 delta 而非 message
 *
 * @see https://platform.openai.com/docs/api-reference/chat/streaming#chat/streaming-choices
 */
class StreamedChoice
{
    use JsonSerializiable;

    /**
     * @param  int  $index  选择索引
     * @param  Message|null  $delta  增量消息（流式）
     * @param  string|null  $finishReason  结束原因
     * @param  Logprobs|null  $logprobs  对数概率信息
     */
    public function __construct(
        public int $index = 0,
        public ?Message $delta = null,
        public ?string $finishReason = null,
        public ?Logprobs $logprobs = null,
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'index' => 'required|integer|min:0',
            'delta' => 'nullable|array',
            'finish_reason' => 'nullable|string|in:stop,length,tool_calls,content_filter,function_call',
            'logprobs' => 'nullable|array',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        $delta = null;
        if (isset($data['delta']) && is_array($data['delta'])) {
            $delta = Message::fromArray($data['delta']);
        }

        $logprobs = null;
        if (isset($data['logprobs']) && is_array($data['logprobs'])) {
            $logprobs = Logprobs::fromArray($data['logprobs']);
        }

        return new self(
            index: $data['index'] ?? 0,
            delta: $delta,
            finishReason: $data['finish_reason'] ?? null,
            logprobs: $logprobs,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'index' => $this->index,
        ];

        if ($this->delta !== null) {
            $result['delta'] = $this->delta->toArray();
        }

        if ($this->finishReason !== null) {
            $result['finish_reason'] = $this->finishReason;
        }

        if ($this->logprobs !== null) {
            $result['logprobs'] = $this->logprobs->toArray();
        }

        return $result;
    }
}
