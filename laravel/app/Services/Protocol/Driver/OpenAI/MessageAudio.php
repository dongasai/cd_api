<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;

/**
 * OpenAI 音频响应结构体
 *
 * 用于响应消息中的 audio 字段，包含生成的音频内容
 *
 * @see https://platform.openai.com/docs/api-reference/chat/object#chat/object-choices-message-audio
 */
class MessageAudio
{
    use JsonSerializiable;

    /**
     * @param  string  $id  音频ID
     * @param  string  $data  Base64 编码的音频数据
     * @param  int  $expiresAt  过期时间戳
     * @param  string  $transcript  音频文本转录
     */
    public function __construct(
        public string $id = '',
        public string $data = '',
        public int $expiresAt = 0,
        public string $transcript = '',
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'id' => 'required|string',
            'data' => 'required|string',
            'expires_at' => 'required|integer',
            'transcript' => 'required|string',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        return new self(
            id: $data['id'] ?? '',
            data: $data['data'] ?? '',
            expiresAt: $data['expires_at'] ?? 0,
            transcript: $data['transcript'] ?? '',
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'data' => $this->data,
            'expires_at' => $this->expiresAt,
            'transcript' => $this->transcript,
        ];
    }
}
