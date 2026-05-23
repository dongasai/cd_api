# 修复流式响应 Usage 输出不完整问题

## 问题描述

用户反馈流式响应输出给客户端的 usage 信息不完整，只有 `output_tokens`，缺少 `input_tokens` 等详细信息。

虽然数据库审计日志已经正确记录了完整的 token 统计（prompt_tokens、completion_tokens、total_tokens），但客户端通过 SSE 接收到的 usage 信息不全。

## 问题分析

### 根本原因

OpenAI 格式的流式响应在最后一个 chunk 返回完整的 usage 信息：

```json
{
  "choices": [],
  "usage": {
    "prompt_tokens": 254,
    "completion_tokens": 111,
    "total_tokens": 365,
    "completion_tokens_details": {"reasoning_tokens": 77},
    "prompt_tokens_details": {"cached_tokens": 0}
  }
}
```

但转换为 Anthropic 格式后，只输出了：

```
event: message_delta
data: {"type":"message_delta","delta":{"stop_reason":null},"usage":{"output_tokens":111}}
```

缺少 `input_tokens` 等完整信息。

### Anthropic 流式规范

根据 Anthropic API 规范：
- **message_start** 事件：在流开始时发送，包含初始 usage（input_tokens 和 output_tokens 都为 0）
- **message_delta** 事件：在流过程中发送增量 usage（只有 output_tokens）
- **message_stop** 事件：流结束标记

问题在于：`message_start` 在流开始时发送，此时还不知道最终的 token 数量。最终 usage 在流结束时才返回，但没有事件能携带完整的 usage 信息给客户端。

## 解决方案

修改 `AnthropicMessagesDriver::buildStreamChunk()` 方法，在收到最终的 usage chunk 时：

1. 发送标准的 `message_delta` 事件（包含 output_tokens）
2. **额外发送更新的 `message_start` 事件**，包含完整的 usage 信息

这样客户端可以：
- 从 `message_start` 获取完整的 usage 统计（input_tokens、output_tokens、cache tokens）
- 从 `message_delta` 获取增量统计

## 实施内容

**文件**: [laravel/app/Services/Protocol/Driver/AnthropicMessagesDriver.php](laravel/app/Services/Protocol/Driver/AnthropicMessagesDriver.php:184-210)

```php
// Usage 数据块（OpenAI 格式的最后一个 chunk，只有 usage 没有 delta）
if ($chunk->usage !== null && $chunk->contentDelta === null && $chunk->delta === '' && $chunk->toolCalls === null && $chunk->finishReason === null) {
    // 发送 message_delta 事件包含标准的 usage 信息
    $usage = $chunk->usage;
    $data = [
        'type' => self::EVENT_MESSAGE_DELTA,
        'delta' => ['stop_reason' => null],
        'usage' => ['output_tokens' => $usage->outputTokens ?? 0],
    ];

    $output = $this->buildSSEEvent(self::EVENT_MESSAGE_DELTA, $this->safeJsonEncode($data));

    // 如果有 input_tokens，发送更新的 message_start 事件（包含完整 usage）
    if ($usage->inputTokens > 0) {
        $messageStart = [
            'type' => self::EVENT_MESSAGE_START,
            'message' => [
                'id' => $chunk->id,
                'type' => 'message',
                'role' => 'assistant',
                'model' => $chunk->model,
                'content' => [],
                'stop_reason' => null,
                'stop_sequence' => null,
                'usage' => [
                    'input_tokens' => $usage->inputTokens ?? 0,
                    'output_tokens' => $usage->outputTokens ?? 0,
                    'cache_read_input_tokens' => $usage->cacheReadInputTokens,
                    'cache_creation_input_tokens' => $usage->cacheCreationInputTokens,
                ],
            ],
        ];
        $output = $this->buildSSEEvent(self::EVENT_MESSAGE_START, $this->safeJsonEncode($messageStart)).$output;
    }

    return $output;
}
```

## 验证结果

重放请求后，客户端输出：

```
event: message_stop
data: {}
event: message_start
data: {"type":"message_start","message":{"usage":{"input_tokens":254,"output_tokens":123,"cache_read_input_tokens":0}}}
event: message_delta
data: {"type":"message_delta","delta":{"stop_reason":null},"usage":{"output_tokens":123}}

Token 使用:
  输入：254
  输出：123
  缓存：0
```

✅ 客户端可以正确接收完整的 usage 统计信息

## 数据库验证

审计日志正确记录：
```sql
SELECT id, prompt_tokens, completion_tokens, total_tokens
FROM audit_logs
ORDER BY id DESC LIMIT 1
```

结果：
```json
{
  "id": 4345,
  "prompt_tokens": 254,
  "completion_tokens": 111,
  "total_tokens": 365
}
```

## 技术要点

1. **协议转换时机**: OpenAI → Anthropic 转换时，usage 信息在流结束时才可用
2. **事件顺序**: 标准 Anthropic 流式响应中，message_start 在最开始发送，usage 为 0
3. **解决方案**: 在收到 usage chunk 时，发送更新的 message_start 事件携带完整统计
4. **客户端处理**: 客户端应累积所有事件的 usage 信息，或取最后一个 message_start 的值

## 修改文件

- [laravel/app/Services/Protocol/Driver/AnthropicMessagesDriver.php](laravel/app/Services/Protocol/Driver/AnthropicMessagesDriver.php)

## 相关问题

- [30-修复force_stream_option_include_usage配置未生效问题.md](30-修复force_stream_option_include_usage配置未生效问题.md)
- OpenAI `stream_options.include_usage` 参数支持
- StreamChunk 中间层的 usage 传递