<?php

namespace App\Services\Shared\DTO;

/**
 * 统一工具定义 DTO
 *
 * 纯数据容器，不包含业务逻辑
 * 作为 OpenAI 和 Anthropic 工具格式的中间格式
 */
class Tool
{
    /**
     * 工具名称
     */
    public string $name = '';

    /**
     * 参数定义（JSON Schema 格式）
     */
    public array $parameters = [];

    /**
     * 工具描述
     */
    public ?string $description = null;

    /**
     * 额外字段（透传）
     */
    public array $additionalData = [];
}
