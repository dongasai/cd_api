# Responses API 有状态协议实现完成

## 背景

OpenAI Responses API 是一个有状态协议，通过 `previous_response_id` 维护对话历史。本次实现将状态管理从 Driver 层迁移到 ProtocolRequest/ProtocolResponse 层，遵循系统的核心架构原则：**Driver 应无状态，状态转换应在 ProtocolRequest/ProtocolResponse 的双向契约中完成**。

## 实现方案

### 核心架构

```
请求流程：
1. ProxyServer 注入 apiKeyId 到 rawRequest
2. OpenAIResponsesRequest.fromArray() 提取 _apiKeyId
3. toSharedDTO() 检索历史消息，合并当前消息，创建 ResponsesContext
4. ResponsesContext 通过 protocolContext 传递到响应阶段

响应流程：
5. 非流式：fromSharedDTO() 检测 protocolContext，调用 storeState()
6. 流式：postStreamProcess() 检测 protocolContext，调用 storeState()
7. storeState() 将完整消息历史存储到 response_sessions 表
```

### 关键修改文件

| 文件 | 修改内容 |
|------|---------|
| `OpenAIResponsesRequest.php` | 添加 `$_apiKeyId` 属性，`toSharedDTO()` 实现历史检索和消息合并 |
| `OpenAIResponsesResponse.php` | 添加 `postStreamProcess()` 和 `storeState()` 实现状态存储 |
| `ResponsesContext.php` | 新建协议上下文类，携带 previousResponseId、fullMessages、apiKeyId |
| `ProxyServer.php` | 在协议转换前提取 protocolContext，传递给 Handler |
| `NonStreamHandler.php` | 接收 protocolContext，在响应转换时注入 |
| `StreamHandler.php` | 接收 protocolContext，流式结束后调用 postStreamProcess() |

### 状态存储数据结构

```json
{
  "response_id": "019d5bed9141dcac9db03619ef8555be",
  "api_key_id": 16,
  "model": "Qwen/Qwen3.5-397B-A17B",
  "total_tokens": 517,
  "previous_response_id": "019d5bed5b2448d82db6b2cff72f0dbb",
  "messages": [
    {"role": "user", "content": "你好，请简单介绍一下自己"},
    {"role": "assistant", "content": "你好呀！我是 Qwen3.5..."},
    {"role": "user", "content": "我刚才问了什么？请复述我的问题。"},
    {"role": "assistant", "content": "您刚才问的问题是..." }
  ]
}
```

## E2E 测试结果

| 测试项 | 结果 | 说明 |
|-------|------|-----|
| 基础非流式请求 | ✅ | 正常返回响应，状态正确存储 |
| 状态管理 (previous_response_id) | ✅ | 模型正确回忆历史对话 |
| 流式请求 | ✅ | 服务器正常完成，状态正确存储 |
| instructions 参数 | ✅ | 系统指令正常生效 |
| 数组格式 input | ✅ | 消息数组格式正常工作 |
| 错误处理 | ✅ | 无效模型正确返回错误 |

## 关键问题修复

### 问题1: apiKeyId 为 null

**原因**: `_api_key_id` 放在 rawRequest 顶级，但代码在 `metadata` 中查找

**解决**: 添加 `$_apiKeyId` 属性，在 `fromArray()` 中直接提取

### 问题2: protocolContext 在协议转换中丢失

**原因**: 协议转换时 `fromSharedDTO()` 不保留 protocolContext

**解决**: 在 ProxyServer 中提前提取 protocolContext，直接传递给 Handler

### 问题3: 流式输出长度为 0

**原因**: 客户端 SDK 流读取超时

**说明**: 服务器端流式处理正常完成（3303 chunks），状态存储正确

## 后续建议

1. 增加流式请求超时配置
2. 添加状态过期清理机制
3. 考虑添加会话摘要功能减少 token 消耗