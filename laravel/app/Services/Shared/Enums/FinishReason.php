<?php

namespace App\Services\Shared\Enums;

/**
 * 结束原因枚举
 */
enum FinishReason: string
{
    case Stop = 'stop';                         // 自然结束（遇到 stop 序列）
    case EndTurn = 'end_turn';                  // 轮次结束（Anthropic）
    case MaxTokens = 'max_tokens';              // 达到最大 token 限制
    case ToolUse = 'tool_use';                  // 调用工具
    case StopSequence = 'stop_sequence';        // 遇到停止序列（Anthropic）

    /**
     * 转换为 OpenAI 格式
     */
    public function toOpenAI(): string
    {
        return match ($this) {
            self::EndTurn => 'stop',
            self::ToolUse => 'tool_calls',
            self::StopSequence => 'stop',
            default => $this->value,
        };
    }

    /**
     * 转换为 Anthropic 格式
     */
    public function toAnthropic(): string
    {
        return match ($this) {
            self::Stop => 'end_turn',
            self::ToolUse => 'tool_use',
            self::MaxTokens => 'max_tokens',
            default => $this->value,
        };
    }

    /**
     * 从 OpenAI 格式创建
     */
    public static function fromOpenAI(string $reason): self
    {
        return match ($reason) {
            'stop' => self::Stop,
            'tool_calls' => self::ToolUse,
            'length' => self::MaxTokens,
            default => self::Stop,
        };
    }

    /**
     * 从 Anthropic 格式创建
     */
    public static function fromAnthropic(string $reason): self
    {
        return match ($reason) {
            'end_turn' => self::EndTurn,
            'max_tokens' => self::MaxTokens,
            'stop_sequence' => self::StopSequence,
            'tool_use' => self::ToolUse,
            default => self::EndTurn,
        };
    }
}
