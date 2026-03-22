<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;

/**
 * OpenAI 响应选择结构体
 *
 * @see https://platform.openai.com/docs/api-reference/chat/object#chat/object-choices
 */
class Choice
{
    use JsonSerializiable;

    /**
     * @param  int  $index  选择索引
     * @param  Message|null  $message  完整消息（非流式）
     * @param  Message|null  $delta  增量消息（流式）
     * @param  string|null  $finish_reason  结束原因
     */
    public function __construct(
        public int $index = 0,
        public ?Message $message = null,
        public ?Message $delta = null,
        public ?string $finish_reason = null,
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'index' => 'required|integer|min:0',
            'message' => 'nullable|array',
            'delta' => 'nullable|array',
            'finish_reason' => 'nullable|string|in:stop,length,tool_calls,content_filter,function_call',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        $message = null;
        if (isset($data['message']) && is_array($data['message'])) {
            $message = Message::fromArray($data['message']);
        }

        $delta = null;
        if (isset($data['delta']) && is_array($data['delta'])) {
            $delta = Message::fromArray($data['delta']);
        }

        return new self(
            index: $data['index'] ?? 0,
            message: $message,
            delta: $delta,
            finish_reason: $data['finish_reason'] ?? null,
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

        if ($this->message !== null) {
            $result['message'] = $this->message->toArray();
        }

        if ($this->delta !== null) {
            $result['delta'] = $this->delta->toArray();
        }

        if ($this->finish_reason !== null) {
            $result['finish_reason'] = $this->finish_reason;
        }

        return $result;
    }
}
