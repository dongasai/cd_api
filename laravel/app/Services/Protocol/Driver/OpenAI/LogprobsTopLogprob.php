<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;

/**
 * OpenAI Top Logprob 结构体
 *
 * 表示最可能的替代 token 及其对数概率
 *
 * @see https://platform.openai.com/docs/api-reference/chat/object#chat/object-choices-logprobs-content-top_logprobs
 */
class LogprobsTopLogprob
{
    use JsonSerializiable;

    /**
     * @param  string  $token  token 文本
     * @param  float  $logprob  对数概率值
     * @param  int[]|null  $bytes  token 的字节表示
     */
    public function __construct(
        public string $token = '',
        public float $logprob = 0.0,
        public ?array $bytes = null,
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'token' => 'required|string',
            'logprob' => 'required|numeric',
            'bytes' => 'nullable|array',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        return new self(
            token: $data['token'] ?? '',
            logprob: $data['logprob'] ?? 0.0,
            bytes: $data['bytes'] ?? null,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'token' => $this->token,
            'logprob' => $this->logprob,
        ];

        if ($this->bytes !== null) {
            $result['bytes'] = $this->bytes;
        }

        return $result;
    }
}
