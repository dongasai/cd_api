<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;

/**
 * OpenAI Logprobs 结构体
 *
 * 包含 token 的对数概率信息
 *
 * @see https://platform.openai.com/docs/api-reference/chat/object#chat/object-choices-logprobs
 */
class Logprobs
{
    use JsonSerializiable;

    /**
     * @param  LogprobsContent[]|null  $content  内容 token 的 logprobs 列表
     */
    public function __construct(
        public ?array $content = null,
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'content' => 'nullable|array',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        $content = null;
        if (isset($data['content']) && is_array($data['content'])) {
            $content = array_map(
                fn (array $item) => LogprobsContent::fromArray($item),
                $data['content']
            );
        }

        return new self(
            content: $content,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content !== null
                ? array_map(fn (LogprobsContent $item) => $item->toArray(), $this->content)
                : null,
        ];
    }
}
