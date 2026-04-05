<?php

namespace App\Services\Shared\Enums;

/**
 * 消息角色枚举
 */
enum MessageRole: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';
    case Developer = 'developer';  // OpenAI Responses API 特有角色

    /**
     * 判断是否为系统角色
     */
    public function isSystem(): bool
    {
        return $this === self::System;
    }

    /**
     * 判断是否为用户角色
     */
    public function isUser(): bool
    {
        return $this === self::User;
    }

    /**
     * 判断是否为助手角色
     */
    public function isAssistant(): bool
    {
        return $this === self::Assistant;
    }

    /**
     * 判断是否为工具角色
     */
    public function isTool(): bool
    {
        return $this === self::Tool;
    }
}
