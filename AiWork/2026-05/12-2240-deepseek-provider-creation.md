# DeepSeek 独立驱动创建 - reasoning_content 处理修复

**时间**: 2026-05-12 22:40

## 问题背景

API 接口报错：
```
Error from provider (DeepSeek): The `reasoning_content` in the thinking mode must be passed back to the API.
```

### 根本原因

DeepSeek 的思考模型（deepseek-reasoner）在多轮对话中对 `reasoning_content` 字段有特殊要求：

- **有工具调用的轮次**：必须保留 reasoning_content，完整传递给 API（让模型继续思考）
- **无工具调用的轮次**：必须删除 reasoning_content，否则 API 返回 400 错误

原有代码使用 `OpenAICompatibleProvider`，没有处理这个特殊逻辑，导致错误。

## 解决方案

### 创建 DeepSeekProvider 独立驱动

**文件**: [laravel/app/Services/Provider/Driver/DeepSeekProvider.php](laravel/app/Services/Provider/Driver/DeepSeekProvider.php)

**核心逻辑**：
```php
protected function processReasoningContent(array $messages): array
{
    return array_map(function ($message) {
        if (is_array($message)) {
            // 检查是否有 tool_calls
            $hasToolCalls = isset($message['tool_calls']) && !empty($message['tool_calls']);

            // 如果没有工具调用，删除 reasoning_content
            if (!$hasToolCalls && isset($message['reasoning_content'])) {
                unset($message['reasoning_content']);
            }
        }

        return $message;
    }, $messages);
}
```

### 更新 ProviderManager

**文件**: [laravel/app/Services/Provider/ProviderManager.php](laravel/app/Services/Provider/ProviderManager.php)

添加 DeepSeek 类型映射：
```php
return match ($providerName) {
    'openai' => new OpenAIProvider($config),
    'anthropic' => new AnthropicProvider($config),
    'deepseek' => new DeepSeekProvider($config),  // 新增
    default => new OpenAICompatibleProvider($config),
};
```

## 测试验证

### 测试场景 1：有工具调用（保留 reasoning_content）

```php
$messages = [
    ['role' => 'assistant', 'content' => 'Hi', 'reasoning_content' => 'Thinking...', 'tool_calls' => [...]]
];
// 结果：reasoning_content 被保留 ✓
```

### 测试场景 2：无工具调用（删除 reasoning_content）

```php
$messages = [
    ['role' => 'assistant', 'content' => 'Hi', 'reasoning_content' => 'Thinking process...']
];
// 结果：reasoning_content 被删除 ✓
```

## 支持的模型列表

DeepSeekProvider 支持以下模型：

- `deepseek-v4-pro` (新版本)
- `deepseek-v4-flash` (新版本)
- `deepseek-chat` (将于 2026-07-24 弃用)
- `deepseek-coder` (将于 2026-07-24 弃用)
- `deepseek-reasoner` (将于 2026-07-24 弃用)

## 技术要点

### DeepSeek API 文档说明

**参考文档**: https://api-docs.deepseek.com/zh-cn/guides/reasoning_model

关键规则：
1. 思考模式下，模型会输出 `reasoning_content`（思维链）和 `content`（最终回答）
2. 进行工具调用后，必须在后续所有请求中完整回传 `reasoning_content`
3. 未进行工具调用的轮次，删除 `reasoning_content` 后再发起请求

### 字段对比

| 特性 | DeepSeek Reasoner | OpenAI o1 |
|------|-------------------|-----------|
| 推理内容字段 | `reasoning_content` (公开) | 无公开字段 |
| 多轮对话处理 | 工具调用轮次必须保留，否则删除 | 正常拼接 |
| 推理控制 | 无特殊参数 | `reasoning_effort` |

## 后续工作

1. ✅ DeepSeekProvider 创建完成
2. ✅ ProviderManager 更新完成
3. ✅ 单元测试通过
4. 🔄 建议进行集成测试验证真实 API 调用

## 相关文件

- [DeepSeekProvider.php](laravel/app/Services/Provider/Driver/DeepSeekProvider.php) - 新创建的驱动
- [ProviderManager.php](laravel/app/Services/Provider/ProviderManager.php) - 更新了匹配逻辑
- [AbstractProvider.php](laravel/app/Services/Provider/Driver/AbstractProvider.php) - 父类，流式处理已支持 reasoning_content

---

**状态**: ✅ 已完成并通过测试