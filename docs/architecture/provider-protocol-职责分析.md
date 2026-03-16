# Provider 驱动与 Protocol 驱动职责分析与重构方案

## 问题背景

当前架构中存在职责重叠问题：
- `ProviderStreamChunk` 中有 `toOpenAIChunk()`/`toAnthropicEvent()` 方法
- Protocol 驱动中也有 `parseStreamEvent()`/`buildStreamChunk()` 方法
- 两层都在做"解析上游响应"的工作

---

## 文件结构

### Provider 层

```
app/Services/Provider/
├── Driver/
│   ├── ProviderInterface.php      # 供应商接口
│   ├── AbstractProvider.php       # 抽象基类（HTTP通信、重试、熔断）
│   ├── OpenAIProvider.php         # OpenAI 官方 API
│   ├── AnthropicProvider.php      # Anthropic API
│   ├── OpenAICompatibleProvider.php # OpenAI 兼容 API
│   └── AzureProvider.php          # Azure OpenAI
├── DTO/
│   ├── ProviderRequest.php        # 供应商请求 DTO
│   ├── ProviderResponse.php       # 供应商响应 DTO
│   ├── ProviderStreamChunk.php    # 流式响应块 DTO ⚠️ 有冗余方法
│   ├── TokenUsage.php             # Token 使用量 DTO
│   └── ActualRequestInfo.php      # 实际请求信息 DTO
├── Exceptions/
│   └── ProviderException.php      # 供应商异常
└── ProviderManager.php            # 供应商管理器
```

### Protocol 层

```
app/Services/Protocol/
├── Driver/
│   ├── DriverInterface.php        # 协议驱动接口
│   ├── AbstractDriver.php         # 抽象驱动基类
│   ├── OpenAiChatCompletionsDriver.php  # OpenAI 协议驱动
│   └── AnthropicMessagesDriver.php      # Anthropic 协议驱动
├── DTO/
│   ├── StandardRequest.php        # 标准请求 DTO
│   ├── StandardResponse.php       # 标准响应 DTO
│   ├── StandardStreamEvent.php    # 标准流式事件 DTO ⚠️ 与 ProviderStreamChunk 重复
│   ├── StandardMessage.php        # 标准消息 DTO
│   ├── StandardToolCall.php       # 标准工具调用 DTO
│   ├── StandardUsage.php          # 标准使用量 DTO
│   └── ContentBlock.php           # 内容块 DTO
├── Exceptions/
│   ├── ProtocolException.php      # 协议异常基类
│   ├── UnsupportedProtocolException.php # 不支持的协议异常
│   └── ConversionException.php    # 转换异常
├── DriverManager.php              # 驱动管理器
└── ProtocolConverter.php          # 协议转换器
```

---

## 核心实体定义

### 层次结构

```
┌─────────────────────────────────────────────────────────┐
│              客户端请求/响应（JSON 格式）                 │
│          OpenAI Chat Completions / Anthropic Messages    │
└─────────────────────────────────────────────────────────┘
                          │
                          │ parseRequest() / buildResponse()
                          ▼
┌─────────────────────────────────────────────────────────┐
│              Shared\DTO 实体（协议无关）                  │
│                   统一中间格式                            │
└─────────────────────────────────────────────────────────┘
                          │
                          │ buildRequestBody() / parseResponse()
                          ▼
┌─────────────────────────────────────────────────────────┐
│              上游请求/响应（JSON 格式）                   │
│          OpenAI API / Anthropic API 原始格式             │
└─────────────────────────────────────────────────────────┘
```

**设计原则**：
- 不引入额外的 Entity 层，避免过度设计
- Protocol 层直接操作 JSON ↔ Shared\DTO 的转换
- Provider 层直接操作上游 JSON ↔ Shared\DTO 的转换
- 减少转换层级，提升性能

### Shared\DTO 实体（协议无关）

**文件**: `app/Services/Shared/DTO/`

**设计原则**：
- 不引入额外的 Entity 层，避免过度设计
- 所有 DTO 都是**纯数据容器**，不包含任何业务逻辑
- DTO 不包含 `from*()` 解析方法（由 Provider 层负责）
- DTO 不包含 `to*()` 转换方法（由 Protocol 层负责）

**字段命名约定**：
- 使用驼峰命名法（camelCase）
- 统一使用完整语义化名称，避免缩写

```php
// Request.php - 统一请求格式
class Request
{
    public string $model;
    public array $messages; // Message[]
    public ?int $maxTokens = null;
    public ?float $temperature = null;
    public ?float $topP = null;
    public ?int $topK = null;
    public ?bool $stream = false;
    public ?array $stopSequences = null;
    public ?string $system = null;
    public ?array $tools = null;
    public $toolChoice = null;
    public ?array $thinking = null;
    public ?array $metadata = null;
}

// Response.php - 统一响应格式
class Response
{
    public string $id;
    public string $model;
    public array $choices; // Choice[]
    public ?Usage $usage = null;
    public ?string $stopReason = null;
}

// StreamChunk.php - 统一流式块格式
class StreamChunk
{
    public string $id;
    public string $model;
    public ?string $contentDelta = null;      // 增量文本（原 delta）
    public ?FinishReason $finishReason = null; // ⭐ 使用枚举替代字符串
    public ?int $index = 0;

    // 事件类型（原 event）
    public StreamEventType $type = StreamEventType::ContentDelta;

    // 工具调用相关（单数，因为每次只有一个增量）
    public ?ToolCall $toolCall = null;

    // 推理内容（Claude/DeepSeek 等）
    public ?string $reasoningDelta = null;
    public ?string $signature = null;

    // Token 使用量
    public ?Usage $usage = null;

    // 透传原始数据（用于 Anthropic 特殊事件）
    public ?string $rawEvent = null;

    // 错误信息
    public ?string $error = null;
    public ?ErrorType $errorType = null;      // ⭐ 使用枚举替代字符串

    // 解析状态
    public bool $isPartial = false;           // 是否为部分解析结果
    public ?string $parseError = null;        // 解析失败原因
}

// Message.php - 统一消息格式
class Message
{
    public MessageRole $role;                 // 使用枚举
    public ?string $content = null;
    public ?array $toolCalls = null; // ToolCall[]
    public ?string $toolCallId = null;
    public ?array $contentBlocks = null; // ContentBlock[] (Anthropic)
}

// ToolCall.php - 统一工具调用格式
class ToolCall
{
    public ?string $id = null;
    public ToolType $type = ToolType::Function; // 使用枚举
    public ?string $name = null;
    public ?string $arguments = null;         // 字符串形式
    public ?int $index = null;                // 流式场景中的索引
    public ?string $callId = null;            // Anthropic uses tool_use_id
}

// Usage.php - 统一使用量格式（合并 TokenUsage + StandardUsage）
class Usage
{
    public int $inputTokens = 0;
    public int $outputTokens = 0;
    public ?int $cacheReadInputTokens = null;
    public ?int $cacheCreationInputTokens = null;
}

// ContentBlock.php - 内容块（Anthropic 特有）
class ContentBlock
{
    public string $type; // text, tool_use, thinking
    public ?string $text = null;
    public ?string $thinking = null;
    public ?string $signature = null;
    public ?string $id = null;
    public ?string $name = null;
    public ?array $input = null;
}
```

---

## 枚举定义

### StreamEventType - 流式事件类型

**文件**: `app/Services/Shared/Enums/StreamEventType.php`

```php
<?php

namespace App\Services\Shared\Enums;

/**
 * 流式事件类型枚举
 */
enum StreamEventType: string
{
    case Start = 'start';                        // 流开始
    case ContentDelta = 'content_delta';         // 内容增量
    case ReasoningDelta = 'reasoning_delta';     // 推理内容增量（Claude/DeepSeek）
    case ToolUse = 'tool_use';                   // 工具调用
    case ToolUseInputDelta = 'tool_use_input_delta'; // 工具调用输入增量
    case Finish = 'finish';                      // 流结束
    case Error = 'error';                        // 错误
    case Ping = 'ping';                          // 心跳

    /**
     * 判断是否为内容类型事件
     */
    public function isContent(): bool
    {
        return in_array($this, [
            self::ContentDelta,
            self::ReasoningDelta,
            self::ToolUse,
            self::ToolUseInputDelta,
        ]);
    }

    /**
     * 判断是否为控制类型事件
     */
    public function isControl(): bool
    {
        return in_array($this, [
            self::Start,
            self::Finish,
            self::Error,
            self::Ping,
        ]);
    }
}
```

### FinishReason - 结束原因

**文件**: `app/Services/Shared/Enums/FinishReason.php`

```php
<?php

namespace App\Services\Shared\Enums;

/**
 * 结束原因枚举
 */
enum FinishReason: string
{
    case Stop = 'stop';                         // 自然结束（遇到 stop 序列）
    case EndTurn = 'end_turn';                  // 轮次结束（Anthropic）
    case MaxTokens = 'max_tokens';              // 达到最大 token 限制
    case ToolUse = 'tool_use';                  // 调用工具
    case StopSequence = 'stop_sequence';        // 遇到停止序列（Anthropic）

    /**
     * 转换为 OpenAI 格式
     */
    public function toOpenAI(): string
    {
        return match ($this) {
            self::EndTurn => 'stop',
            self::ToolUse => 'tool_calls',
            self::StopSequence => 'stop',
            default => $this->value,
        };
    }

    /**
     * 转换为 Anthropic 格式
     */
    public function toAnthropic(): string
    {
        return match ($this) {
            self::Stop => 'end_turn',
            self::ToolUse => 'tool_use',
            self::MaxTokens => 'max_tokens',
            default => $this->value,
        };
    }

    /**
     * 从 OpenAI 格式创建
     */
    public static function fromOpenAI(string $reason): self
    {
        return match ($reason) {
            'stop' => self::Stop,
            'tool_calls' => self::ToolUse,
            'length' => self::MaxTokens,
            default => self::Stop,
        };
    }

    /**
     * 从 Anthropic 格式创建
     */
    public static function fromAnthropic(string $reason): self
    {
        return match ($reason) {
            'end_turn' => self::EndTurn,
            'max_tokens' => self::MaxTokens,
            'stop_sequence' => self::StopSequence,
            'tool_use' => self::ToolUse,
            default => self::EndTurn,
        };
    }
}
```

### ErrorType - 错误类型

**文件**: `app/Services/Shared/Enums/ErrorType.php`

```php
<?php

namespace App\Services\Shared\Enums;

/**
 * 错误类型枚举
 */
enum ErrorType: string
{
    // 认证错误
    case AuthenticationError = 'authentication_error';
    case InvalidApiKey = 'invalid_api_key';
    case InsufficientQuota = 'insufficient_quota';

    // 请求错误
    case InvalidRequest = 'invalid_request_error';
    case ContextLengthExceeded = 'context_length_exceeded';
    case RateLimitExceeded = 'rate_limit_exceeded';
    case ModelNotFound = 'model_not_found';

    // 服务器错误
    case InternalError = 'internal_error';
    case ServiceUnavailable = 'service_unavailable';
    case GatewayTimeout = 'gateway_timeout';

    // 内容错误
    case ContentPolicyViolation = 'content_policy_violation';

    /**
     * 获取错误类型的HTTP状态码
     */
    public function getHttpStatusCode(): int
    {
        return match ($this) {
            self::AuthenticationError,
            self::InvalidApiKey => 401,

            self::InsufficientQuota,
            self::RateLimitExceeded => 429,

            self::InvalidRequest,
            self::ContextLengthExceeded,
            self::ModelNotFound,
            self::ContentPolicyViolation => 400,

            self::InternalError => 500,
            self::ServiceUnavailable => 503,
            self::GatewayTimeout => 504,
        };
    }

    /**
     * 获取错误类型的可读描述
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::AuthenticationError => 'Authentication failed',
            self::InvalidApiKey => 'Invalid API key provided',
            self::InsufficientQuota => 'You exceeded your current quota',
            self::InvalidRequest => 'Invalid request parameters',
            self::ContextLengthExceeded => 'Context length exceeded limit',
            self::RateLimitExceeded => 'Rate limit exceeded',
            self::ModelNotFound => 'Model not found',
            self::InternalError => 'Internal server error',
            self::ServiceUnavailable => 'Service temporarily unavailable',
            self::GatewayTimeout => 'Gateway timeout',
            self::ContentPolicyViolation => 'Content policy violation',
        };
    }

    /**
     * 从 OpenAI 错误类型创建
     */
    public static function fromOpenAI(string $type): self
    {
        return match ($type) {
            'invalid_api_key' => self::InvalidApiKey,
            'insufficient_quota' => self::InsufficientQuota,
            'rate_limit_exceeded' => self::RateLimitExceeded,
            'context_length_exceeded' => self::ContextLengthExceeded,
            'model_not_found' => self::ModelNotFound,
            default => self::InvalidRequest,
        };
    }

    /**
     * 从 Anthropic 错误类型创建
     */
    public static function fromAnthropic(string $type): self
    {
        return match ($type) {
            'authentication_error' => self::AuthenticationError,
            'rate_limit_error' => self::RateLimitExceeded,
            'context_length_exceeded' => self::ContextLengthExceeded,
            'not_found_error' => self::ModelNotFound,
            'overloaded_error' => self::ServiceUnavailable,
            default => self::InvalidRequest,
        };
    }

    /**
     * 转换为 OpenAI 格式
     */
    public function toOpenAI(): string
    {
        return match ($this) {
            self::AuthenticationError => 'invalid_api_key',
            self::InsufficientQuota => 'insufficient_quota',
            self::RateLimitExceeded => 'rate_limit_exceeded',
            self::ContextLengthExceeded => 'context_length_exceeded',
            self::ModelNotFound => 'model_not_found',
            self::ServiceUnavailable => 'server_error',
            default => 'invalid_request_error',
        };
    }

    /**
     * 转换为 Anthropic 格式
     */
    public function toAnthropic(): string
    {
        return match ($this) {
            self::AuthenticationError => 'authentication_error',
            self::RateLimitExceeded => 'rate_limit_error',
            self::ContextLengthExceeded => 'context_length_exceeded',
            self::ModelNotFound => 'not_found_error',
            self::ServiceUnavailable => 'overloaded_error',
            default => 'invalid_request_error',
        };
    }
}
```

### MessageRole - 消息角色

**文件**: `app/Services/Shared/Enums/MessageRole.php`

```php
<?php

namespace App\Services\Shared\Enums;

/**
 * 消息角色枚举
 */
enum MessageRole: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';

    /**
     * 判断是否为系统角色
     */
    public function isSystem(): bool
    {
        return $this === self::System;
    }

    /**
     * 判断是否为用户角色
     */
    public function isUser(): bool
    {
        return $this === self::User;
    }

    /**
     * 判断是否为助手角色
     */
    public function isAssistant(): bool
    {
        return $this === self::Assistant;
    }

    /**
     * 判断是否为工具角色
     */
    public function isTool(): bool
    {
        return $this === self::Tool;
    }
}
```

### ToolType - 工具类型

**文件**: `app/Services/Shared/Enums/ToolType.php`

```php
<?php

namespace App\Services\Shared\Enums;

/**
 * 工具类型枚举
 */
enum ToolType: string
{
    case Function = 'function';

    /**
     * 获取所有可用的工具类型
     */
    public static function available(): array
    {
        return [
            self::Function,
        ];
    }
}
```

---

## 文件结构-新（重构后）

### Shared 层（新增）

```
app/Services/Shared/
├── DTO/
│   ├── Request.php              # 统一请求 DTO（合并 ProviderRequest + StandardRequest）
│   ├── Response.php             # 统一响应 DTO（合并 ProviderResponse + StandardResponse）
│   ├── StreamChunk.php          # 统一流式块 DTO（合并 ProviderStreamChunk + StandardStreamEvent）
│   ├── Message.php              # 消息 DTO（来自 StandardMessage）
│   ├── ToolCall.php             # 工具调用 DTO（合并部分 TokenUsage + StandardToolCall）
│   ├── Usage.php                # 使用量 DTO（合并 TokenUsage + StandardUsage）
│   ├── ContentBlock.php         # 内容块 DTO（来自 Protocol）
│   └── ActualRequestInfo.php    # 实际请求信息 DTO（来自 Provider）
└── Enums/                       # 枚举定义 ⭐ 新增
    ├── StreamEventType.php      # 流式事件类型枚举
    ├── FinishReason.php         # 结束原因枚举
    ├── ErrorType.php            # 错误类型枚举
    ├── MessageRole.php          # 消息角色枚举
    └── ToolType.php             # 工具类型枚举
```

**重要原则**：
- 所有 DTO 都是**纯数据容器**，不包含任何业务逻辑
- DTO 不包含 `from*()` 解析方法（由 Provider 层负责）
- DTO 不包含 `to*()` 转换方法（由 Protocol 层负责）
- 所有枚举使用 PHP 8.1+ Enum 特性，提供类型安全和IDE自动补全

### Protocol 层

```
app/Services/Protocol/
├── Driver/
│   ├── DriverInterface.php        # 协议驱动接口
│   ├── AbstractDriver.php         # 抽象驱动基类
│   ├── OpenAiChatCompletionsDriver.php  # OpenAI 协议驱动
│   └── AnthropicMessagesDriver.php      # Anthropic 协议驱动
├── DTO/                           # ⭐ 协议特定结构体
│   ├── OpenAI/
│   │   ├── ChatRequest.php        # OpenAI Chat Completions 请求结构体
│   │   ├── ChatResponse.php       # OpenAI Chat Completions 响应结构体
│   │   ├── ChatChunk.php          # OpenAI 流式响应块结构体
│   │   ├── Message.php            # OpenAI 消息结构体
│   │   ├── ToolCall.php           # OpenAI 工具调用结构体
│   │   └── StreamOptions.php      # OpenAI 流式选项结构体
│   └── Anthropic/
│       ├── MessagesRequest.php    # Anthropic Messages 请求结构体
│       ├── MessagesResponse.php   # Anthropic Messages 响应结构体
│       ├── MessagesEvent.php      # Anthropic 流式事件结构体
│       ├── Message.php            # Anthropic 消息结构体
│       ├── ContentBlock.php       # Anthropic 内容块结构体
│       └── Thinking.php           # Anthropic 思考配置结构体
├── Exceptions/
│   ├── ProtocolException.php
│   ├── UnsupportedProtocolException.php
│   └── ConversionException.php
├── DriverManager.php
└── ProtocolConverter.php
```

**协议结构体说明**：
- **用途**：定义各协议的请求/响应结构，提供强类型支持和输入验证
- **位置**：`Protocol\DTO\{协议名}\` 目录下
- **职责**：JSON 序列化/反序列化、字段验证、协议文档化
- **与 Shared\DTO 关系**：协议结构体 → Shared\DTO（单向转换）

### 依赖关系（重构后）

```
┌─────────────────────────────────────────────────────────┐
│                      Shared\DTO                         │
│              （协议无关的中间格式）                        │
└─────────────────────────────────────────────────────────┘
                          ▲
                          │ 转换
                          │
┌─────────────────────────┴───────────────────────────────┐
│                  Protocol\DTO（协议结构体）               │
│              OpenAI / Anthropic 特定格式                  │
│         （强类型、验证、JSON 序列化）                      │
└─────────────────────────────────────────────────────────┘
                          ▲
                          │
            ┌─────────────┴─────────────┐
            │                           │
            │                           │
┌───────────┴───────────┐   ┌───────────┴───────────┐
│    Provider 层        │   │    Protocol 层        │
│  （供应商适配）         │   │  （协议转换）          │
│                       │   │                       │
│  解析上游响应          │   │  解析客户端请求        │
│  构建上游请求          │   │  构建客户端响应        │
└───────────────────────┘   └───────────────────────┘
```

**关键变化**：
- Provider 和 Protocol 互不依赖
- Protocol\DTO 提供**协议特定结构体**，增强类型安全
- Protocol 层负责：JSON ↔ 协议结构体 ↔ Shared\DTO
- DTO 不再属于任何一层，成为独立的中间格式

---

## 当前架构梳理

### Provider 层

**定位**：与上游 AI 供应商通信

**核心类**：
- `OpenAIProvider` - OpenAI 官方 API
- `AnthropicProvider` - Anthropic API
- `OpenAICompatibleProvider` - OpenAI 兼容 API
- `AzureProvider` - Azure OpenAI

**核心方法**：
| 方法 | 职责 |
|------|------|
| `send(ProviderRequest)` | 发送同步请求 |
| `sendStream(ProviderRequest)` | 发送流式请求 |
| `parseStreamChunk(string)` | 解析原始 SSE → `ProviderStreamChunk` |
| `buildRequestBody(ProviderRequest)` | 构建请求体 |
| `parseResponse(array)` | 解析响应 |

### Protocol 层

**定位**：协议转换（OpenAI/Anthropic）

**核心类**：
- `OpenAiChatCompletionsDriver` - OpenAI Chat Completions 协议
- `AnthropicMessagesDriver` - Anthropic Messages 协议

**核心方法**：
| 方法 | 职责 |
|------|------|
| `parseRequest(array)` | 解析原始请求 → `StandardRequest` |
| `buildResponse(StandardResponse)` | 构建响应 |
| `parseStreamEvent(string)` | 解析流式事件 → `StandardStreamEvent` |
| `buildStreamChunk(StandardStreamEvent)` | 构建流式块 |
| `buildUpstreamRequest(StandardRequest)` | 构建上游请求 |
| `parseUpstreamResponse(array)` | 解析上游响应 |

---

## 数据流分析

### 当前实现

#### 非流式请求

```
客户端请求 (OpenAI/Anthropic 协议)
              │
              ▼
┌─────────────────────────────────────┐
│ Protocol 层: parseRequest()         │
│ 原始请求 → StandardRequest          │
└─────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│ Provider 层: buildRequestBody()     │
│ StandardRequest → 上游请求格式       │
└─────────────────────────────────────┘
              │
              ▼
        上游 AI 供应商
              │
              ▼
┌─────────────────────────────────────┐
│ Provider 层: parseResponse()        │
│ 上游响应 → ProviderResponse         │
└─────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│ Protocol 层: buildResponse()        │
│ ProviderResponse → 客户端响应格式    │
└─────────────────────────────────────┘
```

#### 流式请求

```
上游 SSE 流
              │
              ▼
┌─────────────────────────────────────────────────────┐
│ Provider 层: parseStreamChunk()                     │
│ 原始 SSE → ProviderStreamChunk                      │
│                                                     │
│ OpenAIProvider 调用 ProviderStreamChunk::fromOpenAI │
│ AnthropicProvider 调用 ProviderStreamChunk::fromAnthropic │
└─────────────────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────────────────┐
│ Protocol 层: buildStreamChunk()                     │
│ ProviderStreamChunk → 客户端 SSE 格式               │
│                                                     │
│ 通过 ProxyServer::convertStreamChunk() 调用         │
└─────────────────────────────────────────────────────┘
              │
              ▼
        客户端 SSE 流
```

**当前问题**：
- Protocol 层有 `parseStreamEvent()` 方法但未被使用（冗余）
- ProviderStreamChunk 有 `toOpenAIChunk()`/`toAnthropicEvent()` 方法但未被使用（冗余）
- 使用了两套 DTO（ProviderStreamChunk 和 StandardStreamEvent），存在冗余

---

### 重构后

#### 非流式请求

```
客户端请求 (OpenAI/Anthropic 协议 - JSON)
              │
              ▼
┌─────────────────────────────────────────────────────┐
│ Protocol 层: parseRequest()                         │
│                                                     │
│ 步骤 1: JSON → 协议结构体                            │
│   OpenAI: json_decode() → Protocol\DTO\OpenAI\ChatRequest │
│   Anthropic: json_decode() → Protocol\DTO\Anthropic\MessagesRequest │
│   （利用结构体进行字段验证、类型检查）                 │
│                                                     │
│ 步骤 2: 协议结构体 → Shared\DTO\Request              │
│   OpenAI: ChatRequest::toSharedRequest()            │
│   Anthropic: MessagesRequest::toSharedRequest()     │
└─────────────────────────────────────────────────────┘
              │
              ▼
        Shared\DTO\Request
              │
              ▼
┌─────────────────────────────────────────────────────┐
│ Provider 层: buildRequestBody()                     │
│ Shared\DTO\Request → 上游请求格式（数组）            │
└─────────────────────────────────────────────────────┘
              │
              ▼
        上游 AI 供应商
              │
              ▼
┌─────────────────────────────────────────────────────┐
│ Provider 层: parseResponse()                        │
│ 上游响应（数组）→ Shared\DTO\Response               │
└─────────────────────────────────────────────────────┘
              │
              ▼
        Shared\DTO\Response
              │
              ▼
┌─────────────────────────────────────────────────────┐
│ Protocol 层: buildResponse()                        │
│                                                     │
│ 步骤 1: Shared\DTO\Response → 协议结构体             │
│   OpenAI: Protocol\DTO\OpenAI\ChatResponse::fromShared() │
│   Anthropic: Protocol\DTO\Anthropic\MessagesResponse::fromShared() │
│                                                     │
│ 步骤 2: 协议结构体 → JSON                            │
│   OpenAI: ChatResponse::toJson()                    │
│   Anthropic: MessagesResponse::toJson()             │
└─────────────────────────────────────────────────────┘
              │
              ▼
        客户端响应 (JSON)
```

#### 流式请求

```
上游 SSE 流
              │
              ▼
┌─────────────────────────────────────────────────────┐
│ Provider 层: parseStreamChunk()                     │
│ 原始 SSE → Shared\DTO\StreamChunk                   │
│                                                     │
│ OpenAIProvider::parseStreamChunk() 解析 OpenAI 格式 │
│ AnthropicProvider::parseStreamChunk() 解析 Anthropic 格式 │
│ （解析逻辑在 Provider 中，不在 DTO 中）              │
└─────────────────────────────────────────────────────┘
              │
              ▼
      Shared\DTO\StreamChunk
        （纯数据容器）
              │
              ▼
┌─────────────────────────────────────────────────────┐
│ Protocol 层: buildStreamChunk()                     │
│                                                     │
│ 步骤 1: Shared\DTO\StreamChunk → 协议结构体          │
│   OpenAI: Protocol\DTO\OpenAI\ChatChunk::fromShared() │
│   Anthropic: Protocol\DTO\Anthropic\MessagesEvent::fromShared() │
│                                                     │
│ 步骤 2: 协议结构体 → SSE 格式                        │
│   OpenAI: ChatChunk::toSSE()                        │
│   Anthropic: MessagesEvent::toSSE()                 │
└─────────────────────────────────────────────────────┘
              │
              ▼
        客户端 SSE 流
```

**重构改进**：
- 使用统一的 `Shared\DTO`，消除两套 DTO 的冗余
- 引入**协议结构体**，提供强类型支持和输入验证
- 删除 Protocol 层的 `parseStreamEvent()` 方法（职责清晰）
- 删除 StreamChunk 中的 `toOpenAIChunk()`/`toAnthropicEvent()` 方法（职责清晰）
- Provider 层专注"解析上游"，Protocol 层专注"构建客户端"

---

## 问题分析

### 1. 解析逻辑重复

**Provider 层**：
```php
// AbstractProvider::sendStream() 中
$parsed = $this->parseStreamChunk($rawChunk);

// OpenAIProvider::parseStreamChunk()
// 解析逻辑应该在 Provider 中实现，而不是在 DTO 中
public function parseStreamChunk(string $rawEvent): ?StreamChunk
{
    $data = json_decode($rawEvent, true);
    // 解析逻辑...
    return new StreamChunk(...);
}
```

**Protocol 层**：
```php
// OpenAiChatCompletionsDriver
public function parseStreamEvent(string $rawEvent): ?StandardStreamEvent { ... }

// AnthropicMessagesDriver
public function parseStreamEvent(string $rawEvent): ?StandardStreamEvent { ... }
```

**问题**：
- Protocol 层的 `parseStreamEvent()` 方法冗余，解析上游响应是 Provider 层的职责
- 当前 `ProviderStreamChunk::fromOpenAI()`/`fromAnthropic()` 方法不应该存在，DTO 应该是纯数据容器

### 2. ProviderStreamChunk 中的转换方法未使用

**代码验证结果**（2026-03-15）：

```bash
# 搜索方法调用
grep -r "->toOpenAIChunk\(" laravel/app/     # 结果：No files found
grep -r "->toAnthropicEvent\(" laravel/app/  # 结果：No files found
```

**结论**：`toOpenAIChunk()` 和 `toAnthropicEvent()` 方法确实**未被任何代码调用**，属于冗余代码。

```php
class ProviderStreamChunk
{
    // ❌ 以下方法未被调用，应删除（grep 搜索结果为 0）
    public function toOpenAIChunk(string $id, string $model): string { ... }
    public function toAnthropicEvent(): string { ... }
}
```

**原因分析**：当前流式转换通过 `ProxyServer::convertStreamChunk()` 调用 `Protocol::buildStreamChunk()` 实现，未直接使用 DTO 的转换方法。

### 3. Protocol 层职责不清晰

Protocol 层既有：
- `parseStreamEvent()` - 解析上游
- `buildStreamChunk()` - 构建客户端输出

这导致职责边界模糊。

### 4. 两个中间格式

- `ProviderStreamChunk` - Provider 层的中间格式
- `StandardStreamEvent` - Protocol 层的中间格式

两者字段几乎相同，造成冗余。

---

## 核心问题：上游协议 ≠ 客户端协议

### 四种组合场景

| 客户端协议 | 上游协议 | 场景 |
|-----------|---------|------|
| OpenAI | OpenAI | 直接透传 |
| Anthropic | Anthropic | 直接透传 |
| OpenAI | Anthropic | 需要转换 |
| Anthropic | OpenAI | 需要转换 |

### 需要两层抽象的原因

```
客户端协议 ─────────────────────────────────────────────┐
                                                        │
                    Protocol 层                         │
                  (协议转换/格式构建)                     │
                                                        │
                        │                               │
                        ▼                               │
                统一中间格式                               │
                        │                               │
                        ▼                               │
                    Provider 层                         │
                (供应商适配/响应解析)                      │
                        │                               │
                        ▼                               │
上游供应商协议 ──────────────────────────────────────────┘
```

**Provider 层**：适配不同上游供应商
- 知道如何与 OpenAI/Anthropic/Azure/... 通信
- 知道如何解析各自格式的响应

**Protocol 层**：适配不同客户端协议
- 知道 OpenAI 协议的请求/响应格式
- 知道 Anthropic 协议的请求/响应格式

---

### 3. DTO 归属问题

**当前问题**：DTO 放在哪一层？

```
Provider 层有 DTO: ProviderRequest, ProviderResponse, ProviderStreamChunk, ...
Protocol 层有 DTO: StandardRequest, StandardResponse, StandardStreamEvent, ...
```

**依赖关系分析**：

```
如果 Provider 使用 Protocol 的 DTO:
  Provider → Protocol DTO
  Provider 层就依赖了 Protocol 层（不合理）

如果 Provider 有自己的 DTO:
  Provider → Provider DTO
  Protocol → Protocol DTO
  两边独立，但存在重复
```

**选定方案**：DTO 应该是"协议无关的中间格式"，不属于任何一层

```
app/Services/
├── Shared/
│   └── DTO/
│       ├── Request.php          # 合并 ProviderRequest + StandardRequest
│       ├── Response.php         # 合并 ProviderResponse + StandardResponse
│       ├── StreamChunk.php      # 合并 ProviderStreamChunk + StandardStreamEvent
│       ├── Message.php
│       ├── ToolCall.php
│       └── Usage.php
├── Provider/
│   └── ...（使用 Shared\DTO）
└── Protocol/
    └── ...（使用 Shared\DTO）
```

**优点**：
- Provider 层和 Protocol 层都依赖公共 DTO，互不依赖
- 依赖方向清晰：`Provider → Shared`，`Protocol → Shared`
- 消除冗余的 DTO，减少转换开销

---

## 完整职责流程

### 四步流程图

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           完整请求-响应流程                                   │
└─────────────────────────────────────────────────────────────────────────────┘

【请求阶段 - 从客户端到上游】
                              客户端请求 (JSON)
                              OpenAI/Anthropic 协议
                                     │
                                     │ ① Protocol 层: parseRequest()
                                     │    解析客户端格式 → 中间格式
                                     ▼
                         ┌───────────────────────┐
                         │  Shared\DTO\Request   │
                         │    （中间格式）        │
                         └───────────────────────┘
                                     │
                                     │ ② Provider 层: buildRequestBody()
                                     │    中间格式 → 上游格式
                                     ▼
                              上游请求 (JSON)
                              OpenAI/Anthropic API
                                     │
                                     │ 发送到上游供应商
                                     ▼
                              ┌─────────────┐
                              │  上游 AI    │
                              │   供应商    │
                              └─────────────┘

【响应阶段 - 从上游到客户端】
                              ┌─────────────┐
                              │  上游 AI    │
                              │   供应商    │
                              └─────────────┘
                                     │
                                     │ ③ Provider 层: parseResponse()
                                     │    上游格式 → 中间格式
                                     ▼
                         ┌───────────────────────┐
                         │  Shared\DTO\Response  │
                         │    （中间格式）        │
                         └───────────────────────┘
                                     │
                                     │ ④ Protocol 层: buildResponse()
                                     │    中间格式 → 客户端格式
                                     ▼
                              客户端响应 (JSON)
                              OpenAI/Anthropic 协议
```

### 核心方法职责对照表

| 步骤 | 层级 | 方法 | 方向 | 输入 | 输出 | 职责说明 |
|:---:|:---:|------|:---:|------|------|---------|
| ① | Protocol | `parseRequest()` | 客户端 → 中间 | 客户端 JSON | `Shared\DTO\Request` | **解析客户端请求**，理解客户端协议格式 |
| ② | Provider | `buildRequestBody()` | 中间 → 上游 | `Shared\DTO\Request` | 上游 JSON 数组 | **构建上游请求**，适配目标供应商 API |
| ③ | Provider | `parseResponse()` | 上游 → 中间 | 上游 JSON 数组 | `Shared\DTO\Response` | **解析上游响应**，理解供应商响应格式 |
| ④ | Protocol | `buildResponse()` | 中间 → 客户端 | `Shared\DTO\Response` | 客户端 JSON | **构建客户端响应**，匹配客户端期望格式 |

### 流式处理流程

```
【流式请求阶段】
   客户端 SSE 请求
         │
         │ Protocol::parseRequest()
         ▼
   Shared\DTO\Request (stream=true)
         │
         │ Provider::buildRequestBody()
         ▼
   上游 SSE 请求

【流式响应阶段】
   上游 SSE 流
         │
         │ ③ Provider::parseStreamChunk()
         │    循环解析每个原始 SSE 块
         ▼
   ┌───────────────────────┐
   │ Shared\DTO\StreamChunk │ ← 纯数据容器
   └───────────────────────┘
         │
         │ ④ Protocol::buildStreamChunk()
         │    构建 SSE 输出格式
         ▼
   客户端 SSE 流
         │
         │ Protocol::buildStreamDone()
         ▼
   流结束标记
```

### 职责核心原则

| 层级 | 核心职责 | 理解的方向 | 关键能力 |
|------|---------|-----------|---------|
| **Provider 层** | 适配上游供应商 | 理解上游 | 知道如何与不同供应商通信<br>知道如何解析不同供应商的响应格式 |
| **Protocol 层** | 适配客户端协议 | 理解客户端 | 知道不同协议的请求/响应格式<br>知道如何构建符合协议规范的输出 |
| **Shared\DTO** | 中间格式 | 协议无关 | 统一的数据结构<br>消除两层之间的耦合 |

---

## 推荐的职责划分

### Provider 层职责

| 职责 | 方法示例 | 说明 |
|------|---------|------|
| HTTP 通信 | `send()`, `sendStream()` | 发送请求到上游，处理连接、超时 |
| 重试/熔断 | 内部机制 | 容错机制，提升稳定性 |
| 构建请求体 | `buildRequestBody()` | 中间格式 → 上游格式 |
| **解析响应** | `parseResponse()`, `parseStreamChunk()` | 上游格式 → 中间格式（包括流式） |

### Protocol 层职责

| 职责 | 方法示例 | 说明 |
|------|---------|------|
| **解析请求** | `parseRequest()` | 客户端格式 → 中间格式 |
| 构建响应 | `buildResponse()`, `buildStreamChunk()` | 中间格式 → 客户端格式（包括流式） |
| 构建结束标记 | `buildStreamDone()` | 流式结束标记 |
| 错误处理 | `buildErrorResponse()` | 构建协议规范的错误响应 |

### 数据流（重构后）

```
上游 SSE 流
              │
              ▼
┌─────────────────────────────────────────────────────┐
│ Provider 层: parseStreamChunk()                     │
│ 原始 SSE → Shared\DTO\StreamChunk（纯数据容器）      │
│                                                     │
│ OpenAIProvider::parseStreamChunk() 解析 OpenAI 格式 │
│ AnthropicProvider::parseStreamChunk() 解析 Anthropic 格式 │
└─────────────────────────────────────────────────────┘
              │
              ▼
      Shared\DTO\StreamChunk
        （纯数据容器）
              │
              ▼
┌─────────────────────────────────────────────────────┐
│ Protocol 层: buildStreamChunk()                     │
│ Shared\DTO\StreamChunk → 客户端 SSE 格式            │
│                                                     │
│ OpenAiChatCompletionsDriver 构建 OpenAI 格式        │
│ AnthropicMessagesDriver 构建 Anthropic 格式         │
└─────────────────────────────────────────────────────┘
              │
              ▼
        客户端 SSE 流
```

---

## 重构计划

### 阶段一：新建 Shared\DTO 层（不影响现有代码）

**目标**：创建新的 DTO 类，保持向后兼容

```
app/Services/Shared/
└── DTO/
    ├── Request.php              # 统一请求 DTO
    ├── Response.php             # 统一响应 DTO
    ├── StreamChunk.php          # 统一流式块 DTO
    ├── Message.php              # 消息 DTO
    ├── ToolCall.php             # 工具调用 DTO
    ├── Usage.php                # 使用量 DTO
    ├── ContentBlock.php         # 内容块 DTO
    └── ActualRequestInfo.php    # 实际请求信息 DTO
```

**任务清单**：
- [ ] 创建 `app/Services/Shared/DTO/` 目录
- [ ] 创建 `app/Services/Shared/Enums/` 目录
- [ ] 实现 `StreamEventType.php` 枚举
- [ ] 实现 `FinishReason.php` 枚举
- [ ] 实现 `ErrorType.php` 枚举
- [ ] 实现 `MessageRole.php` 枚举
- [ ] 实现 `ToolType.php` 枚举
- [ ] 实现 `Usage.php`（合并 TokenUsage + StandardUsage）
- [ ] 实现 `ToolCall.php`（合并相关字段）
- [ ] 实现 `Message.php`
- [ ] 实现 `ContentBlock.php`
- [ ] 实现 `Request.php`（合并 ProviderRequest + StandardRequest）
- [ ] 实现 `Response.php`（合并 ProviderResponse + StandardResponse）
- [ ] 实现 `StreamChunk.php`（合并 ProviderStreamChunk + StandardStreamEvent）
- [ ] 实现 `ActualRequestInfo.php`
- [ ] 编写单元测试验证 DTO 字段正确性
- [ ] 编写单元测试验证枚举转换逻辑

**验收标准**：
- 所有 DTO 类创建完成
- 单元测试覆盖所有字段
- 现有代码无需修改即可运行

### 阶段二：逐步替换引用

**目标**：将 Provider 和 Protocol 层切换到新 DTO

#### 2.1 Protocol 层迁移

**任务清单**：
- [ ] 更新 `DriverInterface` 方法签名
- [ ] 更新 `OpenAiChatCompletionsDriver` 使用新 DTO
- [ ] 更新 `AnthropicMessagesDriver` 使用新 DTO
- [ ] 更新 `ProtocolConverter` 使用新 DTO
- [ ] 删除 `parseStreamEvent()` 方法
- [ ] 运行 Protocol 层测试，确保功能正常

#### 2.2 Provider 层迁移

**任务清单**：
- [ ] 更新 `AbstractProvider` 方法签名
- [ ] 更新 `OpenAIProvider::parseStreamChunk()` 实现
- [ ] 更新 `AnthropicProvider::parseStreamChunk()` 实现
- [ ] 更新 `OpenAICompatibleProvider` 使用新 DTO
- [ ] 更新 `AzureProvider` 使用新 DTO
- [ ] 更新 `ProviderManager` 使用新 DTO
- [ ] 运行 Provider 层测试，确保功能正常

#### 2.3 调用层迁移

**任务清单**：
- [ ] 更新 `ProxyServer::parseStreamChunk()` 简化逻辑
- [ ] 更新 `ProxyServer::convertStreamChunk()` 使用新 DTO
- [ ] 删除 `ProviderStreamChunk` 到 `StandardStreamEvent` 的手动转换
- [ ] 运行集成测试，确保端到端功能正常

**验收标准**：
- 所有测试通过
- 无编译错误
- 功能回归测试通过

### 阶段三：清理旧代码

**目标**：删除旧的 DTO 文件和冗余方法

#### 3.1 创建别名类（向后兼容）

```php
// app/Services/Provider/DTO/ProviderStreamChunk.php
namespace App\Services\Provider\DTO;

/**
 * @deprecated 使用 App\Services\Shared\DTO\StreamChunk 代替
 * @see \App\Services\Shared\DTO\StreamChunk
 */
class ProviderStreamChunk extends \App\Services\Shared\DTO\StreamChunk {}
```

#### 3.2 删除旧文件

```
删除:
- app/Services/Provider/DTO/ProviderRequest.php
- app/Services/Provider/DTO/ProviderResponse.php
- app/Services/Provider/DTO/ProviderStreamChunk.php
- app/Services/Provider/DTO/TokenUsage.php
- app/Services/Protocol/DTO/StandardRequest.php
- app/Services/Protocol/DTO/StandardResponse.php
- app/Services/Protocol/DTO/StandardStreamEvent.php
- app/Services/Protocol/DTO/StandardMessage.php
- app/Services/Protocol/DTO/StandardToolCall.php
- app/Services/Protocol/DTO/StandardUsage.php

移动到 Shared:
- app/Services/Provider/DTO/ActualRequestInfo.php → Shared/DTO/ActualRequestInfo.php
- app/Services/Protocol/DTO/ContentBlock.php → Shared/DTO/ContentBlock.php
```

#### 3.3 删除冗余方法

```php
// 删除 DriverInterface::parseStreamEvent()
// 删除 ProviderStreamChunk::fromOpenAI()
// 删除 ProviderStreamChunk::fromAnthropic()
// 删除 ProviderStreamChunk::toOpenAIChunk()
// 删除 ProviderStreamChunk::toAnthropicEvent()
```

**验收标准**：
- 所有旧 DTO 文件已删除或标记 deprecated
- 所有测试通过
- 代码覆盖率不降低

### 1. 新建 Shared\DTO 目录

将 Provider 和 Protocol 的 DTO 合并到公共层：

```
app/Services/Shared/DTO/
├── Request.php          # 合并 ProviderRequest + StandardRequest（纯数据容器）
├── Response.php         # 合并 ProviderResponse + StandardResponse（纯数据容器）
├── StreamChunk.php      # 合并 ProviderStreamChunk + StandardStreamEvent（纯数据容器）
├── Message.php          # 合并 Provider 无 + StandardMessage（纯数据容器）
├── ToolCall.php         # 合并 TokenUsage 的工具调用部分 + StandardToolCall（纯数据容器）
├── Usage.php            # 合并 TokenUsage + StandardUsage（纯数据容器）
└── ActualRequestInfo.php # 保留（仅 Provider 使用，但放在公共层，纯数据容器）
```

**重要**：所有 DTO 都必须是纯数据容器，不包含任何业务逻辑方法。

### 2. 删除 DTO 中的解析和转换方法

```php
// 删除 ProviderStreamChunk/StreamChunk 中的这些方法：

// ❌ 删除解析方法（应该是 Provider 层的职责）
public static function fromOpenAI(string $rawEvent): ?self { ... }
public static function fromAnthropic(string $rawEvent): ?self { ... }

// ❌ 删除转换方法（应该是 Protocol 层的职责）
public function toOpenAIChunk(string $id, string $model): string { ... }
public function toAnthropicEvent(): string { ... }
```

### 3. Provider 层实现解析逻辑

```php
// OpenAIProvider.php
class OpenAIProvider extends AbstractProvider
{
    public function parseStreamChunk(string $rawEvent): ?StreamChunk
    {
        // 解析 OpenAI 格式的 SSE 事件
        if (str_starts_with($rawEvent, 'data: ')) {
            $data = json_decode(substr($rawEvent, 6), true);
            // 解析逻辑...
            return new StreamChunk(
                contentDelta: $delta,
                finishReason: $finishReason,
                // ...
            );
        }
        return null;
    }
}

// AnthropicProvider.php
class AnthropicProvider extends AbstractProvider
{
    public function parseStreamChunk(string $rawEvent): ?StreamChunk
    {
        // 解析 Anthropic 格式的 SSE 事件
        if (str_starts_with($rawEvent, 'data: ')) {
            $data = json_decode(substr($rawEvent, 6), true);
            // 解析逻辑...
            return new StreamChunk(
                contentDelta: $delta,
                finishReason: $finishReason,
                // ...
            );
        }
        return null;
    }
}
```

### 4. 删除 Protocol Driver 中的解析方法

```php
interface DriverInterface
{
    // ❌ 删除这个方法，解析是 Provider 层的职责
    // public function parseStreamEvent(string $rawEvent): ?StandardStreamEvent;

    // ✅ 保留这个方法，构建是 Protocol 层的职责
    // 参数改为使用 Shared\DTO\StreamChunk
    public function buildStreamChunk(StreamChunk $chunk): string;
}
```

### 5. 更新所有引用

- Provider 层：`ProviderRequest` → `Shared\DTO\Request`
- Provider 层：`ProviderResponse` → `Shared\DTO\Response`
- Provider 层：`ProviderStreamChunk` → `Shared\DTO\StreamChunk`
- Protocol 层：`StandardRequest` → `Shared\DTO\Request`
- Protocol 层：`StandardResponse` → `Shared\DTO\Response`
- Protocol 层：`StandardStreamEvent` → `Shared\DTO\StreamChunk`

---

## 总结

| 问题 | 现状 | 建议 |
|------|------|------|
| 解析逻辑重复 | Provider 和 Protocol 都有 | Provider 负责，Protocol 删除 |
| 转换方法位置错误 | ProviderStreamChunk 中有未使用的转换方法 | 删除，由 Protocol 层负责 |
| 两个中间格式 | ProviderStreamChunk 和 StandardStreamEvent | 合并或明确职责 |
| Protocol 层职责不清 | 既解析又构建 | 只负责构建 |

**核心原则**：
- **Provider 层**：理解上游，负责"读"（解析上游响应）
- **Protocol 层**：理解客户端，负责"写"（构建客户端响应）

---

## 测试覆盖说明

### 测试矩阵

| 客户端协议 | 上游协议 | 场景 | 测试文件 |
|-----------|---------|------|---------|
| OpenAI | OpenAI | 直接透传 | `OpenAIToOpenAITest.php` |
| OpenAI | Anthropic | 完整转换 | `OpenAIToAnthropicTest.php` |
| Anthropic | Anthropic | 直接透传 | `AnthropicToAnthropicTest.php` |
| Anthropic | OpenAI | 完整转换 | `AnthropicToOpenAITest.php` |

### 测试场景覆盖

每个协议组合需覆盖以下场景：

#### 1. 非流式请求测试

```php
// tests/Feature/Protocol/NonStreamingTest.php

class NonStreamingTest extends TestCase
{
    /** @test */
    public function 普通文本响应()
    {
        // 验证：contentDelta 正确转换
    }

    /** @test */
    public function 推理内容响应()
    {
        // 验证：reasoningDelta 正确转换
    }

    /** @test */
    public function 工具调用响应()
    {
        // 验证：toolCall 正确转换
    }

    /** @test */
    public function 多选响应()
    {
        // 验证：choices 数组正确转换
    }

    /** @test */
    public function token使用量统计()
    {
        // 验证：usage 字段正确转换
    }
}
```

#### 2. 流式请求测试

```php
// tests/Feature/Protocol/StreamingTest.php

class StreamingTest extends TestCase
{
    /** @test */
    public function 流式文本增量()
    {
        // 验证：contentDelta 增量正确累积
    }

    /** @test */
    public function 流式推理内容()
    {
        // 验证：reasoningDelta 增量正确累积
    }

    /** @test */
    public function 流式工具调用()
    {
        // 验证：toolCall 增量正确合并
    }

    /** @test */
    public function 流式开始事件()
    {
        // 验证：type=start 事件正确处理
    }

    /** @test */
    public function 流式结束事件()
    {
        // 验证：finishReason 和 usage 正确处理
    }

    /** @test */
    public function 流式中断恢复()
    {
        // 验证：连接中断后能正确恢复或优雅降级
    }
}
```

#### 3. 错误处理测试

```php
// tests/Feature/Protocol/ErrorHandlingTest.php

class ErrorHandlingTest extends TestCase
{
    /** @test */
    public function 上游返回错误()
    {
        // 验证：错误信息正确传递给客户端
    }

    /** @test */
    public function 上游超时()
    {
        // 验证：超时错误正确处理
    }

    /** @test */
    public function 无效响应格式()
    {
        // 验证：解析失败时的降级处理
    }

    /** @test */
    public function 协议转换失败()
    {
        // 验证：转换异常的正确处理
    }
}
```

#### 4. 边界情况测试

```php
// tests/Feature/Protocol/EdgeCasesTest.php

class EdgeCasesTest extends TestCase
{
    /** @test */
    public function 空响应()
    {
        // 验证：空 choices 的正确处理
    }

    /** @test */
    public function 超长响应()
    {
        // 验证：大文本的正确处理
    }

    /** @test */
    public function 特殊字符编码()
    {
        // 验证：Unicode、Emoji 等特殊字符的正确处理
    }

    /** @test */
    public function 并发请求()
    {
        // 验证：多请求并发时的数据隔离
    }
}
```

### 测试覆盖率目标

| 模块 | 行覆盖率 | 分支覆盖率 |
|------|---------|-----------|
| Shared\DTO | 100% | 100% |
| Provider\Driver | ≥90% | ≥85% |
| Protocol\Driver | ≥90% | ≥85% |
| ProxyServer | ≥80% | ≥75% |

---

## 类型安全改进

### 枚举字段替代字符串

**当前问题**：DTO 中部分字段使用字符串类型，存在类型不安全的风险。

```php
// ❌ 当前实现（类型不安全）
class StandardStreamEvent
{
    public ?string $errorType = null;      // 可能是任意字符串
    public ?string $finishReason = null;   // 可能是任意字符串
}

// 风险示例
$event->errorType = 'invalid_type';        // 编译通过，但运行时可能出错
$event->finishReason = 'unknown_reason';   // IDE 无法提示可用值
```

**改进方案**：使用枚举类型替代字符串。

```php
// ✅ 改进实现（类型安全）
class StreamChunk
{
    public ?ErrorType $errorType = null;       // 枚举类型
    public ?FinishReason $finishReason = null; // 枚举类型
}

// 优势示例
$chunk->errorType = ErrorType::RateLimitExceeded;  // IDE 自动补全
$chunk->finishReason = FinishReason::ToolUse;      // 编译时类型检查

// 错误示例会被 IDE 和静态分析工具检测
$chunk->errorType = 'invalid';  // ❌ 类型错误
```

### 枚举使用示例

```php
use App\Services\Shared\Enums\ErrorType;
use App\Services\Shared\Enums\FinishReason;

// Provider 层解析上游响应
$chunk = new StreamChunk(
    errorType: ErrorType::fromOpenAI($data['error']['type']),
    finishReason: FinishReason::fromAnthropic($data['stop_reason']),
);

// Protocol 层构建客户端响应
$errorType = $chunk->errorType?->toOpenAI();      // 'rate_limit_exceeded'
$finishReason = $chunk->finishReason?->toAnthropic(); // 'tool_use'
```

### 类型安全对比

| 方面 | 字符串类型 | 枚举类型 |
|------|-----------|---------|
| IDE 自动补全 | ❌ 无 | ✅ 支持 |
| 编译时检查 | ❌ 无 | ✅ 有 |
| 运行时错误 | ⚠️ 可能 | ✅ 避免 |
| 重构友好 | ❌ 困难 | ✅ 容易 |
| 文档化 | ❌ 需要额外注释 | ✅ 枚举本身就是文档 |

---

## 错误处理设计

### 错误类型定义

```php
// app/Services/Shared/DTO/StreamChunk.php

use App\Services\Shared\Enums\ErrorType;

class StreamChunk
{
    // ... 其他字段 ...

    // 错误信息
    public ?string $error = null;
    public ?ErrorType $errorType = null;    // 使用枚举替代字符串

    // 解析状态
    public bool $isPartial = false;         // 是否为部分解析结果
    public ?string $parseError = null;      // 解析失败原因
}
```

### 错误处理流程

```
上游错误响应
      │
      ▼
┌─────────────────────────────────────────────────────┐
│ Provider 层: parseResponse() / parseStreamChunk()   │
│                                                     │
│ 1. 捕获上游错误格式                                  │
│ 2. 使用 ErrorType 枚举填充 errorType               │
│ 3. 记录错误日志                                      │
│                                                     │
│ 示例:                                               │
│ $chunk->errorType = ErrorType::fromOpenAI(         │
│     $data['error']['type']                          │
│ );                                                  │
└─────────────────────────────────────────────────────┘
      │
      ▼
┌─────────────────────────────────────────────────────┐
│ Protocol 层: buildStreamChunk()                     │
│                                                     │
│ 1. 检查 StreamChunk->error                         │
│ 2. 使用 ErrorType::toOpenAI() 或 toAnthropic()     │
│    转换为目标协议的错误格式                          │
│ 3. 添加必要的错误详情                                │
│                                                     │
│ 示例:                                               │
│ 'type' => $chunk->errorType->toOpenAI()            │
└─────────────────────────────────────────────────────┘
      │
      ▼
  客户端错误响应
```

### 解析失败处理

```php
// app/Services/Provider/Driver/AbstractProvider.php

use App\Services\Shared\DTO\StreamChunk;
use App\Services\Shared\Enums\StreamEventType;
use App\Services\Shared\Enums\ErrorType;

protected function handleParseError(string $rawEvent, \Throwable $e): ?StreamChunk
{
    Log::warning('Stream chunk parse failed', [
        'raw_event' => $rawEvent,
        'error' => $e->getMessage(),
    ]);

    // 返回部分解析结果或 null
    return new StreamChunk(
        id: uniqid(),
        model: '',
        type: StreamEventType::Error,
        isPartial: true,
        parseError: $e->getMessage(),
    );
}
```

### 降级策略

| 场景 | 降级策略 |
|------|---------|
| 解析失败 | 记录日志，跳过当前块，继续处理后续块 |
| 连接中断 | 返回已累积内容，标记 `isPartial: true` |
| 转换失败 | 返回原始格式（透传模式） |
| 超时 | 根据已接收内容返回部分结果 |

### 错误响应格式

```php
// OpenAI 格式
use App\Services\Shared\Enums\ErrorType;

$errorType = ErrorType::RateLimitExceeded;

[
    'error' => [
        'message' => 'Rate limit exceeded',
        'type' => $errorType->toOpenAI(),        // 'rate_limit_exceeded'
        'code' => $errorType->value,              // 'rate_limit_exceeded'
    ]
]

// Anthropic 格式
[
    'type' => 'error',
    'error' => [
        'type' => $errorType->toAnthropic(),      // 'rate_limit_error'
        'message' => 'Rate limit exceeded',
    ]
]
```

### 使用枚举的优势

1. **类型安全**：IDE 自动补全，避免拼写错误
2. **集中管理**：所有错误类型定义在一处，易于维护
3. **转换逻辑**：内置 `toOpenAI()` / `toAnthropic()` 方法，避免散落的转换代码
4. **扩展性强**：添加新错误类型只需修改枚举类
5. **文档友好**：枚举本身就是文档，包含所有可能的值