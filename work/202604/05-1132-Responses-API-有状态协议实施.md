# Responses API 有状态协议实施

## 时间
2026-04-05 11:32 - 12:15

## 背景
OpenAI Responses API 是一个有状态协议，通过 `previous_response_id` 维护对话历史。原实现将状态管理逻辑放在 Driver 层，违反了系统的核心架构原则：**Driver 应无状态，状态转换应在 ProtocolRequest/ProtocolResponse 的双向契约中完成**。

## 实施内容

### 新建文件
1. `laravel/app/Services/Protocol/Driver/OpenAIResponses/ResponsesContext.php` - 协议上下文类
2. `laravel/app/Services/Protocol/Driver/Concerns/ProtocolResponseTrait.php` - 默认空实现 Trait

### 修改文件

#### SharedDTO 扩展
- `laravel/app/Services/Shared/DTO/Request.php` - 添加 `protocolContext` 属性
- `laravel/app/Services/Shared/DTO/Response.php` - 添加 `protocolContext` 属性

#### 协议接口扩展
- `laravel/app/Services/Protocol/Contracts/ProtocolResponse.php` - 添加 `postStreamProcess()` 方法

#### 现有 Response 添加 Trait
- `laravel/app/Services/Protocol/Driver/OpenAI/ChatCompletionResponse.php`
- `laravel/app/Services/Protocol/Driver/Anthropic/MessagesResponse.php`

#### 核心重构
- `laravel/app/Services/Protocol/Driver/OpenAIResponses/OpenAIResponsesRequest.php`
  - 重构 `toSharedDTO()` 添加历史检索逻辑
  - 创建 `ResponsesContext` 传递状态信息

- `laravel/app/Services/Protocol/Driver/OpenAIResponses/OpenAIResponsesResponse.php`
  - 重构 `fromSharedDTO()` 添加状态存储
  - 实现 `postStreamProcess()` 处理流式后存储
  - 添加私有方法 `storeState()` 共用存储逻辑

- `laravel/app/Services/Protocol/Driver/OpenAIResponsesDriver.php`
  - 删除状态属性：`$currentRequest`, `$fullMessages`, `$accumulatedOutput`, `$previousResponseId`
  - 删除方法：`setRequest()`, `storeStreamSession()`, `storeResponseSession()`, `retrieveHistory()`, `getCurrentApiKeyId()`
  - 简化为纯格式转换驱动

- `laravel/app/Services/Protocol/ProtocolConverter.php`
  - `getResponseClass()` 改为 public 方法

#### Handler 层调整
- `laravel/app/Services/Router/Handler/StreamHandler.php`
  - 添加 `protocolContext` 变量接收请求上下文
  - 流式结束后调用 `postStreamProcess()`
  - 添加 `buildResponseFromChunks()` 方法

- `laravel/app/Services/Router/ProxyServer.php`
  - 注入 `_api_key_id` 到 rawRequest（Responses 协议）
  - 删除 `setRequest()` 调用

## 架构设计

```
请求阶段:
OpenAIResponsesRequest.toSharedDTO()
  → 检索历史 (StateManager.retrieve)
  → 合并消息
  → 返回 SharedRequest + ResponsesContext

响应阶段:
非流式: OpenAIResponsesResponse.fromSharedDTO() → 存储状态
流式: StreamHandler 流结束后 → response.postStreamProcess(chunks, context)
```

## 验证
- 代码格式化：`vendor/bin/pint --dirty --format agent` ✓
- E2E 测试脚本：`tests/E2E/ResponsesApiTest.php`

## 文档
- 设计文档：`docs/Responses-API-有状态协议设计.md`

## 后续
- 可编写单元测试验证历史检索和状态存储逻辑
- 可通过 E2E 测试脚本验证完整对话链