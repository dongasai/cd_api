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
        $dto = new SharedContentBlock;
        $dto->type = $this->type;
        $dto->text = $this->text;
        $dto->imageUrl = $this->image_url['url'] ?? null;
        $dto->detail = $this->image_url['detail'] ?? null;
        $dto->audioData = $this->image_data;
        $dto->audioFormat = null;

        return $dto;
    }

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(object $dto): static
    {
        // 直接读取 DTO 属性，不调用 toOpenAI()
        // 注意：thinking/tool_use/tool_result 是 Anthropic 特有类型，不应出现在 OpenAI content 中
        return match ($dto->type) {
            'text', 'input_text', 'output_text' => new self(  // Responses API 格式转换为 text
                type: 'text',
                text: $dto->text ?? '',
            ),
            'image', 'image_url', 'input_image' => new self(  // input_image 也转换为 image_url
                type: 'image_url',
                image_url: array_filter([
                    'url' => $dto->imageUrl ?? $dto->source['url'] ?? $dto->source ?? null,
                    'detail' => $dto->detail,
                ], fn ($v) => $v !== null),
            ),
            'audio', 'input_audio' => new self(
                type: 'input_audio',
                image_data: $dto->audioData,
            ),
            // Anthropic 特有类型转换为文本，保留标记以便识别
            'thinking' => new self(
                type: 'text',
                text: $dto->thinking ?? '',
            ),
            // tool_use 和 tool_result 不应出现在 content 中
            // 它们应该被转换为 tool_calls 或独立的 tool 消息
            'tool_use', 'tool_result' => new self(
                type: 'text',
                text: '', // 空内容，这些类型应由上层逻辑处理
            ),
            default => new self(
                type: in_array($dto->type, ['input_text', 'output_text']) ? 'text' : $dto->type,
                text: $dto->text,
            ),
        };
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
