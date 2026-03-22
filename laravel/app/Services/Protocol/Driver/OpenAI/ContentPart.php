<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\Convertible;
use App\Services\Protocol\Driver\Concerns\JsonSerializiable;
use App\Services\Shared\DTO\ContentBlock as SharedContentBlock;

/**
 * OpenAI 内容部分结构体
 *
 * 用于表示消息中的多模态内容块（文本、图片等）
 * 与 ContentBlock 等价，提供更语义化的命名
 *
 * @see https://platform.openai.com/docs/api-reference/chat/create#chat-create-messages-content
 */
class ContentPart
{
    use Convertible;
    use JsonSerializiable;

    /**
     * @param  string  $type  类型（text|image_url|input_image|input_file）
     * @param  string|null  $text  文本内容（type=text 时）
     * @param  array|null  $image_url  图片URL配置（type=image_url 时）
     * @param  string|null  $image_data  Base64 图片数据（type=input_image 时）
     * @param  string|null  $file_id  文件ID（type=input_file 时）
     * @param  array|null  $additionalData  额外数据
     */
    public function __construct(
        public string $type = 'text',
        public ?string $text = null,
        public ?array $image_url = null,
        public ?string $image_data = null,
        public ?string $file_id = null,
        public array $additionalData = [],
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'type' => 'required|string',
            'text' => 'required_if:type,text|nullable|string',
            'image_url' => 'required_if:type,image_url|nullable|array',
            'image_data' => 'required_if:type,input_image|nullable|string',
            'file_id' => 'required_if:type,input_file|nullable|string',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        $knownKeys = ['type', 'text', 'image_url', 'image_data', 'file_id'];
        $additionalData = array_diff_key($data, array_flip($knownKeys));

        return new self(
            type: $data['type'] ?? 'text',
            text: $data['text'] ?? null,
            image_url: $data['image_url'] ?? null,
            image_data: $data['image_data'] ?? null,
            file_id: $data['file_id'] ?? null,
            additionalData: $additionalData,
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

        if ($this->text !== null) {
            $result['text'] = $this->text;
        }

        if ($this->image_url !== null) {
            $result['image_url'] = $this->image_url;
        }

        if ($this->image_data !== null) {
            $result['image_data'] = $this->image_data;
        }

        if ($this->file_id !== null) {
            $result['file_id'] = $this->file_id;
        }

        return array_merge($result, $this->additionalData);
    }

    /**
     * 转换为 Shared\DTO
     */
    public function toSharedDTO(): SharedContentBlock
    {
        return SharedContentBlock::fromOpenAI($this->toArray());
    }

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(object $dto): static
    {
        $data = $dto->toOpenAI();

        return self::fromArray($data);
    }

    /**
     * 创建文本内容部分
     */
    public static function text(string $text): static
    {
        return new self(type: 'text', text: $text);
    }

    /**
     * 创建图片URL内容部分
     */
    public static function imageUrl(string $url, ?string $detail = null): static
    {
        $image_url = ['url' => $url];
        if ($detail !== null) {
            $image_url['detail'] = $detail;
        }

        return new self(type: 'image_url', image_url: $image_url);
    }
}
