# StreamChunk 流式响应块

## 概述

`StreamChunk` 是CdApi项目中的统一流式数据传输对象(DTO)，用于在不同AI协议（OpenAI、Anthropic等）之间进行流式响应数据的转换和传递。

**文件位置：** [laravel/app/Services/Shared/DTO/StreamChunk.php](laravel/app/Services/Shared/DTO/StreamChunk.php)

## 数据结构

### 核心字段

| 字段 | 类型 | 说明 | 示例 |
|------|------|------|------|
| `id` | `string` | 响应唯一标识 | `"chatcmpl-abc123"` |
| `model` | `string` | 模型名称 | `"gpt-4"`, `"claude-3-opus"` |
| `contentDelta` | `?string` | 文本内容增量 | `"Hello"` |
| `reasoningDelta` | `?string` | 推理/思考内容增量 | `"Let me think..."` |
| `finishReason` | `?FinishReason` | 结束原因枚举 | `FinishReason::Stop` |
| `index` | `?int` | 内容块索引 | `0`, `1`, `2` |
| `usage` | `?Usage` | Token使用统计 | `{inputTokens: 100, outputTokens: 50}` |

### 工具调用字段

| 字段 | 类型 | 说明 |
|------|------|------|
| `toolCall` | `?ToolCall` | 单个工具调用（Anthropic风格） |
| `toolCalls` | `?array` | 工具调用列表（OpenAI风格） |

### 元数据字段

| 字段 | 类型 | 说明 |
|------|------|------|
| `event` | `string` | 事件类型名称 |
| `data` | `array` | 原始事件数据 |
| `delta` | `string` | 内容增量（兼容字段） |
| `signature` | `?string` | 思考内容签名 |
| `isPartial` | `bool` | 是否为部分数据 |

### 错误处理字段

| 字段 | 类型 | 说明 |
|------|------|------|
| `error` | `?string` | 错误消息 |
| `errorType` | `?ErrorType` | 错误类型枚举 |
| `parseError` | `?string` | 解析错误信息 |

## 使用场景

### 1. OpenAI格式解析

当接收到OpenAI格式的流式响应时，解析为StreamChunk：

**输入（OpenAI格式）：**
```json
{
  "id": "chatcmpl-123",
  "model": "gpt-4",
  "choices": [{
    "index": 0,
    "delta": {
      "content": "Hello",
      "reasoning_content": "Thinking...",
      "tool_calls": [{
        "id": "call_abc",
        "type": "function",
        "function": {
          "name": "get_weather",
          "arguments": "{\"location\": \"Beijing\"}"
        }
      }]
    },
    "finish_reason": "tool_calls"
  }],
  "usage": {
    "prompt_tokens": 10,
    "completion_tokens": 20
  }
}
```

**输出（StreamChunk对象）：**
```php
$chunk = new StreamChunk;
$chunk->id = "chatcmpl-123";
$chunk->model = "gpt-4";
$chunk->index = 0;
$chunk->contentDelta = "Hello";
$chunk->reasoningDelta = "Thinking...";
$chunk->toolCalls = [
    [
        'id' => 'call_abc',
        'type' => 'function',
        'function' => [
            'name' => 'get_weather',
            'arguments' => '{"location": "Beijing"}'
        ]
    ]
];
$chunk->finishReason = FinishReason::ToolUse;
$chunk->usage = new Usage;
$chunk->usage->inputTokens = 10;
$chunk->usage->outputTokens = 20;
```

### 2. Anthropic格式解析

当接收到Anthropic格式的流式响应时，解析为StreamChunk：

**输入（Anthropic格式）：**
```json
{
  "type": "content_block_delta",
  "index": 0,
  "delta": {
    "type": "text_delta",
    "text": "Hello"
  }
}
```

**输出（StreamChunk对象）：**
```php
$chunk = new StreamChunk;
$chunk->event = "content_block_delta";
$chunk->index = 0;
$chunk->contentDelta = "Hello";
```

### 3. 协议转换

StreamChunk作为中间格式，实现协议转换：

```
OpenAI格式 → StreamChunk → Anthropic格式
Anthropic格式 → StreamChunk → OpenAI格式
```

## 转换示例

### OpenAI → Anthropic 转换

**场景：工具调用**

1. **OpenAI输入：**
```json
{
  "choices": [{
    "delta": {
      "tool_calls": [{
        "id": "tool-123",
        "function": {
          "name": "Read",
          "arguments": "{\"file_path\": \"/test.md\"}"
        }
      }]
    }
  }]
}
```

2. **转换为StreamChunk：**
```php
$chunk->toolCalls = [
    [
        'id' => 'tool-123',
        'function' => [
            'name' => 'Read',
            'arguments' => '{"file_path": "/test.md"}'
        ]
    ]
];
```

3. **转换为Anthropic输出：**
```
event: content_block_start
data: {"type":"content_block_start","index":1,"content_block":{"type":"tool_use","id":"tool-123","name":"Read","input":[]}}

event: content_block_delta
data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":"{\"file_path\": \"/test.md\"}"}}

event: content_block_stop
data: {"type":"content_block_stop","index":1}

event: message_delta
data: {"type":"message_delta","delta":{"stop_reason":"tool_use"},"usage":{"output_tokens":0}}

event: message_stop
data: {}
```

**重要：Index转换规则**
- **Anthropic**: index从**1**开始
- **OpenAI**: tool_calls[].index从**0**开始
- 转换时需要+1处理

### Anthropic → OpenAI 转换

**场景：文本内容**

1. **Anthropic输入：**
```
event: content_block_delta
data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"Hello"}}
```

2. **转换为StreamChunk：**
```php
$chunk->event = "content_block_delta";
$chunk->index = 0;
$chunk->contentDelta = "Hello";
```

3. **转换为OpenAI输出：**
```json
{
  "choices": [{
    "index": 0,
    "delta": {
      "content": "Hello"
    }
  }]
}
```

## 重要方法

### isEmpty()

判断是否为空数据块：

```php
public function isEmpty(): bool
{
    // 有事件类型的不是空事件
    if (! empty($this->event)) {
        return false;
    }

    // 有推理内容的不是空事件
    if (! empty($this->reasoningDelta)) {
        return false;
    }

    // 有工具调用的不是空事件
    if (! empty($this->toolCalls)) {
        return false;
    }

    // 其他情况:检查是否有实际内容
    return empty($this->delta) && empty($this->finishReason) && empty($this->usage);
}
```

### isDone()

判断是否为结束数据块：

```php
public function isDone(): bool
{
    return $this->event === 'message_stop' || $this->finishReason !== null;
}
```

## 处理流程

### 完整的流式响应处理

```
┌─────────────────┐
│  上游AI服务      │
│  (OpenAI格式)   │
└────────┬────────┘
         │ 原始SSE流
         ↓
┌─────────────────┐
│  Provider解析    │
│  parseOpenAI    │
│  StreamChunk()  │
└────────┬────────┘
         │ StreamChunk对象
         ↓
┌─────────────────┐
│  ProtocolConverter│
│  convertStream   │
│  Chunk()        │
└────────┬────────┘
         │ 转换后的SSE格式
         ↓
┌─────────────────┐
│  客户端响应      │
│  (Anthropic格式)│
└─────────────────┘
```

### 典型的转换流程

1. **接收SSE流**：从上游AI服务接收原始SSE数据
2. **解析为StreamChunk**：Provider驱动解析原始格式为StreamChunk对象
3. **协议转换**：ProtocolConverter调用目标协议驱动转换格式
4. **输出SSE流**：向客户端发送转换后的SSE数据

## 注意事项

### 空字符串vs null

**重要：** 在判断条件时要注意区分空字符串`""`和`null`：

```php
// ❌ 错误：空字符串会通过检查
if ($chunk->contentDelta !== null) {
    // contentDelta="" 时也会执行
}

// ✅ 正确：同时检查非空和非null
if ($chunk->contentDelta !== null && $chunk->contentDelta !== '') {
    // 只有有实际内容时才执行
}
```

这在处理 `finish_reason` 场景时特别重要，因为OpenAI格式可能返回：
```json
{"finish_reason":"tool_calls","delta":{"content":""}}
```

这里 `content=""`（空字符串，不是null），如果不正确处理会导致提前返回，无法执行finish_reason的逻辑。

### 工具调用的两种格式

StreamChunk支持两种工具调用格式：

1. **OpenAI风格：** `toolCalls` 数组
2. **Anthropic风格：** `toolCall` 单个对象

在转换时需要处理这两种格式的兼容。

### 流式响应的完整性

确保流式响应包含完整的结束序列：

1. `content_block_stop` - 关闭内容块
2. `message_delta` - 包含finish_reason和usage
3. `message_stop` - 消息结束标记

缺少任何一个都会导致客户端接收不完整的数据。

## 相关文件

- [StreamChunk.php](laravel/app/Services/Shared/DTO/StreamChunk.php) - DTO定义
- [ToolCall.php](laravel/app/Services/Shared/DTO/ToolCall.php) - 工具调用DTO
- [Usage.php](laravel/app/Services/Shared/DTO/Usage.php) - Token使用统计DTO
- [FinishReason.php](laravel/app/Services/Shared/Enums/FinishReason.php) - 结束原因枚举
- [AnthropicMessagesDriver.php](laravel/app/Services/Protocol/Driver/AnthropicMessagesDriver.php) - Anthropic协议驱动
- [OpenAIProvider.php](laravel/app/Services/Provider/Driver/OpenAIProvider.php) - OpenAI提供者

## 参见

- [协议转化架构](协议转化.md)
- [修复tool_use事件流式响应中断问题](../work/2026年03月/29-修复tool_use事件流式响应中断问题.md)