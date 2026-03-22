<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;

/**
 * OpenAI 图片响应结构体
 *
 * 用于响应消息中的 images 字段，包含生成的图片信息
 *
 * @see https://platform.openai.com/docs/api-reference/chat/object#chat/object-choices-message-images
 */
class MessageImage
{
    use JsonSerializiable;

    /**
     * @param  array  $imageUrl  图片 URL 信息（包含 url 和 detail）
     * @param  int  $index  图片索引
     * @param  string  $type  类型
     */
    public function __construct(
        public array $imageUrl = [],
        public int $index = 0,
        public string $type = '',
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'image_url' => 'required|array',
            'index' => 'required|integer',
            'type' => 'required|string',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        return new self(
            imageUrl: $data['image_url'] ?? [],
            index: $data['index'] ?? 0,
            type: $data['type'] ?? '',
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'image_url' => $this->imageUrl,
            'index' => $this->index,
            'type' => $this->type,
        ];
    }
}
