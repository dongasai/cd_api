# Responses API 有状态协议设计

## 问题背景

OpenAI Responses API 是一个**有状态协议**，通过 `previous_response_id` 维护对话历史。当前实现将状态管理逻辑放在 Driver 层，违反了系统的核心架构原则。

## 系统核心架构

```
客户端协议 ──→ ProtocolRequest ──→ Shared\DTO（无状态） ──→ 目标协议 ProtocolRequest ──→ 上游API
上游响应    ──→ ProtocolResponse ──→ Shared\DTO（无状态） ──→ 客户端协议 ProtocolResponse
```

**核心原则**：
- Driver 无状态，纯协议转换
- SharedDTO 作为协议无关的中间层
- 状态转换发生在 `toSharedDTO()` / `fromSharedDTO()` 双向契约中

## 设计方案

### 整体架构

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           请求流程                                           │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  客户端: { input: "你好", previous_response_id: "resp_123", model: "gpt-4" } │
│                              ↓                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │ OpenAIResponsesRequest.toSharedDTO()                                 │  │
│  │                                                                       │  │
│  │   ① 检索: StateManager.retrieve("resp_123")                          │  │
│  │      → [历史消息...]                                                  │  │
│  │                                                                       │  │
│  │   ② 合并: [历史消息...] + [{role:"user", content:"你好"}]             │  │
│  │      → [完整消息历史]                                                 │  │
│  │                                                                       │  │
│  │   ③ 返回: SharedRequest {                                            │  │
│  │        messages: [完整历史],                                          │  │
│  │        model: "gpt-4",                                                │  │
│  │        protocolContext: ResponsesContext {                            │  │
│  │          previousResponseId: "resp_123",                             │  │
│  │          fullMessages: [完整历史]                                     │  │
│  │        }                                                              │  │
│  │      }                                                                │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                              ↓                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │ SharedDTO (无状态)                                                    │  │
│  │   SharedRequest {                                                     │  │
│  │     messages: [{role:"user",content:"历史1"}, ...],                  │  │
│  │     model: "gpt-4",                                                   │  │
│  │     protocolContext: ResponsesContext { ... }  // 协议特有上下文       │  │
│  │   }                                                                   │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                              ↓                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │ ChatCompletionRequest.fromSharedDTO()                                │  │
│  │   直接使用 SharedRequest 数据创建（忽略 protocolContext）             │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                              ↓                                              │
│  发送到上游 Chat Completions API                                            │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         非流式响应流程                                       │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  上游返回: ChatCompletionResponse                                           │
│                              ↓                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │ ChatCompletionResponse.toSharedDTO()                                 │  │
│  │   转换为 SharedResponse                                               │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                              ↓                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │ SharedDTO (无状态)                                                    │  │
│  │   SharedResponse {                                                    │  │
│  │     id: "chatcmpl_xxx",                                               │  │
│  │     model: "gpt-4",                                                   │  │
│  │     content: "完整响应内容",                                          │  │
│  │     usage: {...},                                                     │  │
│  │     protocolContext: ResponsesContext { ... }  // 从请求传递          │  │
│  │   }                                                                   │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                              ↓                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │ OpenAIResponsesResponse.fromSharedDTO()                              │  │
│  │                                                                       │  │
│  │   ① 构建 Responses 格式响应                                           │  │
│  │                                                                       │  │
│  │   ② 存储状态:                                                         │  │
│  │      StateManager::store(                                             │  │
│  │          responseId: "resp_456",                                      │  │
│  │          messages: [...context.fullMessages, 助手回复],               │  │
│  │          previousResponseId: context.previousResponseId               │  │
│  │      )                                                                │  │
│  │                                                                       │  │
│  │   ③ 返回 ResponsesResponse                                           │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                              ↓                                              │
│  返回客户端: Responses 格式响应                                              │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         流式响应流程                                         │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  流式块 StreamChunk (无状态)                                                │
│       ↓                                                                     │
│  Driver.buildStreamChunk() → 转换格式，立即输出                              │
│       ↓                                                                     │
│  Handler 累积 chunks → 用于日志（协议无关）                                  │
│       ↓                                                                     │
│  流式结束                                                                    │
│       ↓                                                                     │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │ Handler 统一调用后处理（协议无关）                                      │ │
│  │                                                                        │ │
│  │   $response->postStreamProcess($chunks, $context)                     │ │
│  │                                                                        │ │
│  │   其他协议: 空实现，不做任何处理                                        │ │
│  │   Responses: 提取内容 + 存储状态                                       │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
│       ↓                                                                     │
│  Driver.buildStreamDone() → 发送 [DONE]                                     │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

## 组件设计

### 1. ProtocolResponse 接口扩展

```php
// ProtocolResponse.php
interface ProtocolResponse
{
    /**
     * 转换为 Shared\DTO
     */
    public function toSharedDTO(): SharedResponse;

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(object $dto): static;

    /**
     * 转换为数组
     */
    public function toArray(): array;

    /**
     * ⭐ 流式后处理
     *
     * 流式响应结束后，协议特定的处理逻辑
     * - 默认实现：无操作
     * - Responses API：提取完整内容，存储状态
     *
     * @param  array  $chunks  累积的流式块
     * @param  object|null  $context  协议上下文（从请求传递）
     */
    public function postStreamProcess(array $chunks, ?object $context = null): void;
}
```

### 2. 默认实现（Trait）

```php
// ProtocolResponseTrait.php
trait ProtocolResponseTrait
{
    /**
     * 默认：无操作
     *
     * 大多数协议不需要流式后处理，空实现
     */
    public function postStreamProcess(array $chunks, ?object $context = null): void
    {
        // 默认无操作
    }
}
```

### 3. SharedDTO 扩展

```php
// SharedRequest.php
class Request
{
    // 标准字段
    public string $model = '';
    public array $messages = [];
    public ?int $maxTokens = null;
    public ?float $temperature = null;
    // ...

    /**
     * 协议特有上下文（用于传递状态信息）
     * 不影响标准转换流程
     */
    public ?object $protocolContext = null;
}
```

```php
// SharedResponse.php
class Response
{
    // 标准字段
    public string $id = '';
    public string $model = '';
    public string $content = '';
    public ?Usage $usage = null;
    public ?FinishReason $finishReason = null;
    // ...

    /**
     * 协议特有上下文（从请求传递到响应）
     */
    public ?object $protocolContext = null;
}
```

### 4. ResponsesContext 定义

```php
// OpenAIResponses/ResponsesContext.php
namespace App\Services\Protocol\Driver\OpenAIResponses;

/**
 * Responses API 协议上下文
 *
 * 携带请求阶段的状态信息，传递到响应阶段用于存储
 */
class ResponsesContext
{
    public function __construct(
        public ?string $previousResponseId = null,
        public array $fullMessages = [],
        public ?int $apiKeyId = null,
    ) {}
}
```

### 5. OpenAIResponsesRequest 重构

```php
class OpenAIResponsesRequest implements ProtocolRequest
{
    // 保持纯数据容器，无类属性状态

    public function toSharedDTO(): SharedRequest
    {
        $dto = new SharedRequest;
        $dto->model = $this->model;

        // 构建消息
        $messages = $this->inputToMessages();

        // 状态转换：有状态 → 无状态
        $fullMessages = $messages;
        $previousResponseId = $this->previousResponseId;

        if ($previousResponseId) {
            $history = ResponseStateManager::retrieve(
                $previousResponseId,
                $this->getApiKeyId()
            );

            if ($history !== null) {
                $fullMessages = array_merge($history, $messages);
            } else {
                // 历史不存在，开始新对话
                $previousResponseId = null;
            }
        }

        $dto->messages = $fullMessages;
        $dto->maxTokens = $this->maxTokens;
        $dto->temperature = $this->temperature;
        // ...

        // ⭐ 携带协议上下文
        $dto->protocolContext = new ResponsesContext(
            previousResponseId: $previousResponseId,
            fullMessages: $fullMessages,
            apiKeyId: $this->getApiKeyId(),
        );

        return $dto;
    }
}
```

### 6. OpenAIResponsesResponse 重构

```php
class OpenAIResponsesResponse implements ProtocolResponse
{
    use ProtocolResponseTrait;

    // 非流式响应：状态存储在 fromSharedDTO 中完成
    public static function fromSharedDTO(object $dto): static
    {
        $response = new self;
        $response->id = $dto->id;
        $response->model = $dto->model;

        // 构建 output 数组格式
        $response->output = [
            [
                'type' => 'message',
                'id' => $dto->id.'_msg',
                'role' => 'assistant',
                'content' => [
                    ['type' => 'output_text', 'text' => $dto->content],
                ],
            ],
        ];

        // 转换 usage
        if ($dto->usage !== null) {
            $response->usage = [
                'input_tokens' => $dto->usage->inputTokens,
                'output_tokens' => $dto->usage->outputTokens,
            ];
        }

        // 转换 finishReason
        if ($dto->finishReason !== null) {
            $response->stopReason = self::mapFinishReason($dto->finishReason->value);
        }

        // ⭐ 非流式：状态存储
        if ($dto->protocolContext instanceof ResponsesContext) {
            $response->storeState($dto->protocolContext, $dto->content, $dto->usage);
        }

        return $response;
    }

    /**
     * ⭐ 流式后处理：存储状态
     */
    public function postStreamProcess(array $chunks, ?object $context = null): void
    {
        if (!$context instanceof ResponsesContext) {
            return;
        }

        // 从 chunks 提取完整内容
        $content = '';
        $usage = null;
        foreach ($chunks as $chunk) {
            $content .= $chunk->contentDelta ?? $chunk->delta ?? '';
            if ($chunk->usage !== null) {
                $usage = $chunk->usage;
            }
        }

        // 存储状态
        $this->storeState($context, $content, $usage);
    }

    /**
     * 存储状态（共用逻辑）
     */
    private function storeState(ResponsesContext $context, string $content, ?Usage $usage): void
    {
        // 构建完整消息历史（请求历史 + 助手回复）
        $completeMessages = $context->fullMessages;
        $completeMessages[] = [
            'role' => 'assistant',
            'content' => $content,
        ];

        // 存储状态
        ResponseStateManager::store(
            responseId: $this->id,
            messages: $completeMessages,
            apiKeyId: $context->apiKeyId,
            model: $this->model,
            totalTokens: $usage?->getTotalTokens() ?? 0,
            previousResponseId: $context->previousResponseId,
        );
    }
}
```

### 7. OpenAIResponsesDriver 重构

```php
class OpenAIResponsesDriver extends AbstractDriver
{
    // ⭐ 移除所有类属性状态

    public function parseRequest(array $rawRequest): ProtocolRequest
    {
        return OpenAIResponsesRequest::fromArrayValidated($rawRequest);
    }

    public function buildResponse(ProtocolResponse $response): array
    {
        if ($response instanceof ChatCompletionResponse) {
            $sharedDTO = $response->toSharedDTO();
            return OpenAIResponsesResponse::fromSharedDTO($sharedDTO)->toArray();
        }

        if ($response instanceof OpenAIResponsesResponse) {
            return $response->toArray();
        }

        // 其他类型通过 SharedDTO 转换
        $sharedDTO = $response->toSharedDTO();
        return OpenAIResponsesResponse::fromSharedDTO($sharedDTO)->toArray();
    }

    public function buildStreamChunk(StreamChunk $chunk): string
    {
        // 纯格式转换，无状态
        // ... 现有逻辑 ...
    }

    public function buildStreamDone(): string
    {
        return "data: [DONE]\n\n";
    }
}
```

### 8. Handler 层调整

```php
// StreamHandler.php
class StreamHandler
{
    public function handle(...): Generator
    {
        // 请求阶段：获取 SharedDTO（包含 protocolContext）
        $sharedRequest = $protocolRequest->toSharedDTO();
        $context = $sharedRequest->protocolContext;

        // 流式处理
        $streamChunks = [];
        foreach ($stream as $chunk) {
            $streamChunks[] = $chunk;
            yield $this->protocolConverter->convertStreamChunk($chunk, $sourceProtocol);
        }

        // ⭐ 流式结束后：统一调用后处理（协议无关）
        // 构建临时响应对象用于调用 postStreamProcess
        $response = $this->buildResponseFromChunks($streamChunks, $sourceProtocol);
        $response->postStreamProcess($streamChunks, $context);

        // 记录日志等其他处理...
    }

    /**
     * 从流式块构建响应对象
     */
    private function buildResponseFromChunks(array $chunks, string $protocol): ProtocolResponse
    {
        $responseClass = $this->protocolConverter->getResponseClass($protocol);
        $response = new $responseClass;

        // 设置基本属性
        foreach ($chunks as $chunk) {
            if (!empty($chunk->id)) $response->id = $chunk->id;
            if (!empty($chunk->model)) $response->model = $chunk->model;
        }

        return $response;
    }
}
```

## 状态管理职责划分

| 层级 | 职责 | 状态相关 |
|-----|------|---------|
| **Controller** | 接收请求，返回响应 | 无 |
| **ProtocolRequest** | `toSharedDTO()` 时检索历史、合并消息 | ✅ 状态展开 |
| **SharedDTO** | 无状态数据容器，携带 context | 仅传递，不处理 |
| **ProtocolResponse** | `fromSharedDTO()` / `postStreamProcess()` 存储状态 | ✅ 状态存储 |
| **Driver** | 纯格式转换 | 无 |
| **Handler** | 协调流程，传递 context，调用后处理 | 无 |
| **StateManager** | 状态持久化 | ✅ CRUD 操作 |

## 非流式 vs 流式对比

| 场景 | 状态存储时机 | 存储位置 |
|-----|-------------|---------|
| **非流式** | `fromSharedDTO()` | 响应转换时自动触发 |
| **流式** | `postStreamProcess()` | Handler 流式结束后调用 |

## 优势

1. **符合系统架构**：Driver 无状态，状态转换在 DTO 双向契约中完成
2. **职责清晰**：状态管理封装在 ProtocolResponse 的方法中
3. **协议无关**：Handler 统一调用 `postStreamProcess()`，无需判断协议类型
4. **可扩展**：其他有状态协议可实现自己的 `postStreamProcess()`
5. **无并发问题**：每次请求都是独立的实例，不存在共享状态污染

## 迁移步骤

1. 扩展 `ProtocolResponse` 接口，添加 `postStreamProcess()` 方法
2. 创建 `ProtocolResponseTrait` 提供默认空实现
3. 创建 `ResponsesContext` 类
4. 扩展 `SharedRequest` 和 `SharedResponse`，添加 `protocolContext` 属性
5. 重构 `OpenAIResponsesRequest.toSharedDTO()`
6. 重构 `OpenAIResponsesResponse`，实现 `postStreamProcess()`
7. 清理 `OpenAIResponsesDriver` 的类属性状态
8. 调整 `StreamHandler`，流式结束后调用 `postStreamProcess()`
9. 编写测试验证

## 注意事项

- `protocolContext` 是可选的，不影响标准协议转换流程
- 其他 Driver 应忽略不认识的 `protocolContext`
- 其他协议的 `postStreamProcess()` 使用默认空实现
- 状态存储失败不应影响响应返回（catch 异常，记录日志）