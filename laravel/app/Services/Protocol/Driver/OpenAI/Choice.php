<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;
use App\Services\Shared\Enums\MessageRole;

/**
 * OpenAI 响应选择结构体（非流式）
 *
 * 非流式响应使用 message 字段
 * 流式响应请使用 StreamedChoice 类（使用 delta 字段）
 *
 * @see https://platform.openai.com/docs/api-reference/chat/object#chat/object-choices
 */
class Choice
{
    use JsonSerializiable;

    /**
     * @param  int  $index  选择索引
     * @param  Message  $message  完整消息（非流式）
     * @param  string|null  $finishReason  结束原因
     * @param  Logprobs|null  $logprobs  对数概率信息
     */
    public function __construct(
        public int $index = 0,
        public Message $message = new Message(MessageRole::Assistant),
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
            'message' => 'required|array',
            'finish_reason' => 'nullable|string|in:stop,length,tool_calls,content_filter,function_call',
            'logprobs' => 'nullable|array',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        $message = Message::fromArray($data['message'] ?? ['role' => 'assistant']);

        $logprobs = null;
        if (isset($data['logprobs']) && is_array($data['logprobs'])) {
            $logprobs = Logprobs::fromArray($data['logprobs']);
        }

        return new self(
            index: $data['index'] ?? 0,
            message: $message,
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
            'message' => $this->message->toArray(),
        ];

        if ($this->finishReason !== null) {
            $result['finish_reason'] = $this->finishReason;
        }

        if ($this->logprobs !== null) {
            $result['logprobs'] = $this->logprobs->toArray();
        }

        return $result;
    }
}
