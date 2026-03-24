<?php

namespace App\Services\Shared\DTO;

/**
 * 内容块 DTO（Anthropic 特有）
 *
 * 纯数据容器，不包含业务逻辑
 */
class ContentBlock
{
    /**
     * 内容块类型
     *
     * @var string (text|image|tool_use|tool_result|thinking)
     */
    public string $type;

    /**
     * 文本内容
     */
    public ?string $text = null;

    /**
     * 文本引用列表（PDF、纯文本、内容块引用）
     */
    public ?array $citations = null;

    /**
     * 图片/音频来源
     */
    public ?array $source = null;

    /**
     * 图片 URL
     */
    public ?string $imageUrl = null;

    /**
     * 图片详情级别
     */
    public ?string $detail = null;

    /**
     * 音频数据
     */
    public ?string $audioData = null;

    /**
     * 音频格式
     */
    public ?string $audioFormat = null;

    /**
     * 工具调用 ID
     */
    public ?string $toolId = null;

    /**
     * 工具名称
     */
    public ?string $toolName = null;

    /**
     * 工具输入参数
     */
    public ?array $toolInput = null;

    /**
     * 工具调用者信息
     *
     * @var array|null (direct|server_tool)
     */
    public ?array $caller = null;

    /**
     * 工具结果 ID
     */
    public ?string $toolResultId = null;

    /**
     * 工具结果内容
     */
    public ?string $toolResultContent = null;

    /**
     * 工具结果是否为错误
     */
    public ?bool $toolResultIsError = null;

    /**
     * 思考内容
     */
    public ?string $thinking = null;

    /**
     * 签名
     */
    public ?string $signature = null;

    /**
     * 缓存控制
     */
    public ?array $cacheControl = null;

    /**
     * 从数组创建
     */
    public static function fromArray(array $block): self
    {
        $dto = new self;
        $dto->type = $block['type'] ?? 'text';
        $dto->text = $block['text'] ?? null;
        $dto->citations = $block['citations'] ?? null;
        $dto->source = $block['source'] ?? null;
        $dto->imageUrl = $block['image_url']['url'] ?? $block['image_url'] ?? null;
        $dto->detail = $block['image_url']['detail'] ?? $block['detail'] ?? null;
        $dto->audioData = $block['audio_data'] ?? $block['input_audio']['data'] ?? null;
        $dto->audioFormat = $block['audio_format'] ?? $block['input_audio']['format'] ?? null;
        $dto->toolId = $block['tool_id'] ?? $block['id'] ?? null;
        $dto->toolName = $block['tool_name'] ?? $block['name'] ?? null;
        $dto->toolInput = $block['tool_input'] ?? $block['input'] ?? null;
        $dto->caller = $block['caller'] ?? null;
        $dto->toolResultId = $block['tool_result_id'] ?? $block['tool_use_id'] ?? null;
        $dto->toolResultContent = $block['tool_result_content'] ?? (is_array($block['content'] ?? null) ? json_encode($block['content']) : ($block['content'] ?? null));
        $dto->toolResultIsError = $block['tool_result_is_error'] ?? $block['is_error'] ?? null;
        $dto->thinking = $block['thinking'] ?? null;
        $dto->signature = $block['signature'] ?? null;
        $dto->cacheControl = $block['cache_control'] ?? $block['cacheControl'] ?? null;

        return $dto;
    }
}
