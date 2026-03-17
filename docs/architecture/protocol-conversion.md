# 协议转换说明

## 概述

本文档说明 CdApi 系统中协议转换的实现机制，重点说明 OpenAI 与 Anthropic 协议之间的转换规则。

## 核心问题

### System 消息处理差异

**OpenAI 格式**:
- System 消息作为 `messages` 数组的一部分传递
- 格式: `{"role": "system", "content": "..."}`

**Anthropic 格式**:
- System 指令通过独立的 `system` 字段传递
- 格式: `{"system": "...", "messages": [...]}`

这种差异导致了一个关键问题：**OpenAI 格式的请求如何正确转换为 Anthropic 格式？**

## 解决方案

### 1. System 消息提取

在 OpenAI 协议驱动中（`OpenAiChatCompletionsDriver::parseRequest()`）实现了自动提取：

```php
foreach ($rawRequest['messages'] ?? [] as $msg) {
    // 提取 system 消息
    if (($msg['role'] ?? '') === 'system') {
        if (is_string($msg['content'] ?? null)) {
            $systemContent = $msg['content'];
        }
        // 不添加到 messages 数组，跳过 system 消息
        continue;
    }

    // 处理其他消息...
    $messages[] = new Message(...);
}

// 优先使用独立 system 字段（如果存在），否则使用提取的 system 消息
$systemField = $rawRequest['system'] ?? $systemContent;
```

**关键点**：
1. 从 `messages` 数组中提取 `role === 'system'` 的消息
2. 将内容存储到 `system` 字段
3. 不将 system 消息添加到 messages 数组
4. 支持独立的 `system` 字段优先级

### 2. 协议转换流程

```
OpenAI 请求                标准格式                  Anthropic 请求
┌─────────────┐           ┌──────────┐              ┌─────────────┐
│ messages: [ │           │ system:  │              │ system: "..."│
│   system,   │ ──────▶  │ "..."    │  ──────▶    │ messages: [  │
│   user     │           │ messages:│              │   user       │
│ ]          │           │  [user]  │              │ ]            │
└─────────────┘           └──────────┘              └─────────────┘
```

**步骤**：
1. **解析**: `ProtocolConverter::normalizeRequest()` 调用 OpenAI 驱动解析请求
2. **提取**: OpenAI 驱动从 `messages` 中提取 system 消息
3. **标准化**: 创建 `Shared\DTO\Request` 对象，包含提取的 system 字段
4. **转换**: 调用 `toAnthropic()` 方法转换为 Anthropic 格式
5. **构建**: 生成包含独立 `system` 字段的 Anthropic 请求

## 透传协议匹配规则

### Body 透传机制

当渠道配置 `body_passthrough: true` 时，请求体将原样转发，不进行协议转换。

### 匹配规则

为确保透传正确性，实现了**协议匹配过滤**：

```php
protected function applyPassthroughProtocolFilter(
    \Illuminate\Database\Eloquent\Collection $channels,
    string $sourceProtocol
): \Illuminate\Database\Eloquent\Collection {
    return $channels->filter(function ($channel) use ($sourceProtocol) {
        // 未开启透传：允许选择（会进行协议转换）
        if (! $channel->shouldPassthroughBody()) {
            return true;
        }

        // 开启透传：检查协议是否匹配
        $channelProtocol = $this->getChannelProtocol($channel);
        return ($channelProtocol === $sourceProtocol);
    });
}
```

**规则说明**：
1. **未开启透传**: 允许选择任何渠道，系统会进行协议转换
2. **开启透传**: 只选择与源请求协议一致的渠道

**示例**：
- OpenAI 格式请求 + Anthropic 渠道（开启透传）→ **被过滤**（协议不匹配）
- OpenAI 格式请求 + Anthropic 渠道（未开启透传）→ **允许选择**（会转换为 Anthropic 格式）
- Anthropic 格式请求 + Anthropic 渠道（开启透传）→ **允许选择**（协议匹配）

### 协议判断逻辑

```php
protected function getChannelProtocol(Channel $channel): string
{
    $provider = $channel->provider;

    if (in_array($provider, ['anthropic', 'claude'])) {
        return 'anthropic';
    }

    return 'openai';
}
```

## 测试验证

### 测试用例

完整测试位于 `tests/Unit/Services/Protocol/OpenAiToAnthropicConversionTest.php`：

1. ✅ System 消息提取测试
2. ✅ 独立 system 字段优先级测试
3. ✅ Anthropic 格式转换测试
4. ✅ 多模态 system 消息处理测试
5. ✅ 无 system 消息场景测试
6. ✅ 完整协议转换流程测试

### 运行测试

```bash
php artisan test --compact tests/Unit/Services/Protocol/OpenAiToAnthropicConversionTest.php
```

## 历史问题记录

### 问题 #3256

**现象**: OpenAI 格式请求发送到 Anthropic 渠道，但请求未转换

**根因**: OpenAI 协议驱动未提取 `messages` 数组中的 system 消息，导致：
1. System 消息保留在 messages 中
2. 转换为 Anthropic 格式时缺少 `system` 字段
3. Anthropic API 无法正确处理请求

**修复**: 实现了 system 消息自动提取机制

**验证**: 通过审计日志 3256 验证修复效果

```bash
php artisan analyze:request-diff 3256
```

## 最佳实践

### 1. 使用独立 system 字段

推荐在 OpenAI 格式请求中使用独立的 `system` 字段（而不是 messages 中的 system 消息）：

```json
{
  "model": "gpt-4",
  "system": "You are a helpful assistant.",
  "messages": [
    {"role": "user", "content": "Hello!"}
  ]
}
```

### 2. 渠道配置建议

- **Anthropic 渠道**: 配置 `body_passthrough: true` 时，确保客户端使用 Anthropic 格式
- **OpenAI 兼容渠道**: 建议关闭透传，让系统处理协议转换

### 3. 调试技巧

使用 `analyze:request-diff` 命令分析请求转换差异：

```bash
php artisan analyze:request-diff {audit_log_id} --show-diff
```

## 相关文档

- [Provider 与 Protocol 职责分析](./provider-protocol-职责分析.md)
- [渠道路由服务](../services/channel-router.md)
- [透传机制说明](./passthrough-mechanism.md)

## 更新日志

### 2026-03-17
- ✅ 修复 OpenAI 协议驱动不提取 system 消息的 bug
- ✅ 修复 Anthropic 协议驱动 contentBlocks 未转换为对象的 bug
- ✅ 新增完整的协议转换测试套件
- ✅ 更新文档说明透传匹配规则和 system 消息处理机制

### 问题 #3257

**现象**: Anthropic 格式消息转换时报错 "Call to a member function toAnthropic() on array"

**根因**: `AnthropicMessagesDriver::parseRequest()` 在解析多模态消息时，直接将数组赋值给 `contentBlocks` 字段，而没有将数组元素转换为 `ContentBlock` 对象

**影响**:
- Anthropic 格式的多模态消息（包含文本、图片、工具调用等）无法正确处理
- 调用 `Message::toAnthropic()` 时会尝试对数组调用对象方法，导致 Fatal Error

**修复**:
```php
// 修复前
if (is_array($msg['content'])) {
    $contentBlocks = $msg['content'];
}

// 修复后
if (is_array($msg['content'])) {
    $contentBlocks = array_map(
        fn ($block) => \App\Services\Shared\DTO\ContentBlock::fromAnthropic($block),
        $msg['content']
    );
}
```

**验证**: 通过完整的 Anthropic 协议驱动测试套件验证