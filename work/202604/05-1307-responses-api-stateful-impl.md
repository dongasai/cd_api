# Responses API 有状态协议实施完成

## 时间
2026-04-05 13:07

## 背景
OpenAI Responses API 是有状态协议，通过 `previous_response_id` 维护对话历史。原实现将状态管理放在 Driver 层，违反架构原则：Driver 应无状态，状态转换应在 ProtocolRequest/ProtocolResponse 双向契约中完成。

## 解决方案
将状态管理从 Driver 层迁移到 ProtocolRequest/ProtocolResponse 层，通过 `protocolContext` 在 SharedDTO 中传递状态信息。

## 关键修改

### 1. 新建 ResponsesContext 类
文件: `app/Services/Protocol/Driver/OpenAIResponses/ResponsesContext.php`
```php
class ResponsesContext
{
    public function __construct(
        public ?string $previousResponseId = null,
        public array $fullMessages = [],
        public ?int $apiKeyId = null,
    ) {}
}
```

### 2. 扩展 SharedDTO
- `Request.php`: 添加 `public ?object $protocolContext = null;`
- `Response.php`: 添加 `public ?object $protocolContext = null;`

### 3. 创建 ProtocolResponseTrait
文件: `app/Services/Protocol/Driver/Concerns/ProtocolResponseTrait.php`
默认空实现，供无状态协议使用。

### 4. 扩展 ProtocolResponse 接口
添加 `postStreamProcess(array $chunks, ?object $context = null): void;`

### 5. OpenAIResponsesRequest 重构
- 添加 `$_apiKeyId` 属性直接捕获 API Key ID
- `toSharedDTO()` 检索历史消息并合并
- 创建 `ResponsesContext` 存入 `protocolContext`
- `inputToMessages()` 转换 `developer` → `user`

### 6. OpenAIResponsesResponse 重构
- 实现 `postStreamProcess()` 存储状态
- `fromSharedDTO()` 处理状态存储

### 7. ContentPart 内容类型转换
- `input_text` → `text`
- `input_image` → `image_url`

### 8. ChatCompletionRequest tools 过滤
跳过没有有效 function 定义的空工具。

### 9. ProxyServer 调整
提前提取 `protocolContext` 并传递给 handlers。

## 修复的问题

| 问题 | 原因 | 解决 |
|-----|------|------|
| apiKeyId 为 null | `_api_key_id` 放在 rawRequest 但代码查 metadata | 添加 `$_apiKeyId` 属性直接提取 |
| protocolContext 丢失 | `fromSharedDTO()` 不保留 protocolContext | ProxyServer 提前提取传递 |
| developer role 错误 | Responses API 支持 developer 但枚举无此值 | 添加 Developer case + 转换为 user |
| input_text 不支持 | 硅基流动拒绝 input_text 类型 | ContentPart 转换为 text |
| tools 参数错误 | 简化 tools 无 function 定义被上游拒绝 | 过滤无效 tools |

## E2E 测试结果
```
=== 测试 1: 基础非流式请求 ===
✓ 请求成功

=== 测试 2: 状态管理 (previous_response_id) ===
✓ 状态管理请求成功
✓ 状态管理正常工作（模型记得之前的对话）

=== 测试 3: 流式请求 ===
✓ 流式请求完成

=== 测试 4: 带 instructions 的请求 ===
✓ Instructions 生效

=== 测试 5: 数组格式 input ===
✓ 请求成功

=== 测试 6: 错误处理 ===
✓ 正确处理错误
```

## 状态流转
```
Request (带 previous_response_id)
    ↓
OpenAIResponsesRequest.toSharedDTO()
    ↓ 检索历史
SharedDTO + protocolContext
    ↓ 转换为 Chat Completions
Provider 发送请求
    ↓ 接收响应
OpenAIResponsesResponse
    ↓ 存储 state
Response (带新 response.id)
```

## 相关文件
- `docs/Responses-API-有状态协议设计.md`
- `docs/协议转换核心架构.md`
- `tests/E2E/ResponsesApiTest.php`