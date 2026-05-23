# DeepSeek reasoning_content 转换修复

**时间**: 2026-05-12 22:50 ~ 23:15

## 问题描述

API 接口报错：
```
Error from provider (DeepSeek): The `reasoning_content` in the thinking mode must be passed back to the API.
```

### 错误场景

- **渠道**: channel_id=6 (opencode-go_deepseek)
- **Provider**: DeepSeek
- **错误次数**: 连续多次 (audit_log_id: 92, 95, 96, 100, 101)

### 根本原因分析

**两层问题叠加**：

1. **Message::fromSharedDTO() 转换丢失 reasoning_content**（根本原因）
   - 文件: [app/Services/Protocol/Driver/OpenAI/Message.php](laravel/app/Services/Protocol/Driver/OpenAI/Message.php)
   - 方法: `fromSharedDTO()` 第 287-289 行
   - 问题: thinking 类型的 contentBlock 被直接 `continue` 跳过，没有转换为 reasoningContent
   - 结果: 构造 Message 时没有传入 reasoningContent 参数

2. **DeepSeek API 特殊要求**
   - DeepSeek 思考模型在工具调用场景下有特殊要求：
     - 有 tool_calls 的 assistant 消息必须保留 reasoning_content
     - 无 tool_calls 的消息必须删除 reasoning_content
   - DeepSeekProvider 的逻辑正确，但上游转换已丢失 reasoning_content

### 请求分析

通过日志分析发现：

```bash
# audit_log_id=101 的请求结构
原始请求: 3 条消息 (system, user, user)
发送给 DeepSeek: 4 条消息 (system, user, assistant with tool_calls, tool)
Message[2]: assistant 消息有 tool_calls 但缺少 reasoning_content ❌
```

**为什么会多出 assistant 消息？**
- 系统在处理多轮对话时，会添加上一轮的 assistant 响应
- 但上一轮 DeepSeek 返回的 thinking 内容（reasoning_content）在转换过程中丢失

## 解决方案

### 修复 Message::fromSharedDTO() 方法

**文件**: [app/Services/Protocol/Driver/OpenAI/Message.php](laravel/app/Services/Protocol/Driver/OpenAI/Message.php:261-335)

**修改内容**：

1. 添加 `$reasoningContent` 变量收集 thinking 内容
2. 将 thinking contentBlock 的 text 转换为 reasoningContent
3. 构造 Message 时传入 reasoningContent 参数

**修复前代码**（第287-289行）：
```php
} elseif ($block->type === 'thinking') {
    // thinking 内容暂时忽略，或可转为 reasoning_content
    // 注意：OpenAI 某些模型支持 reasoning_content 字段
    // 这里暂时跳过，避免污染 content
    continue;
}
```

**修复后代码**：
```php
} elseif ($block->type === 'thinking') {
    // thinking 内容转换为 reasoningContent（DeepSeek 等模型需要）
    if ($block->text !== null) {
        $reasoningContent = $reasoningContent ?? '';
        $reasoningContent .= $block->text;
    }
}
```

**构造 Message 时传入参数**（第325-332行）：
```php
return new self(
    role: $role,
    content: $content,
    toolCalls: $toolCalls,
    toolCallId: $dto->toolCallId,
    name: $dto->name ?? null,
    reasoningContent: $reasoningContent, // 新增参数
);
```

### 关键修复点

**属性名错误修正**：
- 初次修复使用了 `$block->content`（不存在）
- ContentBlock 的正确属性是 `$block->text`
- 已修正为 `$block->text`

## 测试验证

### 测试场景

模拟 assistant 消息包含：
- thinking contentBlock（推理过程）
- text contentBlock（最终答案）
- tool_calls（工具调用）

**测试代码**：
```php
$thinkingBlock = new ContentBlock;
$thinkingBlock->type = 'thinking';
$thinkingBlock->text = 'This is my reasoning process...';

$textBlock = new ContentBlock;
$textBlock->type = 'text';
$textBlock->text = 'Final answer';

$dto->contentBlocks = [$thinkingBlock, $textBlock];
$dto->toolCalls = [['id' => 'call_1', ...]];

$message = Message::fromSharedDTO($dto);
$array = $message->toArray();
```

### 测试结果

```json
{
    "role": "assistant",
    "content": [{"type": "text", "text": "Final answer"}],
    "tool_calls": [{"id": "call_1", ...}],
    "reasoning_content": "This is my reasoning process..."
}
```

**验证通过**：
- ✓ reasoning_content 正确生成
- ✓ tool_calls 正确保留
- ✓ content 正确处理（不含 thinking）

## 技术要点

### DeepSeek API 规则

**参考文档**: https://api-docs.deepseek.com/zh-cn/guides/reasoning_model

关键规则：
1. 思考模型输出 `reasoning_content`（思维链）和 `content`（最终回答）
2. 有 tool_calls 的轮次：必须完整回传 reasoning_content
3. 无 tool_calls 的轮次：删除 reasoning_content

### 字段对比

| 特性 | DeepSeek | OpenAI o1 | Anthropic Claude |
|------|----------|-----------|------------------|
| 推理内容字段 | `reasoning_content`（公开） | 无公开字段 | `thinking` contentBlock |
| 多轮对话处理 | 工具调用轮次必须保留 | 正常拼接 | 自动转换为 reasoning_content |
| 转换时机 | 请求构建时 | 不涉及 | Message::fromSharedDTO() |

### 处理流程

```
Anthropic Response → ContentBlock(thinking) → Message::fromSharedDTO()
                                                     ↓
                                              reasoningContent
                                                     ↓
                                              toArray() → reasoning_content
                                                     ↓
                                          DeepSeekProvider.processReasoningContent()
                                                     ↓
                                              根据tool_calls决定保留/删除
```

## 相关文件

- [Message.php](laravel/app/Services/Protocol/Driver/OpenAI/Message.php) - 修复文件
- [DeepSeekProvider.php](laravel/app/Services/Provider/Driver/DeepSeekProvider.php) - Provider 逻辑（正确）
- [ContentBlock.php](laravel/app/Services/Shared/DTO/ContentBlock.php) - DTO 定义

## 后续建议

1. ✅ Message::fromSharedDTO() 修复完成
2. ✅ 单元测试通过
3. 🔄 建议进行真实 API 集成测试验证
4. 🔄 监控 DeepSeek 渠道的后续请求，确认错误消失

---

**状态**: ✅ 已完成修复并通过测试