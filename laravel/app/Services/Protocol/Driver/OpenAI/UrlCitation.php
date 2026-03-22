<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;

/**
 * OpenAI URL 引用结构体
 *
 * 表示消息中的 URL 引用信息，用于来源标注
 *
 * @see https://platform.openai.com/docs/api-reference/chat/object#chat/object-choices-message-annotations-url_citation
 */
class UrlCitation
{
    use JsonSerializiable;

    /**
     * @param  int  $endIndex  引用结束位置
     * @param  int  $startIndex  引用开始位置
     * @param  string  $title  引用标题
     * @param  string  $url  引用 URL
     */
    public function __construct(
        public int $endIndex = 0,
        public int $startIndex = 0,
        public string $title = '',
        public string $url = '',
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'end_index' => 'required|integer|min:0',
            'start_index' => 'required|integer|min:0',
            'title' => 'required|string',
            'url' => 'required|string|url',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        return new self(
            endIndex: $data['end_index'] ?? 0,
            startIndex: $data['start_index'] ?? 0,
            title: $data['title'] ?? '',
            url: $data['url'] ?? '',
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'end_index' => $this->endIndex,
            'start_index' => $this->startIndex,
            'title' => $this->title,
            'url' => $this->url,
        ];
    }
}
