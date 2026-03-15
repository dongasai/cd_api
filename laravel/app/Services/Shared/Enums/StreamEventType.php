<?php

namespace App\Services\Shared\Enums;

/**
 * 流式事件类型枚举
 */
enum StreamEventType: string
{
    case Start = 'start';                        // 流开始
    case ContentDelta = 'content_delta';         // 内容增量
    case ReasoningDelta = 'reasoning_delta';     // 推理内容增量（Claude/DeepSeek）
    case ToolUse = 'tool_use';                   // 工具调用
    case ToolUseInputDelta = 'tool_use_input_delta'; // 工具调用输入增量
    case Finish = 'finish';                      // 流结束
    case Error = 'error';                        // 错误
    case Ping = 'ping';                          // 心跳

    /**
     * 判断是否为内容类型事件
     */
    public function isContent(): bool
    {
        return in_array($this, [
            self::ContentDelta,
            self::ReasoningDelta,
            self::ToolUse,
            self::ToolUseInputDelta,
        ]);
    }

    /**
     * 判断是否为控制类型事件
     */
    public function isControl(): bool
    {
        return in_array($this, [
            self::Start,
            self::Finish,
            self::Error,
            self::Ping,
        ]);
    }
}
