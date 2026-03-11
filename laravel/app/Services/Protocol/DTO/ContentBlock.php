<?php

namespace App\Services\Protocol\DTO;

/**
 * 多模态内容块
 */
class ContentBlock
{
    public function __construct(
        public string $type,

        public ?string $text = null,

        public ?array $source = null,

        public ?string $imageUrl = null,
        public ?string $detail = null,

        public ?string $audioData = null,
        public ?string $audioFormat = null,

        public ?string $toolId = null,
        public ?string $toolName = null,
        public ?array $toolInput = null,

        public ?string $toolResultId = null,
        public ?string $toolResultContent = null,
        public ?bool $toolResultIsError = null,

        public ?string $thinking = null,

        // Anthropic 特有字段，部分上游 API 不支持
        public ?array $cacheControl = null,
    ) {}

    /**
     * 从 OpenAI 格式创建
     */
    public static function fromOpenAI(array $block): self
    {
        $type = $block['type'] ?? 'text';

        return match ($type) {
            'text' => new self(
                type: 'text',
                text: $block['text'] ?? '',
            ),
            'image_url' => new self(
                type: 'image_url',
                imageUrl: $block['image_url']['url'] ?? null,
                detail: $block['image_url']['detail'] ?? null,
            ),
            'input_audio' => new self(
                type: 'audio',
                audioData: $block['input_audio']['data'] ?? null,
                audioFormat: $block['input_audio']['format'] ?? null,
            ),
            default => new self(type: $type, text: $block['text'] ?? null),
        };
    }

    /**
     * 从 Anthropic 格式创建
     */
    public static function fromAnthropic(array $block): self
    {
        $type = $block['type'] ?? 'text';
        $cacheControl = $block['cache_control'] ?? null;

        return match ($type) {
            'text' => new self(
                type: 'text',
                text: $block['text'] ?? '',
                cacheControl: $cacheControl,
            ),
            'image' => new self(
                type: 'image',
                source: $block['source'] ?? null,
                cacheControl: $cacheControl,
            ),
            'tool_use' => new self(
                type: 'tool_use',
                toolId: $block['id'] ?? null,
                toolName: $block['name'] ?? null,
                toolInput: $block['input'] ?? null,
                cacheControl: $cacheControl,
            ),
            'tool_result' => new self(
                type: 'tool_result',
                toolResultId: $block['tool_use_id'] ?? null,
                toolResultContent: is_array($block['content'] ?? null)
                    ? json_encode($block['content'])
                    : $block['content'],
                toolResultIsError: $block['is_error'] ?? false,
                cacheControl: $cacheControl,
            ),
            'thinking' => new self(
                type: 'thinking',
                thinking: $block['thinking'] ?? '',
                cacheControl: $cacheControl,
            ),
            default => new self(type: $type, text: $block['text'] ?? null, cacheControl: $cacheControl),
        };
    }

    /**
     * 转换为 OpenAI 格式
     */
    public function toOpenAI(): array
    {
        return match ($this->type) {
            'text' => [
                'type' => 'text',
                'text' => $this->text ?? '',
            ],
            'image', 'image_url' => [
                'type' => 'image_url',
                'image_url' => array_filter([
                    'url' => $this->imageUrl ?? $this->source['url'] ?? null,
                    'detail' => $this->detail,
                ], fn ($v) => $v !== null),
            ],
            'audio' => [
                'type' => 'input_audio',
                'input_audio' => array_filter([
                    'data' => $this->audioData,
                    'format' => $this->audioFormat,
                ], fn ($v) => $v !== null),
            ],
            'tool_result' => [
                'type' => 'text',
                'text' => $this->toolResultContent ?? '',
            ],
            'tool_use' => [
                'type' => 'text',
                'text' => json_encode([
                    'tool_call_id' => $this->toolId,
                    'name' => $this->toolName,
                    'arguments' => $this->toolInput,
                ], JSON_UNESCAPED_UNICODE),
            ],
            'thinking' => [
                'type' => 'text',
                'text' => $this->thinking ?? '',
            ],
            default => [
                'type' => 'text',
                'text' => $this->text ?? '',
            ],
        };
    }

    /**
     * 转换为 Anthropic 格式
     *
     * @param  bool  $includeCacheControl  是否包含 cache_control 字段（部分上游 API 不支持）
     */
    public function toAnthropic(bool $includeCacheControl = true): array
    {
        $result = match ($this->type) {
            'text' => [
                'type' => 'text',
                'text' => $this->text ?? '',
            ],
            'image', 'image_url' => [
                'type' => 'image',
                'source' => $this->source ?? [
                    'type' => 'url',
                    'url' => $this->imageUrl,
                ],
            ],
            'tool_use' => [
                'type' => 'tool_use',
                'id' => $this->toolId,
                'name' => $this->toolName,
                'input' => $this->toolInput ?? [],
            ],
            'tool_result' => [
                'tool_use_id' => $this->toolResultId,
                'type' => 'tool_result',
                'content' => $this->toolResultContent,
                'is_error' => $this->toolResultIsError,
            ],
            'thinking' => [
                'type' => 'thinking',
                'thinking' => $this->thinking ?? '',
            ],
            default => [
                'type' => $this->type,
                'text' => $this->text,
            ],
        };

        // 只有明确要求时才包含 cache_control
        if ($includeCacheControl && $this->cacheControl !== null) {
            $result['cache_control'] = $this->cacheControl;
        }

        return $result;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'text' => $this->text,
            'source' => $this->source,
            'image_url' => $this->imageUrl,
            'detail' => $this->detail,
            'audio_data' => $this->audioData,
            'audio_format' => $this->audioFormat,
            'tool_id' => $this->toolId,
            'tool_name' => $this->toolName,
            'tool_input' => $this->toolInput,
            'tool_result_id' => $this->toolResultId,
            'tool_result_content' => $this->toolResultContent,
            'tool_result_is_error' => $this->toolResultIsError,
            'thinking' => $this->thinking,
        ];
    }
}
