# Responses API 流式工具调用修复

## 问题描述

使用 OpenAI 官方 codex CLI 客户端测试 Responses API 时，流式工具调用无法正常工作，客户端报错：
- `failed to parse ResponseItem from output_item.added`
- `Unable to read from stream`

## 根因分析

1. **缺少 `response_id` 字段**：`response.output_item.added` 和 `response.output_item.done` 事件中缺少 `response_id` 字段，导致客户端无法解析。

2. **多余的 `object` 字段**：item 对象中包含 `object: 'response.output_item'` 字段，这不是官方 API 格式的一部分。

3. **缺少必需字段**：SDK 在解析 `response.created` 和 `content_part` 事件时，需要特定的字段：
   - `response.created` 事件中的 `response` 对象需要 `output`, `tool_choice`, `tools`, `parallel_tool_calls` 字段
   - `content_part` 事件中的 `part` 对象需要 `annotations` 字段

## 修复内容

### 1. `buildResponseCreatedEvent()` 方法
添加了 SDK 解析所需的必需字段：
```php
'response' => [
    'id' => $responseId,
    'object' => 'response',
    'created_at' => time(),
    'model' => $model,
    'status' => 'in_progress',
    'output' => [],
    'tool_choice' => 'auto',
    'tools' => [],
    'parallel_tool_calls' => false,
],
```

### 2. `buildMessageItemInitEvents()` 方法
- 添加 `response_id` 字段
- 移除 `object` 字段
- 为 `content_part.added` 事件添加 `annotations` 字段

### 3. `buildToolCallEvents()` 方法
- 添加 `response_id` 字段
- 移除 `object` 字段

### 4. `buildCompleteEvents()` 方法
- 为所有 `output_item.done` 事件添加 `response_id` 字段
- 移除所有 `object` 字段
- 为 `content_part.done` 事件添加 `annotations` 字段
- 为 `output` 数组中的所有 `output_text` 对象添加 `annotations` 字段
- 处理空输出情况，确保至少有一个空的 message output_item

## 测试验证

### E2E 测试结果
```
=== 测试 1: 基础非流式请求 ===
✓ 请求成功

=== 测试 2: 状态管理 (previous_response_id) ===
✓ 状态管理正常工作（模型记得之前的对话）

=== 测试 8: 有效 tools + tool_choice ===
✓ 有效 tools + tool_choice 请求成功
```

### Codex 客户端测试
```bash
codex exec --skip-git-repo-check "读取 demo.md 文件内容"
```
模型成功调用 `exec_command` 工具，参数为 `{"cmd": "cat demo.md"}`。

## 文件变更

- `laravel/app/Services/Protocol/Driver/OpenAIResponsesDriver.php`

## 相关文档

- OpenAI Responses API: https://platform.openai.com/docs/api-reference/responses
- OpenAI PHP SDK: https://github.com/openai-php/client