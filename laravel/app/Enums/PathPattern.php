<?php

namespace App\Enums;

/**
 * API 路径匹配模式枚举
 */
enum PathPattern: string
{
    case ANY = '';
    case CHAT_COMPLETIONS = 'api/openai/v1/chat/completions';
    case COMPLETIONS = 'api/openai/v1/completions';
    case EMBEDDINGS = 'api/openai/v1/embeddings';
    case MODELS = 'api/openai/v1/models';
    case ANTHROPIC_MESSAGES = 'api/anthropic/v1/messages';

    /**
     * 获取路径标签
     */
    public function label(): string
    {
        return match ($this) {
            self::ANY => '不限',
            self::CHAT_COMPLETIONS => '/v1/chat/completions (对话补全)',
            self::COMPLETIONS => '/v1/completions (文本补全)',
            self::EMBEDDINGS => '/v1/embeddings (向量嵌入)',
            self::MODELS => '/v1/models (模型列表)',
            self::ANTHROPIC_MESSAGES => '/v1/messages (Anthropic消息)',
        };
    }

    /**
     * 获取所有选项
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * 转换为正则表达式数组
     */
    public function toPatterns(): ?array
    {
        if ($this === self::ANY) {
            return null;
        }

        return [$this->value];
    }
}
