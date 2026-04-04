# OpenAI Responses API 集成方案

**项目**: CdApi AI代理系统
**目标**: 在 Chat Completions 端点支持 Responses API 格式
**日期**: 2026-04-05
**优先级**: P0 - 最高优先级

---

## 一、核心目标


### 1.1 功能目标

**新增独立的 `/v1/responses` API 端点，支持 Responses API 格式**

**端点**: `POST /v1/responses`

```
客户端发送 Responses 格式请求
    ↓
/v1/responses 端点接收
    ↓
ResponsesDriver 解析并转换为 Chat Completions
    ↓
发送到上游 Chat Completions API
    ↓
响应转换回 Responses 格式
    ↓
返回客户端
```

### 1.2 关键特性

- ✅ 新增独立端点 `/v1/responses`
- ✅ 支持 `input` 字段（字符串或数组）
- ✅ 支持 `previous_response_id` 状态管理 ⭐
- ✅ 响应格式完全符合 Responses API 规范
- ✅ 底层复用现有 Chat Completions 渠道和 Provider

### 1.3 实施范围

**包含**：
- Responses → Chat 请求转换
- Chat → Responses 响应转换
- 状态管理（本地存储）
- 流式响应转换

**不包含**：
- 独立 `/v1/responses` 端点（后续扩展）
- Responses 内置工具（web_search 等）

---

## 二、状态管理设计

### 2.1 为什么需要本地存储？

**问题**：
```
Responses API: 有状态（previous_response_id）
    ↓ 转换
Chat Completions: 无状态（需要完整 messages[]）
```

**解决方案**：
```
客户端发送: {input: "你好", previous_response_id: "resp_123"}
    ↓
CdApi 查询: resp_123 → [历史消息...]
    ↓
合并: [历史消息..., {role: "user", content: "你好"}]
    ↓
发送到 Chat Completions: {messages: [完整历史...]}
```

### 2.2 数据库设计

#### 迁移文件

```php
// laravel/database/migrations/2026_04_05_000001_create_response_sessions_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('response_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('response_id', 255)->unique()->comment('Responses API 返回的 ID');
            $table->unsignedBigInteger('api_key_id')->nullable()->comment('API Key ID');

            // 核心数据
            $table->json('messages')->comment('完整消息历史');
            $table->string('model', 100)->comment('模型名称');

            // 元数据
            $table->unsignedInteger('total_tokens')->default(0)->comment('总 Token 消耗');
            $table->unsignedInteger('message_count')->default(0)->comment('消息数量');

            // 时间管理
            $table->timestamp('expires_at')->comment('过期时间');
            $table->timestamps();

            // 索引
            $table->index('response_id');
            $table->index(['api_key_id', 'expires_at']);
            $table->index('expires_at');

            // 外键（如果 api_keys 表存在）
            // $table->foreign('api_key_id')->references('id')->on('api_keys')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('response_sessions');
    }
};
```

#### 数据表示例

```json
{
  "id": 1,
  "response_id": "resp_abc123",
  "api_key_id": 42,
  "messages": [
    {"role": "user", "content": "你好"},
    {"role": "assistant", "content": "你好！有什么可以帮助你的吗？"},
    {"role": "user", "content": "讲个笑话"}
  ],
  "model": "gpt-4",
  "total_tokens": 150,
  "message_count": 3,
  "expires_at": "2026-04-06 12:00:00",
  "created_at": "2026-04-05 12:00:00",
  "updated_at": "2026-04-05 12:05:00"
}
```

### 2.3 过期策略

**默认过期时间**：24 小时

**清理方式**：
```php
// 定时任务（每小时执行）
php artisan schedule:run

// 或手动清理
php artisan cdapi:cleanup-response-sessions
```

---

## 三、架构设计

### 3.1 整体流程

```
┌──────────────────────────────────────────────────────────────┐
│  客户端请求                                                   │
│  POST /v1/responses                                          │
│  Body: {model, input, previous_response_id}                  │
└──────────────────────────────────────────────────────────────┘
                          ↓
┌──────────────────────────────────────────────────────────────┐
│  ResponsesDriver (新协议驱动)                                 │
│  1. 解析 Responses 格式请求                                   │
│  2. 查询 previous_response_id 历史                            │
│  3. 转换为 Chat Completions 格式                              │
└──────────────────────────────────────────────────────────────┘
                          ↓
┌──────────────────────────────────────────────────────────────┐
│  ResponseStateManager (Service)                              │
│  - retrieve(response_id, api_key_id)                         │
│  - store(response_id, messages, ...)                         │
└──────────────────────────────────────────────────────────────┘
                          ↓
┌──────────────────────────────────────────────────────────────┐
│  上游 Chat Completions API                                    │
│  接收完整 messages[] 数组                                     │
└──────────────────────────────────────────────────────────────┘
                          ↓
┌──────────────────────────────────────────────────────────────┐
│  响应转换                                                     │
│  1. Chat 响应 → Responses 格式                                │
│  2. 存储新 response_id 和完整历史                              │
│  3. 返回 Responses 格式响应                                    │
└──────────────────────────────────────────────────────────────┘
```

### 3.2 核心组件

#### 1. ResponseRequest DTO

```php
// laravel/app/Services/Protocol/Driver/OpenAI/ResponseRequest.php
```

**职责**：
- 解析 Responses 请求格式
- 转换为 Chat Completions 格式

#### 2. ResponseResponse DTO

```php
// laravel/app/Services/Protocol/Driver/OpenAI/ResponseResponse.php
```

**职责**：
- 解析 Responses 响应格式
- 从 Chat Completions 转换

#### 3. ResponseStateManager Service

```php
// laravel/app/Services/Response/ResponseStateManager.php
```

**职责**：
- 会话状态存储和检索
- 过期清理

#### 4. ResponsesDriver (新协议驱动)

```php
// laravel/app/Services/Protocol/Driver/ResponsesDriver.php
```

**职责**：
- 解析 Responses 格式请求
- 调用状态管理器
- 构建 Responses 格式响应
- 处理流式响应转换

---

## 四、详细实施步骤

### 步骤 1: 创建数据库表（15分钟）

```bash
cd laravel
php artisan make:migration create_response_sessions_table
```

编辑迁移文件，使用上述设计。

执行迁移：
```bash
php artisan migrate
```

### 步骤 2: 创建 Model（10分钟）

```php
// laravel/app/Models/ResponseSession.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Responses 会话状态
 */
class ResponseSession extends Model
{
    protected $fillable = [
        'response_id',
        'api_key_id',
        'messages',
        'model',
        'total_tokens',
        'message_count',
        'expires_at',
    ];

    protected $casts = [
        'messages' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * 关联 API Key
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * 是否过期
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * 查找有效会话
     */
    public static function findValid(string $responseId, ?int $apiKeyId = null): ?self
    {
        $query = static::where('response_id', $responseId)
            ->where('expires_at', '>', now());

        if ($apiKeyId !== null) {
            $query->where('api_key_id', $apiKeyId);
        }

        return $query->first();
    }

    /**
     * 获取消息数量
     */
    public function getMessageCount(): int
    {
        return count($this->messages ?? []);
    }
}
```

### 步骤 3: 创建 Service（20分钟）

```php
// laravel/app/Services/Response/ResponseStateManager.php
<?php

namespace App\Services\Response;

use App\Models\ResponseSession;
use Illuminate\Support\Facades\Log;

/**
 * Responses 会话状态管理器
 */
class ResponseStateManager
{
    /**
     * 默认过期时间（小时）
     */
    const DEFAULT_EXPIRY_HOURS = 24;

    /**
     * 存储会话状态
     */
    public function store(
        string $responseId,
        array $messages,
        ?int $apiKeyId = null,
        string $model = '',
        int $totalTokens = 0
    ): ResponseSession {
        // 如果已存在，更新
        $session = ResponseSession::where('response_id', $responseId)->first();

        if ($session) {
            $session->update([
                'messages' => $messages,
                'total_tokens' => $totalTokens,
                'message_count' => count($messages),
                'expires_at' => now()->addHours(self::DEFAULT_EXPIRY_HOURS),
            ]);

            return $session;
        }

        // 创建新会话
        return ResponseSession::create([
            'response_id' => $responseId,
            'api_key_id' => $apiKeyId,
            'messages' => $messages,
            'model' => $model,
            'total_tokens' => $totalTokens,
            'message_count' => count($messages),
            'expires_at' => now()->addHours(self::DEFAULT_EXPIRY_HOURS),
        ]);
    }

    /**
     * 检索会话历史
     */
    public function retrieve(string $responseId, ?int $apiKeyId = null): ?array
    {
        $session = ResponseSession::findValid($responseId, $apiKeyId);

        if (!$session) {
            Log::info('Response session not found or expired', [
                'response_id' => $responseId,
                'api_key_id' => $apiKeyId,
            ]);
            return null;
        }

        // 更新最后活跃时间
        $session->touch();

        return $session->messages;
    }

    /**
     * 追加消息到现有会话
     */
    public function append(
        string $responseId,
        array $newMessages,
        int $additionalTokens = 0
    ): bool {
        $session = ResponseSession::where('response_id', $responseId)->first();

        if (!$session || $session->isExpired()) {
            return false;
        }

        $messages = array_merge($session->messages, $newMessages);

        $session->update([
            'messages' => $messages,
            'message_count' => count($messages),
            'total_tokens' => $session->total_tokens + $additionalTokens,
        ]);

        return true;
    }

    /**
     * 删除会话
     */
    public function forget(string $responseId): bool
    {
        return ResponseSession::where('response_id', $responseId)->delete() > 0;
    }

    /**
     * 清理过期会话
     */
    public function cleanupExpired(): int
    {
        $count = ResponseSession::where('expires_at', '<', now())->delete();

        Log::info('Cleaned up expired response sessions', ['count' => $count]);

        return $count;
    }

    /**
     * 获取会话统计
     */
    public function getStats(?int $apiKeyId = null): array
    {
        $query = ResponseSession::where('expires_at', '>', now());

        if ($apiKeyId !== null) {
            $query->where('api_key_id', $apiKeyId);
        }

        return [
            'active_sessions' => $query->count(),
            'total_messages' => $query->sum('message_count'),
            'total_tokens' => $query->sum('total_tokens'),
        ];
    }
}
```

### 步骤 4: 创建 ResponseRequest DTO（30分钟）

```php
// laravel/app/Services/Protocol/Driver/OpenAI/ResponseRequest.php
<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Shared\DTO\Request as SharedRequest;

/**
 * OpenAI Responses 请求 DTO
 */
class ResponseRequest implements ProtocolRequest
{
    /**
     * 模型名称
     */
    public string $model = '';

    /**
     * 输入内容（字符串或消息数组）
     */
    public string|array $input = '';

    /**
     * 上一次响应ID（状态管理）
     */
    public ?string $previousResponseId = null;

    /**
     * 最大 Token
     */
    public ?int $maxTokens = null;

    /**
     * 温度参数
     */
    public ?float $temperature = null;

    /**
     * Top P
     */
    public ?float $topP = null;

    /**
     * 是否流式
     */
    public ?bool $stream = false;

    /**
     * 工具定义
     */
    public ?array $tools = null;

    /**
     * 工具选择
     */
    public $toolChoice = null;

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): self
    {
        $request = new self;
        $request->model = $data['model'] ?? '';
        $request->input = $data['input'] ?? '';
        $request->previousResponseId = $data['previous_response_id'] ?? null;
        $request->maxTokens = $data['max_tokens'] ?? null;
        $request->temperature = $data['temperature'] ?? null;
        $request->topP = $data['top_p'] ?? null;
        $request->stream = $data['stream'] ?? false;
        $request->tools = $data['tools'] ?? null;
        $request->toolChoice = $data['tool_choice'] ?? null;

        return $request;
    }

    /**
     * 从数组创建（带验证）
     */
    public static function fromArrayValidated(array $data): self
    {
        $request = self::fromArray($data);

        // 验证必填字段
        if (empty($request->model)) {
            throw new \InvalidArgumentException('model is required');
        }

        if (empty($request->input)) {
            throw new \InvalidArgumentException('input is required');
        }

        return $request;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'model' => $this->model,
            'input' => $this->input,
        ];

        // 可选字段
        if ($this->previousResponseId !== null) {
            $result['previous_response_id'] = $this->previousResponseId;
        }

        if ($this->maxTokens !== null) {
            $result['max_tokens'] = $this->maxTokens;
        }

        if ($this->temperature !== null) {
            $result['temperature'] = $this->temperature;
        }

        if ($this->topP !== null) {
            $result['top_p'] = $this->topP;
        }

        if ($this->stream !== false) {
            $result['stream'] = $this->stream;
        }

        if ($this->tools !== null) {
            $result['tools'] = $this->tools;
        }

        if ($this->toolChoice !== null) {
            $result['tool_choice'] = $this->toolChoice;
        }

        return $result;
    }

    /**
     * 转换为 Shared DTO
     */
    public function toSharedDTO(): SharedRequest
    {
        $shared = new SharedRequest;
        $shared->model = $this->model;
        $shared->maxTokens = $this->maxTokens;
        $shared->temperature = $this->temperature;
        $shared->topP = $this->topP;
        $shared->stream = $this->stream;
        $shared->tools = $this->tools;
        $shared->toolChoice = $this->toolChoice;

        // 新增字段
        $shared->input = $this->input;
        $shared->previousResponseId = $this->previousResponseId;

        // input → messages
        if (is_string($this->input)) {
            $shared->messages = [
                \App\Services\Shared\DTO\Message::fromArray([
                    'role' => 'user',
                    'content' => $this->input,
                ])
            ];
        } else {
            // input 已经是消息数组
            $shared->messages = array_map(
                fn($msg) => \App\Services\Shared\DTO\Message::fromArray($msg),
                $this->input
            );
        }

        return $shared;
    }

    /**
     * 从 Shared DTO 创建
     */
    public static function fromSharedDTO(SharedRequest $shared): self
    {
        $request = new self;
        $request->model = $shared->model;
        $request->input = $shared->input ?? $shared->messages;
        $request->previousResponseId = $shared->previousResponseId;
        $request->maxTokens = $shared->maxTokens;
        $request->temperature = $shared->temperature;
        $request->topP = $shared->topP;
        $request->stream = $shared->stream;
        $request->tools = $shared->tools;
        $request->toolChoice = $shared->toolChoice;

        return $request;
    }

    /**
     * ⭐ 转换为 Chat Completions 格式
     *
     * @param array|null $historyMessages 历史消息（从 previous_response_id 检索）
     */
    public function toChatCompletions(?array $historyMessages = null): ChatCompletionRequest
    {
        $chatRequest = new ChatCompletionRequest();
        $chatRequest->model = $this->model;
        $chatRequest->maxTokens = $this->maxTokens;
        $chatRequest->temperature = $this->temperature;
        $chatRequest->topP = $this->topP;
        $chatRequest->stream = $this->stream;
        $chatRequest->tools = $this->tools;
        $chatRequest->toolChoice = $this->toolChoice;

        // ⭐ 核心：处理 input 和历史消息
        if ($historyMessages !== null) {
            // 有历史，合并
            $newMessage = $this->inputToMessage();
            $chatRequest->messages = array_merge($historyMessages, [$newMessage]);
        } else {
            // 无历史，直接转换 input
            $chatRequest->messages = $this->inputToMessages();
        }

        return $chatRequest;
    }

    /**
     * input 转换为单条消息
     */
    private function inputToMessage(): array
    {
        if (is_string($this->input)) {
            return ['role' => 'user', 'content' => $this->input];
        }

        // input 已经是消息格式，返回最后一条
        return is_array($this->input) && isset($this->input[0])
            ? $this->input[count($this->input) - 1]
            : $this->input;
    }

    /**
     * input 转换为消息数组
     */
    private function inputToMessages(): array
    {
        if (is_string($this->input)) {
            return [['role' => 'user', 'content' => $this->input]];
        }

        // input 已经是消息数组
        return $this->input;
    }
}
```

### 步骤 5: 创建 ResponseResponse DTO（30分钟）

```php
// laravel/app/Services/Protocol/Driver/OpenAI/ResponseResponse.php
<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Contracts\ProtocolResponse;
use App\Services\Shared\DTO\Response as SharedResponse;

/**
 * OpenAI Responses 响应 DTO
 */
class ResponseResponse implements ProtocolResponse
{
    /**
     * 响应 ID
     */
    public string $id = '';

    /**
     * 对象类型
     */
    public string $object = 'response';

    /**
     * 创建时间
     */
    public int $created = 0;

    /**
     * 模型名称
     */
    public string $model = '';

    /**
     * 输出内容（字符串或内容块数组）
     */
    public string|array $output = '';

    /**
     * 工具调用
     */
    public ?array $toolCalls = null;

    /**
     * 停止原因
     */
    public ?string $stopReason = null;

    /**
     * Token 使用量
     */
    public ?array $usage = null;

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): self
    {
        $response = new self;
        $response->id = $data['id'] ?? '';
        $response->object = $data['object'] ?? 'response';
        $response->created = $data['created'] ?? time();
        $response->model = $data['model'] ?? '';
        $response->output = $data['output'] ?? '';
        $response->toolCalls = $data['tool_calls'] ?? null;
        $response->stopReason = $data['stop_reason'] ?? null;
        $response->usage = $data['usage'] ?? null;

        return $response;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
            'object' => $this->object,
            'created' => $this->created,
            'model' => $this->model,
            'output' => $this->output,
        ];

        if ($this->toolCalls !== null) {
            $result['tool_calls'] = $this->toolCalls;
        }

        if ($this->stopReason !== null) {
            $result['stop_reason'] = $this->stopReason;
        }

        if ($this->usage !== null) {
            $result['usage'] = $this->usage;
        }

        return $result;
    }

    /**
     * 转换为 Shared DTO
     */
    public function toSharedDTO(): SharedResponse
    {
        $shared = new SharedResponse;
        $shared->id = $this->id;
        $shared->model = $this->model;
        $shared->created = $this->created;

        // output → content
        if (is_string($this->output)) {
            $shared->content = $this->output;
        } elseif (is_array($this->output)) {
            // 内容块数组
            $shared->contentBlocks = $this->output;
        }

        // usage
        if ($this->usage !== null) {
            $shared->usage = new \App\Services\Shared\DTO\Usage;
            $shared->usage->inputTokens = $this->usage['input_tokens'] ?? 0;
            $shared->usage->outputTokens = $this->usage['output_tokens'] ?? 0;
        }

        return $shared;
    }

    /**
     * 从 Shared DTO 创建
     */
    public static function fromSharedDTO(SharedResponse $shared): self
    {
        $response = new self;
        $response->id = $shared->id;
        $response->model = $shared->model;
        $response->created = $shared->created;
        $response->output = $shared->content ?? $shared->contentBlocks ?? '';

        if ($shared->usage !== null) {
            $response->usage = [
                'input_tokens' => $shared->usage->inputTokens,
                'output_tokens' => $shared->usage->outputTokens,
            ];
        }

        return $response;
    }

    /**
     * ⭐ 从 Chat Completions 响应转换
     */
    public static function fromChatCompletions(ChatCompletionResponse $chat): self
    {
        $response = new self;
        $response->id = $chat->id;
        $response->model = $chat->model;
        $response->created = $chat->created;
        $response->object = 'response';

        // choices[0].message → output
        if (!empty($chat->choices)) {
            $choice = $chat->choices[0];
            $message = $choice['message'] ?? [];

            // 提取内容
            $response->output = $message['content'] ?? '';

            // 工具调用
            if (isset($message['tool_calls'])) {
                $response->toolCalls = $message['tool_calls'];
            }

            // 停止原因
            $finishReason = $choice['finish_reason'] ?? null;
            if ($finishReason !== null) {
                $response->stopReason = self::mapFinishReason($finishReason);
            }
        }

        // usage 转换
        if ($chat->usage !== null) {
            $response->usage = [
                'input_tokens' => $chat->usage['prompt_tokens'] ?? 0,
                'output_tokens' => $chat->usage['completion_tokens'] ?? 0,
            ];
        }

        return $response;
    }

    /**
     * 映射 finish_reason → stop_reason
     */
    private static function mapFinishReason(string $finishReason): string
    {
        return match ($finishReason) {
            'stop' => 'end_turn',
            'length' => 'max_tokens',
            'tool_calls' => 'tool_use',
            'content_filter' => 'stop_sequence',
            default => $finishReason,
        };
    }

    /**
     * 获取消息内容（用于存储到会话）
     */
    public function toMessageArray(): array
    {
        $message = [
            'role' => 'assistant',
            'content' => is_string($this->output) ? $this->output : '',
        ];

        if ($this->toolCalls !== null) {
            $message['tool_calls'] = $this->toolCalls;
        }

        return $message;
    }
}
```

**继续查看下一部分...**

---

### 步骤 6: 创建 ResponsesDriver（40分钟）

**创建独立的协议驱动**：

```php
// laravel/app/Services/Protocol/Driver/ResponsesDriver.php
<?php

namespace App\Services\Protocol\Driver;

use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\Contracts\ProtocolResponse;
use App\Services\Protocol\Driver\OpenAI\ResponseRequest;
use App\Services\Protocol\Driver\OpenAI\ResponseResponse;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionRequest;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionResponse;
use App\Services\Shared\DTO\StreamChunk;

/**
 * OpenAI Responses API 协议驱动
 */
class ResponsesDriver extends AbstractDriver
{
    /**
     * 协议名称
     */
    public const PROTOCOL_NAME = 'openai_responses';

    /**
     * 当前处理的 Response ID
     */
    protected ?string $currentResponseId = null;

    /**
     * 完整消息历史（用于存储）
     */
    protected ?array $fullMessages = null;

    /**
     * 获取协议名称
     */
    public function getProtocolName(): string
    {
        return self::PROTOCOL_NAME;
    }

    /**
     * 解析原始请求为协议请求结构体
     */
    public function parseRequest(array $rawRequest): ProtocolRequest
    {
        $responsesRequest = ResponseRequest::fromArrayValidated($rawRequest);

        // 处理 previous_response_id
        $historyMessages = null;
        if ($responsesRequest->previousResponseId) {
            $historyMessages = $this->retrieveHistory($responsesRequest->previousResponseId);

            if ($historyMessages === null) {
                \Log::warning('Previous response not found, starting new conversation', [
                    'previous_response_id' => $responsesRequest->previousResponseId,
                ]);
            }
        }

        // 转换为 Chat Completions 格式
        $chatRequest = $responsesRequest->toChatCompletions($historyMessages);

        // 保存完整消息（用于后续存储）
        $this->fullMessages = $chatRequest->messages;

        return $chatRequest;
    }

    /**
     * 从协议响应结构体构建 Responses 响应数组
     */
    public function buildResponse(ProtocolResponse $response): array
    {
        // 从 Chat Completions 转换
        if ($response instanceof ChatCompletionResponse) {
            $responsesResponse = ResponseResponse::fromChatCompletions($response);
        } else {
            // 从 Shared DTO 转换
            $sharedDTO = $response->toSharedDTO();
            $responsesResponse = ResponseResponse::fromSharedDTO($sharedDTO);
        }

        // ⭐ 存储会话状态
        $this->storeSession($responsesResponse);

        return $responsesResponse->toArray();
    }

    /**
     * 检索历史消息
     */
    private function retrieveHistory(string $responseId): ?array
    {
        $manager = app(\App\Services\Response\ResponseStateManager::class);

        // 获取 API Key ID
        $apiKeyId = $this->getCurrentApiKeyId();

        if (!$apiKeyId) {
            \Log::warning('Cannot retrieve history: no API key context');
            return null;
        }

        return $manager->retrieve($responseId, $apiKeyId);
    }

    /**
     * 存储会话
     */
    private function storeSession(ResponseResponse $response): void
    {
        if (empty($response->id)) {
            return;
        }

        if ($this->fullMessages === null) {
            return;
        }

        $manager = app(\App\Services\Response\ResponseStateManager::class);
        $apiKeyId = $this->getCurrentApiKeyId();

        // 添加助手回复到消息历史
        $fullMessages = $this->fullMessages;
        $fullMessages[] = $response->toMessageArray();

        // 存储会话
        $manager->store(
            $response->id,
            $fullMessages,
            $apiKeyId,
            $response->model,
            $response->usage['total_tokens'] ?? 0
        );

        \Log::info('Response session stored', [
            'response_id' => $response->id,
            'message_count' => count($fullMessages),
        ]);
    }

    /**
     * 获取当前 API Key ID
     */
    private function getCurrentApiKeyId(): ?int
    {
        // 从认证上下文获取
        $user = auth()->user();
        return $user?->api_key_id ?? null;
    }

    /**
     * 从标准格式构建 Responses 流式块
     */
    public function buildStreamChunk(StreamChunk $chunk): string
    {
        $result = [
            'id' => $chunk->id ?: 'resp-'.uniqid(),
            'object' => 'response.chunk',
            'created' => time(),
            'model' => $chunk->model,
        ];

        // 输出增量
        if ($chunk->contentDelta !== null || $chunk->delta !== '') {
            $result['output'] = $chunk->contentDelta ?? $chunk->delta;
        }

        // 完成原因
        if ($chunk->finishReason !== null) {
            $result['stop_reason'] = $this->mapFinishReason($chunk->finishReason->value);
        }

        // Token 使用量
        if ($chunk->usage !== null) {
            $result['usage'] = [
                'input_tokens' => $chunk->usage->inputTokens,
                'output_tokens' => $chunk->usage->outputTokens,
            ];
        }

        return 'data: '.$this->safeJsonEncode($result)."\n\n";
    }

    /**
     * 构建流式结束标记
     */
    public function buildStreamDone(): string
    {
        return "data: [DONE]\n\n";
    }

    /**
     * 获取请求中的模型名称
     */
    public function extractModel(array $rawRequest): string
    {
        return $rawRequest['model'] ?? '';
    }

    /**
     * 构建错误响应
     */
    public function buildErrorResponse(string $message, string $type = 'error', int $code = 500): array
    {
        return [
            'error' => [
                'message' => $message,
                'type' => $type,
                'code' => $code,
            ],
        ];
    }

    /**
     * 映射 finish_reason → stop_reason
     */
    private function mapFinishReason(string $finishReason): string
    {
        return match ($finishReason) {
            'stop' => 'end_turn',
            'length' => 'max_tokens',
            'tool_calls' => 'tool_use',
            'content_filter' => 'stop_sequence',
            default => $finishReason,
        };
    }
}
```

---

### 步骤 7: 注册 Driver（10分钟）

**在 DriverManager 中注册新驱动**：

```php
// laravel/app/Services/Protocol/DriverManager.php

public function __construct()
{
    // 注册现有驱动
    $this->drivers['openai_chat_completions'] = new OpenAiChatCompletionsDriver();
    $this->drivers['anthropic_messages'] = new AnthropicMessagesDriver();

    // ⭐ 注册 Responses 驱动
    $this->drivers['openai_responses'] = new ResponsesDriver();
}
```

---

### 步骤 8: 配置路由（10分钟）

**新增 Responses API 端点路由**：

```php
// laravel/routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProxyController;

Route::prefix('v1')->middleware(['auth:api-key'])->group(function () {
    // 现有 Chat Completions 端点
    Route::post('/chat/completions', [ProxyController::class, 'handle'])
        ->name('api.v1.chat.completions');

    // ⭐ 新增 Responses API 端点
    Route::post('/responses', [ProxyController::class, 'handle'])
        ->name('api.v1.responses');
});
```

**路由说明**：
- 端点：`POST /api/v1/responses`
- 认证：通过 `auth:api-key` 中间件验证 API Key
- 处理器：使用现有的 `ProxyController::handle` 方法（协议驱动会根据路由自动选择）

---

## 五、测试计划

### 5.1 单元测试

#### 测试 ResponseRequest DTO

```php
// tests/Unit/Protocol/Driver/OpenAI/ResponseRequestTest.php

/** @test */
public function it_parses_responses_format_request()
{
    $data = [
        'model' => 'gpt-4',
        'input' => '你好',
        'previous_response_id' => 'resp_abc123',
        'max_tokens' => 100,
    ];

    $request = ResponseRequest::fromArray($data);

    $this->assertEquals('gpt-4', $request->model);
    $this->assertEquals('你好', $request->input);
    $this->assertEquals('resp_abc123', $request->previousResponseId);
    $this->assertEquals(100, $request->maxTokens);
}

/** @test */
public function it_converts_to_chat_completions_without_history()
{
    $request = new ResponseRequest;
    $request->model = 'gpt-4';
    $request->input = 'Hello';

    $chatRequest = $request->toChatCompletions();

    $this->assertCount(1, $chatRequest->messages);
    $this->assertEquals('user', $chatRequest->messages[0]['role']);
    $this->assertEquals('Hello', $chatRequest->messages[0]['content']);
}

/** @test */
public function it_converts_to_chat_completions_with_history()
{
    $request = new ResponseRequest;
    $request->model = 'gpt-4';
    $request->input = '继续';

    $history = [
        ['role' => 'user', 'content' => '讲个笑话'],
        ['role' => 'assistant', 'content' => '为什么...'],
    ];

    $chatRequest = $request->toChatCompletions($history);

    $this->assertCount(3, $chatRequest->messages);
    $this->assertEquals('继续', $chatRequest->messages[2]['content']);
}
```

#### 测试 ResponseResponse DTO

```php
// tests/Unit/Protocol/Driver/OpenAI/ResponseResponseTest.php

/** @test */
public function it_converts_from_chat_completions()
{
    $chatResponse = new ChatCompletionResponse;
    $chatResponse->id = 'chatcmpl_123';
    $chatResponse->model = 'gpt-4';
    $chatResponse->choices = [
        [
            'message' => [
                'role' => 'assistant',
                'content' => '你好！',
            ],
            'finish_reason' => 'stop',
        ],
    ];
    $chatResponse->usage = [
        'prompt_tokens' => 10,
        'completion_tokens' => 5,
    ];

    $responsesResponse = ResponseResponse::fromChatCompletions($chatResponse);

    $this->assertEquals('chatcmpl_123', $responsesResponse->id);
    $this->assertEquals('你好！', $responsesResponse->output);
    $this->assertEquals('end_turn', $responsesResponse->stopReason);
    $this->assertEquals(10, $responsesResponse->usage['input_tokens']);
}
```

#### 测试 ResponseStateManager

```php
// tests/Unit/Services/Response/ResponseStateManagerTest.php

/** @test */
public function it_stores_and_retrieves_session()
{
    $manager = app(ResponseStateManager::class);

    $responseId = 'resp_test_'.uniqid();
    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi!'],
    ];

    // 存储
    $manager->store($responseId, $messages, 1, 'gpt-4');

    // 检索
    $retrieved = $manager->retrieve($responseId, 1);

    $this->assertEquals($messages, $retrieved);
}

/** @test */
public function it_returns_null_for_expired_session()
{
    $manager = app(ResponseStateManager::class);

    $responseId = 'resp_expired_'.uniqid();

    // 创建已过期的会话
    $session = ResponseSession::create([
        'response_id' => $responseId,
        'api_key_id' => 1,
        'messages' => [['role' => 'user', 'content' => 'test']],
        'model' => 'gpt-4',
        'expires_at' => now()->subHour(), // 已过期
    ]);

    // 检索
    $retrieved = $manager->retrieve($responseId, 1);

    $this->assertNull($retrieved);
}
```

### 5.2 集成测试

```php
// tests/Feature/ResponsesApiTest.php

/** @test */
public function it_accepts_responses_format_at_responses_endpoint()
{
    $response = $this->postJson('/api/v1/responses', [
        'model' => 'gpt-4',
        'input' => '你好',
    ], [
        'Authorization' => 'Bearer test-api-key',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'id',
            'object',
            'model',
            'output',
            'usage',
        ]);

    $this->assertEquals('response', $response->json('object'));
}

/** @test */
public function it_maintains_conversation_state_with_previous_response_id()
{
    // 第一次请求
    $response1 = $this->postJson('/api/v1/responses', [
        'model' => 'gpt-4',
        'input' => '我的名字是张三',
    ], [
        'Authorization' => 'Bearer test-api-key',
    ]);

    $responseId = $response1->json('id');

    // 第二次请求，使用 previous_response_id
    $response2 = $this->postJson('/api/v1/responses', [
        'model' => 'gpt-4',
        'input' => '我叫什么名字？',
        'previous_response_id' => $responseId,
    ], [
        'Authorization' => 'Bearer test-api-key',
    ]);

    $response2->assertStatus(200);

    // 验证响应中包含"张三"
    $this->assertStringContainsString('张三', $response2->json('output'));
}
```

---

## 六、使用示例

### 6.1 基础请求

```bash
curl -X POST http://localhost:32126/api/v1/responses \
  -H "Authorization: Bearer your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "gpt-4",
    "input": "你好"
  }'
```

**响应**：
```json
{
  "id": "resp_abc123",
  "object": "response",
  "created": 1712345678,
  "model": "gpt-4",
  "output": "你好！有什么可以帮助你的吗？",
  "stop_reason": "end_turn",
  "usage": {
    "input_tokens": 10,
    "output_tokens": 15
  }
}
```

### 6.2 状态管理

```bash
# 第一次请求
curl -X POST http://localhost:32126/api/v1/responses \
  -H "Authorization: Bearer your-api-key" \
  -H "Content-Type: application/json" \
  -d '{"model": "gpt-4", "input": "我叫李四"}'

# 响应：{"id": "resp_xyz789", "output": "你好李四！", ...}

# 第二次请求（关联历史）
curl -X POST http://localhost:32126/api/v1/responses \
  -H "Authorization: Bearer your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "gpt-4",
    "input": "我叫什么名字？",
    "previous_response_id": "resp_xyz789"
  }'

# 响应：{"output": "你叫李四", ...}
```

### 6.3 流式请求

```bash
curl -X POST http://localhost:32126/api/v1/responses \
  -H "Authorization: Bearer your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "gpt-4",
    "input": "讲个长笑话",
    "stream": true
  }'
```

**流式响应**：
```
data: {"id":"resp_123","object":"response.chunk","output":"有"}

data: {"id":"resp_123","object":"response.chunk","output":"一天"}

data: {"id":"resp_123","object":"response.chunk","output":"..."}

data: {"id":"resp_123","stop_reason":"end_turn","usage":{...}}

data: [DONE]
```

---

## 七、注意事项

### 7.1 性能考虑

**存储影响**：
- 每次响应存储 ~1-5KB JSON
- 24小时过期自动清理
- 预估：1000次请求/天 → 5MB/天

**优化建议**：
- 使用 Redis 缓存活跃会话
- 异步清理过期会话
- 监控存储空间

### 7.2 安全考虑

**API Key 隔离**：
```php
// 确保用户只能访问自己的会话
$messages = $manager->retrieve($responseId, $apiKeyId);
```

**数据清理**：
```bash
# 定时任务清理过期会话
php artisan cdapi:cleanup-response-sessions
```

### 7.3 错误处理

**场景 1：previous_response_id 无效**

```json
{
  "error": {
    "type": "invalid_previous_response_id",
    "message": "上一次响应不存在或已过期，已开始新对话"
  }
}
```

**处理**：记录警告，返回新会话响应

**场景 2：存储失败**

```php
try {
    $manager->store(...);
} catch (\Exception $e) {
    Log::error('Failed to store response session', [
        'response_id' => $responseId,
        'error' => $e->getMessage(),
    ]);
    // 继续返回响应，不影响用户体验
}
```

---

## 八、时间估算

| 步骤 | 任务 | 时间 |
|------|------|------|
| 1 | 创建数据库表 | 15分钟 |
| 2 | 创建 Model | 10分钟 |
| 3 | 创建 Service | 20分钟 |
| 4 | 创建 ResponseRequest DTO | 30分钟 |
| 5 | 创建 ResponseResponse DTO | 30分钟 |
| 6 | 创建 ResponsesDriver | 40分钟 |
| 7 | 注册 Driver | 10分钟 |
| 8 | 配置路由 | 10分钟 |
| 9 | 单元测试 | 30分钟 |
| 10 | 集成测试 | 30分钟 |
| **总计** | | **约 3.5 小时** |

---

## 九、验收标准

- [ ] 数据库表创建成功
- [ ] Model 和 Service 实现完整
- [ ] ResponseRequest DTO 转换逻辑正确
- [ ] ResponseResponse DTO 转换逻辑正确
- [ ] ResponsesDriver 实现完整
- [ ] Driver 成功注册到 DriverManager
- [ ] 路由配置正确，`/v1/responses` 端点可访问
- [ ] 状态管理功能正常（存储、检索、过期）
- [ ] 单元测试覆盖率 > 80%
- [ ] 集成测试通过
- [ ] 文档完整

---

**下一步**: 开始实施步骤 1，创建数据库表

**重要提醒**:
- 本方案新增独立的 `/v1/responses` 端点
- 不修改现有的 `/v1/chat/completions` 端点
- 底层通过 ResponsesDriver 转换为 Chat Completions 格式
- 状态管理通过 ResponseStateManager 实现