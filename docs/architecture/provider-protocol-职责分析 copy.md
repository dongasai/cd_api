# Provider 与 Protocol 架构实现文档

> 更新日期: 2026-03-22

## 架构概览

CdApi 采用三层架构处理 AI 请求：

```
┌─────────────────────────────────────────────────────────┐
│              客户端请求/响应（JSON 格式）                 │
│          OpenAI Chat Completions / Anthropic Messages    │
└─────────────────────────────────────────────────────────┘
                          │
                          │ Protocol 层: parseRequest() / buildResponse()
                          ▼
┌─────────────────────────────────────────────────────────┐
│              Shared\DTO 实体（协议无关）                  │
│                   统一中间格式                            │
└─────────────────────────────────────────────────────────┘
                          │
                          │ Provider 层: buildRequestBody() / parseResponse()
                          ▼
┌─────────────────────────────────────────────────────────┐
│              上游请求/响应（JSON 格式）                   │
│          OpenAI API / Anthropic API 原始格式             │
└─────────────────────────────────────────────────────────┘
```

---

## 当前文件结构

```
app/Services/
├── Shared/                          # 共享层
│   ├── DTO/                         # 数据传输对象 ✅ 已实现
│   │   ├── Request.php              # 统一请求 DTO
│   │   ├── Response.php             # 统一响应 DTO
│   │   ├── StreamChunk.php          # 统一流式块 DTO
│   │   ├── Message.php              # 消息 DTO
│   │   ├── ToolCall.php             # 工具调用 DTO
│   │   ├── Usage.php                # 使用量 DTO
│   │   ├── ContentBlock.php         # 内容块 DTO
│   │   └── ActualRequestInfo.php    # 实际请求信息 DTO
│   └── Enums/                       # 枚举定义 ✅ 已实现
│       ├── StreamEventType.php      # 流式事件类型
│       ├── FinishReason.php         # 结束原因
│       ├── ErrorType.php            # 错误类型
│       ├── MessageRole.php          # 消息角色
│       └── ToolType.php             # 工具类型
│
├── Provider/                        # 供应商层 ✅ 已实现
│   ├── Driver/
│   │   ├── ProviderInterface.php    # 供应商接口
│   │   ├── AbstractProvider.php     # 抽象基类
│   │   ├── OpenAIProvider.php       # OpenAI API
│   │   ├── AnthropicProvider.php    # Anthropic API
│   │   ├── OpenAICompatibleProvider.php # OpenAI 兼容
│   │   └── AzureProvider.php        # Azure OpenAI
│   ├── DTO/                         # ⚠️ 旧 DTO（待清理）
│   └── ProviderManager.php          # 供应商管理器
│
├── Protocol/                        # 协议层 ✅ 已实现
│   ├── Driver/
│   │   ├── DriverInterface.php      # 协议驱动接口
│   │   ├── AbstractDriver.php       # 抽象驱动
│   │   ├── OpenAiChatCompletionsDriver.php
│   │   └── AnthropicMessagesDriver.php
│   ├── DTO/                         # ⚠️ 旧 DTO（待清理）
│   └── ProtocolConverter.php        # 协议转换器
│
└── Router/                          # 路由层 ✅ 已实现
    ├── ProxyServer.php              # 核心代理服务
    ├── ChannelRouterService.php     # 渠道路由
    └── Handler/
        ├── StreamHandler.php        # 流式处理
        └── NonStreamHandler.php     # 非流式处理
```

---

## 核心实体定义

### Shared\DTO 实体

所有 DTO 位于 `App\Services\Shared\DTO\`，作为协议无关的中间格式。

#### Request - 统一请求

```php
class Request
{
    public function __construct(
        public string $model,
        public array $messages,           // Message[]
        public ?int $maxTokens = null,
        public ?float $temperature = null,
        public ?float $topP = null,
        public ?int $topK = null,
        public ?bool $stream = false,
        public ?array $stopSequences = null,
        public string|array|null $system = null,
        public ?array $tools = null,
        public $toolChoice = null,
        public ?array $thinking = null,   // Anthropic thinking 参数
        public ?array $metadata = null,
        public ?string $user = null,
        public array $additionalParams = [],
        public ?array $rawRequest = null,
        public ?string $rawBodyString = null,  // Body 透传
        public ?string $queryString = null,    // Query 参数透传
    ) {}
}
```

#### Response - 统一响应

```php
class Response
{
    public function __construct(
        public string $id,
        public string $model,
        public array $choices,            // Choice[]
        public ?Usage $usage = null,
        public ?FinishReason $finishReason = null,
        public ?string $systemFingerprint = null,
        public int $created = 0,
        public ?array $toolCalls = null,
        public ?array $rawResponse = null,
    ) {}
}
```

#### StreamChunk - 统一流式块

```php
class StreamChunk
{
    public function __construct(
        public string $id = '',
        public string $model = '',
        public ?string $contentDelta = null,      // 增量文本
        public ?FinishReason $finishReason = null,
        public ?int $index = 0,
        public StreamEventType $type = StreamEventType::ContentDelta,
        public ?ToolCall $toolCall = null,
        public ?string $reasoningDelta = null,    // 推理内容（Claude/DeepSeek）
        public ?string $signature = null,
        public ?Usage $usage = null,
        public ?string $rawEvent = null,
        public ?string $error = null,
        public ?ErrorType $errorType = null,
        public bool $isPartial = false,
        public ?string $parseError = null,
        // 兼容旧字段
        public string $event = '',
        public array $data = [],
        public string $delta = '',
        public ?array $toolCalls = null,
    ) {}
}
```

### Shared\Enums 枚举

#### StreamEventType - 流式事件类型

```php
enum StreamEventType: string
{
    case Start = 'start';                        // 流开始
    case ContentDelta = 'content_delta';         // 内容增量
    case ReasoningDelta = 'reasoning_delta';     // 推理内容增量
    case ToolUse = 'tool_use';                   // 工具调用
    case ToolUseInputDelta = 'tool_use_input_delta';
    case Finish = 'finish';                      // 流结束
    case Error = 'error';                        // 错误
    case Ping = 'ping';                          // 心跳
}
```

#### FinishReason - 结束原因

```php
enum FinishReason: string
{
    case Stop = 'stop';                  // 自然结束
    case EndTurn = 'end_turn';           // 轮次结束（Anthropic）
    case MaxTokens = 'max_tokens';       // 达到最大 token
    case ToolUse = 'tool_use';           // 调用工具
    case StopSequence = 'stop_sequence'; // 遇到停止序列
}
```

#### ErrorType - 错误类型

```php
enum ErrorType: string
{
    case AuthenticationError = 'authentication_error';
    case InvalidApiKey = 'invalid_api_key';
    case InsufficientQuota = 'insufficient_quota';
    case InvalidRequest = 'invalid_request_error';
    case ContextLengthExceeded = 'context_length_exceeded';
    case RateLimitExceeded = 'rate_limit_exceeded';
    case ModelNotFound = 'model_not_found';
    case InternalError = 'internal_error';
    case ServiceUnavailable = 'service_unavailable';
    case GatewayTimeout = 'gateway_timeout';
    case ContentPolicyViolation = 'content_policy_violation';
}
```

---

## 层级职责

### Provider 层

**定位**：与上游 AI 供应商通信

**核心接口** (`ProviderInterface`):

| 方法 | 职责 |
|------|------|
| `send(Request): Response` | 发送同步请求 |
| `sendStream(Request): Generator` | 发送流式请求，返回 `StreamChunk` 生成器 |
| `getModels(): array` | 获取支持的模型列表 |
| `healthCheck(): bool` | 健康检查 |
| `isAvailable(): bool` | 检查是否可用（熔断器状态等） |

**抽象基类** (`AbstractProvider`) 提供的功能：
- HTTP 请求发送
- 重试机制（指数退避）
- 熔断器模式
- Header 透传
- 流式响应解析

**子类必须实现的抽象方法**：

```php
abstract public function getDefaultBaseUrl(): string;
abstract public function buildRequestBody(Request $request): array;
abstract public function parseResponse(array $response): Response;
abstract public function parseStreamChunk(string $rawChunk): ?StreamChunk;
abstract public function getEndpoint(Request $request): string;
abstract public function getHeaders(): array;
```

### Protocol 层

**定位**：协议转换（OpenAI/Anthropic）

**核心接口** (`DriverInterface`):

| 方法 | 职责 |
|------|------|
| `parseRequest(array): Request` | 解析客户端请求 → Shared\DTO |
| `buildResponse(Response): array` | 构建客户端响应 |
| `buildStreamChunk(StreamChunk): string` | 构建流式 SSE 输出 |
| `buildStreamDone(): string` | 构建流结束标记 |
| `validateRequest(array): bool` | 验证请求格式 |
| `extractModel(array): string` | 提取模型名称 |
| `buildErrorResponse(string, string, int): array` | 构建错误响应 |

---

## 数据流

### 非流式请求

```
客户端请求 (JSON)
      │
      │ Protocol::parseRequest()
      ▼
Shared\DTO\Request
      │
      │ Provider::buildRequestBody()
      ▼
上游请求 (JSON)
      │
      │ HTTP POST
      ▼
上游响应 (JSON)
      │
      │ Provider::parseResponse()
      ▼
Shared\DTO\Response
      │
      │ Protocol::buildResponse()
      ▼
客户端响应 (JSON)
```

### 流式请求

```
客户端请求 (JSON, stream=true)
      │
      │ Protocol::parseRequest()
      ▼
Shared\DTO\Request
      │
      │ Provider::sendStream() → Generator
      ▼
上游 SSE 流
      │
      │ Provider::parseStreamChunk() [循环]
      ▼
Shared\DTO\StreamChunk
      │
      │ Protocol::buildStreamChunk() [循环]
      ▼
客户端 SSE 流
      │
      │ Protocol::buildStreamDone()
      ▼
流结束标记
```

---

## 实现状态

### ✅ 已完成

| 模块 | 文件数 | 状态 |
|------|--------|------|
| Shared\DTO | 8 | 全部实现 |
| Shared\Enums | 5 | 全部实现 |
| Provider\Driver | 6 | 全部实现 |
| Protocol\Driver | 4 | 全部实现 |
| Router | 5 | 全部实现 |

### ⚠️ 待清理

**Provider\DTO 旧文件**（已被 Shared\DTO 替代）：
- `ProviderRequest.php`
- `ProviderResponse.php`
- `ProviderStreamChunk.php`
- `TokenUsage.php`

**Protocol\DTO 旧文件**（已被 Shared\DTO 替代）：
- `StandardRequest.php`
- `StandardResponse.php`
- `StandardStreamEvent.php`
- `StandardMessage.php`
- `StandardToolCall.php`
- `StandardUsage.php`
- `ContentBlock.php`

### 📝 设计偏离说明

#### 1. DTO 包含转换方法

**原规划**：DTO 应该是纯数据容器，不包含业务逻辑。

**实际实现**：DTO 包含了 `toOpenAI()` / `toAnthropic()` 等转换方法。

**原因**：
- 提供便利性，转换逻辑内聚
- 减少 Provider/Protocol 层的代码量
- IDE 自动补全友好

#### 2. StreamChunk 保留兼容字段

```php
// 兼容旧字段（临时保留）
public string $event = '';
public array $data = [];
public string $delta = '';
public ?array $toolCalls = null;
```

**原因**：渐进式迁移，保持向后兼容。

#### 3. Request 支持透传

```php
public ?string $rawBodyString = null,  // Body 透传
public ?string $queryString = null,    // Query 参数透传
```

**原因**：支持请求透传场景，减少不必要的序列化/反序列化。

---

## 清理计划

### 阶段一：验证兼容性

- [ ] 确认所有代码已使用 `Shared\DTO`
- [ ] 运行完整测试套件
- [ ] 确认无直接引用旧 DTO 的代码

### 阶段二：创建别名类

为旧 DTO 创建别名，标记为 deprecated：

```php
// Provider\DTO\ProviderStreamChunk.php
namespace App\Services\Provider\DTO;

/**
 * @deprecated 使用 App\Services\Shared\DTO\StreamChunk 代替
 * @see \App\Services\Shared\DTO\StreamChunk
 */
class ProviderStreamChunk extends \App\Services\Shared\DTO\StreamChunk {}
```

### 阶段三：删除旧文件

清理以下文件：
- `app/Services/Provider/DTO/ProviderRequest.php`
- `app/Services/Provider/DTO/ProviderResponse.php`
- `app/Services/Provider/DTO/ProviderStreamChunk.php`
- `app/Services/Provider/DTO/TokenUsage.php`
- `app/Services/Protocol/DTO/StandardRequest.php`
- `app/Services/Protocol/DTO/StandardResponse.php`
- `app/Services/Protocol/DTO/StandardStreamEvent.php`
- `app/Services/Protocol/DTO/StandardMessage.php`
- `app/Services/Protocol/DTO/StandardToolCall.php`
- `app/Services/Protocol/DTO/StandardUsage.php`
- `app/Services/Protocol/DTO/ContentBlock.php`

---

## 扩展指南

### 添加新供应商

1. 在 `Provider\Driver\` 创建新类，继承 `AbstractProvider`
2. 实现抽象方法：
   - `getDefaultBaseUrl()` - API 基础 URL
   - `buildRequestBody()` - 构建请求体
   - `parseResponse()` - 解析响应
   - `parseStreamChunk()` - 解析流式块
   - `getEndpoint()` - API 端点
   - `getHeaders()` - 请求头
3. 在 `ProviderManager` 中注册

### 添加新协议

1. 在 `Protocol\Driver\` 创建新类，实现 `DriverInterface`
2. 实现接口方法：
   - `parseRequest()` - 解析请求
   - `buildResponse()` - 构建响应
   - `buildStreamChunk()` - 构建流式块
   - `buildStreamDone()` - 构建结束标记
3. 在 `ProtocolConverter` 中注册

---

## 参考链接

- [OpenAI Chat Completions API](https://platform.openai.com/docs/api-reference/chat)
- [Anthropic Messages API](https://docs.anthropic.com/en/api/messages)