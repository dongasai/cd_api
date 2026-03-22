<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;

/**
 * OpenAI Logprobs 内容项结构体
 *
 * 表示单个 token 的对数概率信息
 *
 * @see https://platform.openai.com/docs/api-reference/chat/object#chat/object-choices-logprobs-content
 */
class LogprobsContent
{
    use JsonSerializiable;

    /**
     * @param  string  $token  token 文本
     * @param  float  $logprob  对数概率值
     * @param  int[]|null  $bytes  token 的字节表示
     * @param  LogprobsTopLogprob[]|null  $topLogprobs  最可能的替代 token（可选）
     */
    public function __construct(
        public string $token = '',
        public float $logprob = 0.0,
        public ?array $bytes = null,
        public ?array $topLogprobs = null,
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
            'top_logprobs' => 'nullable|array',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        $topLogprobs = null;
        if (isset($data['top_logprobs']) && is_array($data['top_logprobs'])) {
            $topLogprobs = array_map(
                fn (array $item) => LogprobsTopLogprob::fromArray($item),
                $data['top_logprobs']
            );
        }

        return new self(
            token: $data['token'] ?? '',
            logprob: $data['logprob'] ?? 0.0,
            bytes: $data['bytes'] ?? null,
            topLogprobs: $topLogprobs,
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

        if ($this->topLogprobs !== null) {
            $result['top_logprobs'] = array_map(
                fn (LogprobsTopLogprob $item) => $item->toArray(),
                $this->topLogprobs
            );
        }

        return $result;
    }
}
