<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;

/**
 * OpenAI 注解结构体
 *
 * 用于响应消息中的 annotations 字段，包含引用信息等
 *
 * @see https://platform.openai.com/docs/api-reference/chat/object#chat/object-choices-message-annotations
 */
class Annotation
{
    use JsonSerializiable;

    /**
     * @param  string  $type  注解类型（如 url_citation）
     * @param  UrlCitation|null  $urlCitation  URL 引用详情
     */
    public function __construct(
        public string $type = '',
        public ?UrlCitation $urlCitation = null,
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'type' => 'required|string',
            'url_citation' => 'required_if:type,url_citation|nullable|array',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        $urlCitation = null;
        if (isset($data['url_citation']) && is_array($data['url_citation'])) {
            $urlCitation = UrlCitation::fromArray($data['url_citation']);
        }

        return new self(
            type: $data['type'] ?? '',
            urlCitation: $urlCitation,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
        ];

        if ($this->urlCitation !== null) {
            $result['url_citation'] = $this->urlCitation->toArray();
        }

        return $result;
    }
}
