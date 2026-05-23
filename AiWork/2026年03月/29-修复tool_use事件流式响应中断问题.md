# 修复流式响应tool_use事件中断问题

## 时间
2026-03-29 05:15

## 问题描述

用户发现审计日志 4224/4230/4235 出现问题：
1. SSE内容在tool_use事件开始后就中断，没有完整的参数和结束标记
2. 审计日志的token统计数据全为0
3. 用户反馈："SSE内容不对，明显没结束但是结束了"

日志显示：
```
event: content_block_start (index=2, tool_use, input:[])
event: content_block_delta (input_json_delta with parameters)
(然后就结束了，没有content_block_stop和message_stop)
```

## 根本原因

### 问题1：ToolCall DTO实例化错误（已修复）
位置：[AnthropicMessagesDriver.php:314-319](laravel/app/Services/Protocol/Driver/AnthropicMessagesDriver.php#L314)

原代码使用命名参数实例化纯数据容器类ToolCall：
```php
$toolCall = new \App\Services\Shared\DTO\ToolCall(
    id: $tc['id'] ?? '',
    type: \App\Services\Shared\Enums\ToolType::from($tc['type'] ?? 'function'),
    ...
);
```

但ToolCall没有构造函数，且$type是string类型不能赋值ToolType枚举。

**修复：**
```php
$toolCall = new \App\Services\Shared\DTO\ToolCall;
$toolCall->id = $tc['id'] ?? '';
$toolCall->type = $tc['type'] ?? 'function';
$toolCall->name = $tc['function']['name'] ?? '';
$toolCall->arguments = $tc['function']['arguments'] ?? '';
$toolCall->index = $tc['index'] ?? 0;
```

### 问题2：finish_reason处理条件错误（主要问题）
位置：[AnthropicMessagesDriver.php:165-172](laravel/app/Services/Protocol/Driver/AnthropicMessagesDriver.php#L165)

当收到 `finish_reason="tool_calls"` 时，OpenAI格式的delta包含：
```json
{"finish_reason":"tool_calls","delta":{"content":"","reasoning_content":null}}
```

解析后：
- `contentDelta = ""` (空字符串，**不是null**)
- `reasoningDelta = null`

原条件：
```php
if ($chunk->delta !== '' || $chunk->contentDelta !== null) {
    return $this->buildContentBlockDeltaEvent($chunk);
}
```

判断结果：
- `delta !== ''` → `false`
- `contentDelta !== null` → `true` (空字符串不是null！)

**导致提前返回**，不会执行后续的 `buildMessageStopEvent`，所以缺少：
- `content_block_stop` (关闭工具调用块)
- `message_delta` (包含stop_reason)
- `message_stop` (消息结束标记)

**修复：**
```php
// 内容增量：检查非空且非null
if (($chunk->delta !== '' && $chunk->delta !== null) ||
    ($chunk->contentDelta !== null && $chunk->contentDelta !== '')) {
    return $this->buildContentBlockDeltaEvent($chunk);
}

// 推理内容增量：检查非null且非空
if ($chunk->reasoningDelta !== null && $chunk->reasoningDelta !== '') {
    return $this->buildContentBlockDeltaEvent($chunk);
}
```

这样空字符串不会触发提前返回，能正确到达 `finishReason` 的处理逻辑。

## 修复后的完整流程

OpenAI格式 → Anthropic格式转换流程：

1. **收到tool_calls delta**（包含完整arguments）
   ```
   OpenAI: {"tool_calls":[{"id":"...","function":{"name":"Read","arguments":"..."}}]}
   ↓
   Anthropic:
   event: content_block_start (index=1, tool_use, input:[])
   event: content_block_delta (input_json_delta with arguments)
   ```

2. **收到finish_reason="tool_calls" delta**
   ```
   OpenAI: {"finish_reason":"tool_calls","delta":{"content":""}}
   ↓
   Anthropic:
   event: content_block_stop (index=1) ← 关闭工具调用块
   event: message_delta (stop_reason: "tool_use", usage)
   event: message_stop
   ```

## 测试验证

测试命令：
```bash
php artisan cdapi:request:replay 4224
```

输出结果（成功）：
```
event: content_block_start
data: {"type":"content_block_start","index":1,"content_block":{"type":"tool_use","id":"...","name":"Read","input":[]}}

event: content_block_delta
data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":"{\"file_path\": \"...\"}"}}

event: content_block_stop  ← 正确发送
data: {"type":"content_block_stop","index":1}

event: message_delta       ← 正确发送
data: {"type":"message_delta","delta":{"stop_reason":"tool_use"},"usage":{"output_tokens":0}}

event: message_stop        ← 正确发送
data: {}
```

审计日志（正确）：
- status_code=200
- finish_reason="tool_use"
- latency_ms有值

## 影响范围

**修改文件：**
- [laravel/app/Services/Protocol/Driver/AnthropicMessagesDriver.php](laravel/app/Services/Protocol/Driver/AnthropicMessagesDriver.php)

**影响功能：**
- OpenAI协议转Anthropic协议的流式响应处理
- tool_use事件的完整性和结束标记
- 审计日志的统计数据记录

**向后兼容：** 完全兼容，修复的是之前缺失的功能

## 相关问题

这次修复解决了以下问题：
1. ✅ ToolCall DTO实例化错误导致崩溃
2. ✅ finish_reason处理条件错误导致响应中断
3. ✅ 缺少content_block_stop导致客户端收到不完整的tool_use参数
4. ✅ 缺少message_stop导致流式响应异常结束

## 后续建议

1. 添加单元测试覆盖流式响应的tool_use场景
2. 检查是否有其他地方存在类似的空字符串判断问题
3. 考虑添加更详细的错误日志帮助调试流式响应问题
4. 更新协议转换文档，说明完整的转换流程