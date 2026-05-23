# 修复空 tools 数组导致的请求错误

## 问题描述

执行请求重放命令时报错：
```bash
php artisan cdapi:request:replay --latest
```

错误信息：
```
状态码：500
请求失败
详情：{
    "message": "[] is too short - 'tools'",
    "exception": "App\\Services\\Provider\\Exceptions\\ProviderException"
}
```

## 问题分析

### 根本原因

在协议请求DTO的 `toArray()` 方法中，使用 `$this->tools !== null` 判断是否添加 `tools` 字段到请求体。

**问题**：空数组 `[]` 在PHP中不等于 `null`，因此即使没有工具，也会添加 `"tools": []` 到请求体中。

某些上游API（如MiniMax）对 `tools` 字段有最小长度验证，不允许空数组，导致400错误。

### 涉及文件

1. [laravel/app/Services/Protocol/Driver/OpenAI/ChatCompletionRequest.php:213](../laravel/app/Services/Protocol/Driver/OpenAI/ChatCompletionRequest.php#L213)
2. [laravel/app/Services/Protocol/Driver/Anthropic/MessagesRequest.php:179](../laravel/app/Services/Protocol/Driver/Anthropic/MessagesRequest.php#L179)

## 修复方案

### 修改内容

将判断条件从 `$this->tools !== null` 改为 `! empty($this->tools)`，确保只有非空数组才会被添加到请求体中。

**修改前**：
```php
if ($this->tools !== null) {
    $result['tools'] = array_map(fn (Tool $tool) => $tool->toArray(), $this->tools);
}
```

**修改后**：
```php
if (! empty($this->tools)) {
    $result['tools'] = array_map(fn (Tool $tool) => $tool->toArray(), $this->tools);
}
```

### 影响范围

- OpenAI协议：`ChatCompletionRequest`
- Anthropic协议：`MessagesRequest`

这两个协议的请求DTO都会受到影响。

## 验证结果

修复后重新执行请求重放命令：
```bash
php artisan cdapi:request:replay --latest
```

**结果**：
- 状态码：200 ✅
- 流式响应正常 ✅
- 无错误信息 ✅

请求成功完成，耗时2725.32ms。

## 经验总结

### PHP空值判断注意事项

在PHP中，不同类型的"空值"有不同的判断方式：

| 值 | `$var !== null` | `! empty($var)` |
|---|---|---|
| `null` | ❌ false | ✅ true |
| `[]` (空数组) | ✅ true | ❌ false |
| `0` (数字) | ✅ true | ❌ false |
| `""` (空字符串) | ✅ true | ❌ false |

**建议**：
- 对于数组字段，使用 `! empty()` 或 `count($var) > 0` 来判断是否应该添加到请求体
- 对于可选字段，明确区分"未设置"(null)和"设置为空"(空数组/空字符串)的语义差异

### API兼容性问题

不同API厂商对可选字段的验证规则可能不同：
- OpenAI官方API通常比较宽松，允许空数组
- 某些兼容API（如MiniMax）可能更严格，要求字段要么不存在，要么符合最小约束

**最佳实践**：对于有最小长度约束的数组字段，要么不添加，要么确保符合约束。

## 相关链接

- OpenAI Chat Completions API: https://platform.openai.com/docs/api-reference/chat/create
- Anthropic Messages API: https://docs.anthropic.com/en/api/messages