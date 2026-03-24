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
    case PauseTurn = 'pause_turn';              // 暂停轮次（Anthropic）
    case Refusal = 'refusal';                   // 拒绝响应（Anthropic）
}
