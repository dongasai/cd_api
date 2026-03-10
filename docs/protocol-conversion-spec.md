# OpenAI/Anthropic 协议转换规范

## 概述

本文档定义了 CdApi 代理中 OpenAI 与 Anthropic 两种协议之间的转换规则，确保在协议转换过程中保持数据完整性和兼容性。

## 协议格式对比

### OpenAI Chat Completions API

```json
{
  "model": "gpt-4",
  "messages": [
    {"role": "system", "content": "你是一个助手"},
    {"role": "user", "content": "你好"}
  ],
  "temperature": 0.7,
  "top_p": 1.0,
  "max_tokens": 1000,
  "stop": ["\n\n"],
  "stream": false,
  "tools": [...],
  "tool_choice": "auto",
  "user": "user-123"
}
```

### Anthropic Messages API

```json
{
  "model": "claude-3-5-sonnet-20241022",
  "max_tokens": 1000,
  "messages": [
    {"role": "user", "content": "你好"}
  ],
  "system": "你是一个助手",
  "temperature": 0.7,
  "top_p": 1.0,
  "top_k": 5,
  "stop_sequences": ["\n\n"],
  "stream": false,
  "tools": [...],
  "tool_choice": {"type": "auto"},
  "metadata": {"user_id": "user-123"}
}
```

## 字段映射关系

### 基础字段映射表

| OpenAI 字段 | Anthropic 字段 | 转换规则 |
|------------|---------------|---------|
| `model` | `model` | 直接映射 |
| `messages` | `messages` | 需要转换格式（见下文） |
| `messages[].role=system` | `system` | system 消息提取到独立字段 |
| `temperature` | `temperature` | 直接映射 |
| `top_p` | `top_p` | 直接映射 |
| `max_tokens` | `max_tokens` | 直接映射 |
| `stop` | `stop_sequences` | 字段名变更 |
| `stream` | `stream` | 直接映射 |
| `tools` | `tools` | 需要转换格式（见下文） |
| `tool_choice` | `tool_choice` | 需要转换格式（见下文） |
| `user` | `metadata.user_id` | 嵌套到 metadata |

### Anthropic 特有字段

| 字段 | 说明 | 处理方式 |
|-----|------|---------|
| `top_k` | Anthropic 特有采样参数 | 保留到 additionalParams |
| `thinking` | Anthropic 思考功能 | 保留到 additionalParams |
| `output_config` | Anthropic 输出配置 | 保留到 additionalParams |
| `beta` | Anthropic Beta 功能标志 | 保留到 additionalParams |
| `metadata` | 元数据容器 | 部分提取 (user_id)，其余保留 |

## StandardRequest DTO 设计

### 核心属性

```php
class StandardRequest
{
    // 核心字段
    public string $model;
    public array $messages;          // StandardMessage[]
    public string|array|null $systemPrompt;

    // 采样参数
    public ?float $temperature;
    public ?float $topP;
    public ?int $topK;               // Anthropic 特有

    // 输出限制
    public ?int $maxTokens;
    public ?int $maxOutputTokens;

    // 停止序列
    public ?array $stopSequences;

    // 流式
    public bool $stream;

    // 工具调用
    public ?array $tools;
    public string|array|null $toolChoice;

    // 其他参数（用于保留协议特有参数）
    public array $additionalParams;

    // 用户标识
    public ?string $user;

    // 多模态
    public bool $hasImages;
    public bool $hasAudio;

    // 原始请求
    public ?array $rawRequest;
}
```

## 转换流程

### OpenAI → Anthropic 转换流程

```
1. fromOpenAI() - 解析 OpenAI 请求
   ├── 提取 system 消息到 systemPrompt
   ├── 过滤掉 system 角色的消息
   ├── 解析 tools 和 toolChoice
   └── 收集 additionalParams（排除已知字段）

2. toAnthropic() - 构建 Anthropic 请求
   ├── 构建基础请求 (model, messages, max_tokens)
   ├── 添加 system 字段（如果有）
   ├── 添加标准字段 (temperature, top_p, etc.)
   ├── 转换 tools 和 toolChoice
   └── 合并 additionalParams（保留特有参数）
```

### Anthropic → OpenAI 转换流程

```
1. fromAnthropic() - 解析 Anthropic 请求
   ├── 解析 messages（处理 tool_result）
   ├── 提取 system 到 systemPrompt
   ├── 解析 tools 和 toolChoice
   └── 收集 additionalParams（排除已知字段）

2. toOpenAI() - 构建 OpenAI 请求
   ├── 构建基础请求 (model, messages)
   ├── systemPrompt 转为 messages[0]（如果有）
   ├── 添加标准字段
   ├── 转换 tools 和 toolChoice
   └── 合并 additionalParams（保留特有参数）
```

## 消息格式转换

### OpenAI 消息格式

```json
{
  "role": "user",
  "content": [
    {"type": "text", "text": "这是什么？"},
    {"type": "image_url", "url": "data:image/..."}
  ]
}
```

### Anthropic 消息格式

```json
{
  "role": "user",
  "content": [
    {"type": "text", "text": "这是什么？"},
    {"type": "image", "source": {"type": "base64", "media_type": "image/png", "data": "..."}}
  ]
}
```

### 多模态内容映射

| OpenAI 类型 | Anthropic 类型 | 转换规则 |
|------------|---------------|---------|
| `text` | `text` | 直接映射 |
| `image_url` | `image` | 需要转换格式 |
| `input_audio` | `audio` | 需要转换格式 |

## 工具格式转换

### OpenAI 工具格式

```json
{
  "type": "function",
  "function": {
    "name": "get_weather",
    "description": "获取天气",
    "parameters": {
      "type": "object",
      "properties": {...},
      "required": ["location"]
    }
  }
}
```

### Anthropic 工具格式

```json
{
  "name": "get_weather",
  "description": "获取天气",
  "input_schema": {
    "type": "object",
    "properties": {...},
    "required": ["location"]
  }
}
```

### 工具字段映射

| OpenAI | Anthropic | 说明 |
|-------|----------|------|
| `function.name` | `name` | 直接映射 |
| `function.description` | `description` | 直接映射 |
| `function.parameters` | `input_schema` | 字段名变更 |

## 额外参数处理策略

### additionalParams 排除列表

在 `fromOpenAI()` 和 `fromAnthropic()` 中，以下字段**不**会被收集到 additionalParams：

**OpenAI 排除列表：**
```php
['model', 'messages', 'temperature', 'top_p', 'max_tokens',
 'stop', 'stream', 'tools', 'tool_choice', 'user',
 'presence_penalty', 'frequency_penalty', 'logit_bias', 'n',
 'response_format', 'seed']
```

**Anthropic 排除列表：**
```php
['model', 'messages', 'system', 'temperature', 'top_p', 'top_k',
 'max_tokens', 'stop_sequences', 'stream', 'tools', 'tool_choice',
 'metadata', 'thinking', 'output_config', 'beta']
```

### 保留策略

1. **协议特有参数**：如 Anthropic 的 `thinking`、`output_config`、`beta` 等
2. **未来扩展参数**：任何不在排除列表中的新参数
3. **元数据**：`metadata` 中除 `user_id` 外的其他字段

## 常见问题

### Q1: 为什么需要 additionalParams？

**A:** 为了支持协议的渐进式演进。当上游 API 添加新参数时，代理层不需要立即更新转换逻辑，而是通过 additionalParams 透明传递这些参数。

### Q2: 如何处理参数冲突？

**A:** 在 `toOpenAI()` 和 `toAnthropic()` 中，标准字段的优先级高于 additionalParams 中的同名字段。通过 `array_merge($this->additionalParams, $request)` 确保标准字段覆盖 additionalParams 中的值。

### Q3: 如何处理 system 消息？

**A:**
- OpenAI: system 是 messages 数组中的一个元素
- Anthropic: system 是独立的顶层字段
- 转换时需要提取/插入 system 消息

### Q4: 如何处理 tool_result？

**A:**
- Anthropic: tool_result 是 user 消息的 content 块
- OpenAI: tool 是独立的消息角色
- 转换时需要拆分/合并消息

## 实现示例

### 从 Anthropic 创建 StandardRequest

```php
// 原始请求
$anthropicRequest = [
    'model' => 'claude-3-5-sonnet-20241022',
    'messages' => [...],
    'system' => '...',
    'max_tokens' => 32000,
    'thinking' => ['type' => 'adaptive'],
    'output_config' => ['effort' => 'medium'],
    'beta' => 'true',
];

// 创建 StandardRequest
$standardRequest = StandardRequest::fromAnthropic($anthropicRequest);

// thinking, output_config, beta 会被收集到 additionalParams
```

### 转换为 Anthropic 格式

```php
// 转换回 Anthropic 格式
$anthropicRequest = $standardRequest->toAnthropic();

// 结果会包含 thinking, output_config, beta
// 因为 array_merge($this->additionalParams, $request)
```

## 更新记录

| 日期 | 变更 |
|------|------|
| 2026-03-10 | 初始版本，添加 additionalParams 保留机制 |
| 2026-03-10 | 参考 new-api 设计，补充 Thinking 模式转换规则 |

## 附录：参考实现对比

### new-api (Go) vs CdApi (PHP)

| 方面 | new-api | CdApi |
|------|---------|-------|
| 转换方式 | 直接转换 (Claude ↔ OpenAI) | DTO 中转 (Claude → Standard → OpenAI) |
| 参数保留 | 显式字段映射 | `additionalParams` 自动保留 |
| 扩展性 | 需要添加新字段 | 自动保留未知参数 |

### Thinking 模式映射

| Anthropic | OpenAI | 说明 |
|-----------|--------|------|
| `thinking.type: "enabled"` | `reasoning_effort: "high"` | 启用思考模式 |
| `thinking.type: "adaptive"` | `reasoning_effort: "medium"` | 自适应思考 |
| 无 thinking | 无 reasoning_effort | 默认不启用 |
