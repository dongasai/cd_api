# 修复流式响应中断问题 - ToolCall DTO实例化错误

## 时间
2026-03-29 04:51

## 问题描述

用户发现审计日志 4230 和 4235 出现明显错误，所有统计数据为零：
- `prompt_tokens=0, completion_tokens=0, total_tokens=0`
- `latency_ms=0, first_token_ms=0`
- `status_code=null, finish_reason=null`
- `response_status=null, is_success=0`

用户还反馈："SSE内容不对，明显没结束但是结束了"

## 根本原因

错误日志显示：
```
Unknown named parameter $id
at AnthropicMessagesDriver.php:315
```

问题发生在 `buildToolUseEvent` 方法中，代码尝试使用命名参数实例化 `ToolCall` DTO：

```php
$toolCall = new \App\Services\Shared\DTO\ToolCall(
    id: $tc['id'] ?? '',
    type: ...,
    name: ...,
    arguments: ...,
    index: ...,
);
```

但 `ToolCall` 类是**纯数据容器**，没有构造函数，不能使用命名参数。

## 历史背景

最近的两次DTO重构commit：
- `f02073d` refactor(协议驱动): 重构DTO转换逻辑，统一为纯数据容器模式
- `93ae78c` refactor(dto): 转换DTO从构造函数模式到纯数据容器模式

这些重构将DTO统一为纯数据容器模式，但 AnthropicMessagesDriver.php 的 buildToolUseEvent 方法没有同步更新。

## 修复方案

将命名参数实例化改为直接属性赋值：

```php
// 修复前（错误）
$toolCall = new \App\Services\Shared\DTO\ToolCall(
    id: $tc['id'] ?? '',
    type: \App\Services\Shared\Enums\ToolType::from($tc['type'] ?? 'function'),
    name: $tc['function']['name'] ?? '',
    arguments: $tc['function']['arguments'] ?? '',
    index: $tc['index'] ?? 0,
);

// 修复后（正确）
$toolCall = new \App\Services\Shared\DTO\ToolCall;
$toolCall->id = $tc['id'] ?? '';
$toolCall->type = \App\Services\Shared\Enums\ToolType::from($tc['type'] ?? 'function');
$toolCall->name = $tc['function']['name'] ?? '';
$toolCall->arguments = $tc['function']['arguments'] ?? '';
$toolCall->index = $tc['index'] ?? 0;
```

## 影响范围

**修改文件：**
- [laravel/app/Services/Protocol/Driver/AnthropicMessagesDriver.php](laravel/app/Services/Protocol/Driver/AnthropicMessagesDriver.php:314)

**影响场景：**
- Anthropic协议驱动处理流式响应中的 tool_use 事件
- 导致流式响应在处理工具调用时中断，审计日志数据不完整

## 测试验证

- ✓ dry-run模式测试通过
- ✓ Pint代码格式化通过
- ✓ 代码语法检查正常

## 相关问题

这个问题解释了用户观察到的两个现象：
1. 审计日志数据全为0 - 因为流式响应在处理tool_use事件时崩溃，没有完成数据记录
2. SSE内容中断 - 流式响应在错误发生时突然结束

## 后续建议

1. 检查是否还有其他地方使用了类似的错误实例化方式
2. 考虑添加单元测试覆盖流式响应中的tool_use事件处理
3. 在DTO重构时需要全量检查所有使用点