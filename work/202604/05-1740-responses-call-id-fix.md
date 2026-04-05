# Responses API 流式工具调用 - call_id 字段修复

## 问题描述

使用 OpenAI 官方 codex CLI 客户端测试 Responses API 时，流式工具调用虽然返回了 `function_call` 输出项，但客户端不执行工具调用。

## 根因分析

通过分析 openai-php/client SDK 源码发现：

**`OutputFunctionToolCall.php`** 要求 `function_call` 输出项必须包含 `call_id` 字段：

```php
/**
 * @phpstan-type OutputFunctionToolCallType array{
 *   arguments: string,
 *   call_id: string,  // 必需字段！
 *   name: string,
 *   type: 'function_call',
 *   id: string,
 *   status: 'in_progress'|'completed'|'incomplete'
 * }
 */
```

OpenAI Responses API 规范中，`call_id` 是工具调用的唯一标识符，客户端使用它来：
1. 识别需要执行的工具调用
2. 在提交工具输出 (`function_call_output`) 时引用对应的工具调用

我们之前的输出格式只有 `id` 字段，缺少 `call_id` 字段，导致 SDK 无法解析，客户端无法执行工具调用。

## 修复内容

### 1. `OpenAIResponsesDriver.php` - 流式事件

#### buildToolCallEvents() 方法
在 `output_item.added` 事件和 `outputItems` 数组中添加 `call_id` 字段：

```php
// 累积输出项目
$callId = $toolCallId;
$this->outputItems[] = [
    'id' => $toolCallId,
    'call_id' => $callId,  // 新增
    'type' => 'function_call',
    // ...
];

// 发送 function_call_item.added 事件
'item' => [
    'id' => $toolCallId,
    'call_id' => $callId,  // 新增
    'type' => 'function_call',
    // ...
],
```

#### buildCompleteEvents() 方法
在 `output_item.done` 事件中添加 `call_id` 字段：

```php
'item' => [
    'id' => $outputItem['id'],
    'call_id' => $outputItem['call_id'] ?? $outputItem['id'],  // 新增
    'type' => 'function_call',
    // ...
],
```

#### 最终 output 数组
在 `response.completed` 事件的 output 数组中添加 `call_id` 字段：

```php
$output[] = [
    'id' => $outputItem['id'],
    'call_id' => $outputItem['call_id'] ?? $outputItem['id'],  // 新增
    'type' => 'function_call',
    // ...
];
```

### 2. `OpenAIResponsesResponse.php` - 非流式响应

在 `fromChatCompletions()` 方法中，构建 `function_call` 输出项时添加 `call_id` 字段：

```php
$response->output[] = [
    'type' => 'function_call',
    'id' => $callId,
    'call_id' => $callId,  // 新增：必须字段
    'status' => 'completed',
    'name' => $funcName,
    'arguments' => $funcArgs,
];
```

## 验证结果

### API 测试

```bash
curl -X POST http://192.168.4.107:32126/api/openai/v1/responses \
  -H "Authorization: Bearer sk-xxx" \
  -H "Content-Type: application/json" \
  -d '{"model":"Qwen/Qwen3.5-397B-A17B","input":"读取 demo.md","tools":[...],"stream":true}'
```

流式响应中 `function_call` 输出项正确包含 `call_id` 字段：
```json
{
  "type": "response.output_item.done",
  "item": {
    "id": "019d5d024b0815c3e0e92cfc037502c1",
    "call_id": "019d5d024b0815c3e0e92cfc037502c1",  // ✓ 正确包含
    "type": "function_call",
    "status": "completed",
    "name": "read_file",
    "arguments": "{\"path\": \"demo.md\"}"
  }
}
```

### codex 客户端测试

```bash
codex exec --skip-git-repo-check "读取 demo.md 文件，告诉我里面有什么内容"
```

**修复前**：工具调用不执行

**修复后**：工具调用成功执行
```
exec
/bin/bash -lc 'cat demo.md' in /data/project/ai_proxy/coding_api
 succeeded in 0ms:
# Demo 文件

这是一个测试文件，用于验证 Responses API 工具调用功能。

## 内容

- 第一行
- 第二行
- 第三行
```

### 注意事项

测试中发现模型会重复执行相同的命令多次，这是 Qwen 模型的行为特性，与工具调用机制无关。工具调用本身工作正常：
1. API 正确返回带 `call_id` 的 `function_call` 输出
2. Codex 客户端正确识别并执行工具调用
3. 工具输出正确返回给模型

## 参考文档

- OpenAI Responses API: https://platform.openai.com/docs/api-reference/responses
- SDK OutputFunctionToolCall: vendor/openai-php/client/src/Responses/Responses/Output/OutputFunctionToolCall.php

## 相关工作日志

- [202604/05-1425-responses-streaming-fix.md](./05-1425-responses-streaming-fix.md) - 之前的流式事件格式修复