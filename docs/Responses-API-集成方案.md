# OpenAI Responses API 集成方案

**项目**: CdApi AI接入网关系统
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
            $table->string('response_id', 255)->unique()->comment('当前响应 ID');
            $table->string('previous_response_id', 255)->nullable()->comment('上一次响应 ID（用于追溯对话链）');
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
            // 注意：response_id 已有 unique 索引，不需要额外 index
            $table->index('previous_response_id'); // 用于追溯对话链
            $table->index(['api_key_id', 'expires_at']); // 用于清理和查询
            $table->index('expires_at'); // 用于过期清理

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
  "previous_response_id": "resp_xyz789",
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

**字段说明**：
- `response_id`: 当前响应的唯一标识
- `previous_response_id`: 上一次响应的ID，形成对话链
- `messages`: 完整的消息历史（包含本次请求和响应）
- 通过 `previous_response_id` 可以追溯完整的对话路径

### 2.3 过期策略

**默认过期时间**：24 小时

**清理方式**：
```php
// 定时任务（每小时执行）
php artisan schedule:run

// 或手动清理
php artisan cdapi:cleanup-response-sessions
```

### 2.4 对话链设计

**关联关系**：
```
resp_001 (previous_response_id: null)
    ↓
resp_002 (previous_response_id: resp_001)
    ↓
resp_003 (previous_response_id: resp_002)
    ↓
resp_004 (previous_response_id: resp_003)
```

**优势**：
1. ✅ 可追溯完整的对话历史链路
2. ✅ 支持对话树/图谱分析
3. ✅ 方便调试和审计
4. ✅ 可检测循环引用问题
5. ✅ 支持对话分支（一个响应可能有多个后续）

**Model 方法**：
```php
// 获取对话链长度
$session->getChainLength(); // 返回对话链中的节点数

// 获取完整对话链（从最早到当前）
$chain = $session->getConversationChain();
// 返回数组：[resp_001, resp_002, resp_003, resp_004]

// 获取上一次响应
$previous = $session->previous(); // 返回 ResponseSession 或 null
```

**使用示例**：
```php
// 追溯完整对话历史
$session = ResponseSession::where('response_id', 'resp_004')->first();
$chain = $session->getConversationChain();

foreach ($chain as $node) {
    echo "Response ID: {$node->response_id}\n";
    echo "Messages: {$node->message_count}\n";
    echo "Tokens: {$node->total_tokens}\n";
}
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

#### 1. ResponsesRequest DTO

```php
// laravel/app/Services/Protocol/Driver/Responses/ResponsesRequest.php
```

**职责**：
- 解析 Responses 请求格式
- 转换为 Chat Completions 格式

#### 2. ResponsesResponse DTO

```php
// laravel/app/Services/Protocol/Driver/Responses/ResponsesResponse.php
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

#### 4. OpenaiResponsesDriver (新协议驱动)

```php
// laravel/app/Services/Protocol/Driver/OpenaiResponsesDriver.php
```

**职责**：
- 解析 Responses 格式请求
- 调用状态管理器
- 构建 Responses 格式响应
- 处理流式响应转换

---

## 四、文件结构

**新增文件清单**：

```
laravel/
├── app/
│   ├── Models/
│   │   └── ResponseSession.php                    # 会话状态 Model
│   ├── Services/
│   │   ├── Response/
│   │   │   └── ResponseStateManager.php           # 状态管理 Service
│   │   └── Protocol/
│   │       └── Driver/
│   │           ├── ResponsesDriver.php            # 协议驱动
│   │           └── Responses/                     # DTO 目录
│   │               ├── ResponsesRequest.php       # 请求 DTO
│   │               └── ResponsesResponse.php      # 响应 DTO
│   └── database/
│       └── migrations/
│           └── ...create_response_sessions_table.php  # 数据库迁移
└── tests/
    ├── Unit/
    │   ├── Services/Response/
    │   │   └── ResponseStateManagerTest.php
    │   └── Protocol/Driver/Responses/
    │       ├── ResponsesRequestTest.php
    │       └── ResponsesResponseTest.php
    └── Feature/
        └── ResponsesApiTest.php
```

**修改文件清单**：

```
laravel/
├── app/Services/Protocol/
│   └── DriverManager.php                          # 注册新 Driver
└── routes/
    └── api.php                                    # 新增路由
```

---

## 五、详细实施步骤

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

**文件**: `laravel/app/Models/ResponseSession.php`

**关键属性**:
```php
protected $fillable = [
    'response_id',           // 当前响应ID
    'previous_response_id',  // 上一次响应ID（对话链）
    'api_key_id',            // API Key ID
    'messages',              // 完整消息历史（JSON）
    'model',                 // 模型名称
    'total_tokens',          // Token消耗
    'message_count',         // 消息数量
    'expires_at',            // 过期时间
];

protected $casts = [
    'messages' => 'array',
    'expires_at' => 'datetime',
];
```

**核心方法**:
```php
// 查找有效会话
public static function findValid(string $responseId, ?int $apiKeyId = null): ?self

// 关联关系
public function apiKey(): BelongsTo
public function previous(): ?self  // 获取上一次响应

// 对话链操作
public function getChainLength(): int  // 对话链长度
public function getConversationChain(): array  // 完整对话链（从最早到当前）

// 辅助方法
public function isExpired(): bool
public function getMessageCount(): int
```

**对话链遍历逻辑**:
```
1. 从当前节点开始
2. 通过 previous_response_id 向前追溯
3. 收集所有节点，形成完整对话链
4. 返回从最早到当前的有序数组
```

### 步骤 3: 创建 Service（20分钟）

**文件**: `laravel/app/Services/Response/ResponseStateManager.php`

**职责**: 会话状态存储和检索

**核心方法**:
```php
class ResponseStateManager
{
    const DEFAULT_EXPIRY_HOURS = 24;  // 默认24小时过期

    // 存储会话状态
    public function store(
        string $responseId,
        array $messages,
        ?int $apiKeyId = null,
        string $model = '',
        int $totalTokens = 0,
        ?string $previousResponseId = null  // ⭐ 对话链关联
    ): ResponseSession

    // 检索会话历史
    public function retrieve(string $responseId, ?int $apiKeyId = null): ?array

    // 追加消息到现有会话
    public function append(string $responseId, array $newMessages, int $additionalTokens = 0): bool

    // 删除会话
    public function forget(string $responseId): bool

    // 清理过期会话
    public function cleanupExpired(): int

    // 获取统计信息
    public function getStats(?int $apiKeyId = null): array
}
```

**核心逻辑**:
```
存储流程:
  1. 检查 response_id 是否已存在
  2. 如果存在 → 更新消息历史和Token统计
  3. 如果不存在 → 创建新记录，设置过期时间
  4. 记录 previous_response_id 形成对话链

检索流程:
  1. 根据 response_id 和 api_key_id 查询
  2. 检查是否过期
  3. 如果有效 → 返回消息历史，更新活跃时间
  4. 如果无效 → 返回 null

清理流程:
  1. 定时任务每小时执行
  2. 删除 expires_at < now() 的记录
  3. 记录清理统计日志
```

### 步骤 4: 创建 ResponsesRequest DTO（30分钟）

**文件**: `laravel/app/Services/Protocol/Driver/Responses/ResponsesRequest.php`

**核心属性**:
```php
class ResponsesRequest implements ProtocolRequest
{
    public string $model = '';                  // 模型名称（必需）
    public string|array $input = '';            // 输入内容（必需）

    // 状态管理
    public ?string $previousResponseId = null;  // 上一次响应ID

    // 可选参数
    public ?string $instructions = null;        // 系统指令
    public ?int $maxTokens = null;              // 最大Token
    public ?float $temperature = null;          // 温度参数
    public ?float $topP = null;                 // Top P
    public ?bool $stream = false;               // 是否流式
    public ?array $tools = null;                // 工具定义
    public $toolChoice = null;                  // 工具选择
    public ?array $metadata = null;             // 元数据
}
```

**核心方法**:
```php
// 从数组创建（带验证）
public static function fromArrayValidated(array $data): self

// 转换为 Chat Completions 格式 ⭐
public function toChatCompletions(?array $historyMessages = null): ChatCompletionRequest
```

**核心转换逻辑**:
```
toChatCompletions 流程:
  1. 创建 ChatCompletionRequest 对象
  2. 复制公共参数（model, maxTokens, temperature等）

  3. 构建消息数组:
     a. 如果有 instructions → 添加 system message
     b. 如果有 historyMessages → 合并历史消息
     c. 转换 input 为消息格式:
        - 字符串 → 单条 user message
        - 消息数组 → 直接使用
        - 内容块数组 → 转换为 user message（多模态）

  4. 设置 messages 到 ChatCompletionRequest
  5. 返回转换后的对象
```

**input 类型处理**:
```
支持三种 input 格式:

1. 字符串类型:
   input: "你好"
   → [{role: "user", content: "你好"}]

2. 消息数组:
   input: [{role: "user", content: "你好"}, ...]
   → 直接使用

3. 内容块数组（多模态）:
   input: [{type: "text", text: "看图"}, {type: "image", url: "..."}]
   → [{role: "user", content: [{type: "text", ...}, {type: "image_url", ...}]}]
```

**关键验证规则**:
- `model`: 必需
- `input`: 必需，支持多种类型
- 其他参数：可选，遵循 OpenAI Responses API 规范

### 步骤 5: 创建 ResponsesResponse DTO（30分钟）

**文件**: `laravel/app/Services/Protocol/Driver/Responses/ResponsesResponse.php`

**核心属性**:
```php
class ResponsesResponse implements ProtocolResponse
{
    public string $id = '';                    // 响应ID
    public string $object = 'response';       // 对象类型
    public int $created = 0;                   // 创建时间
    public string $model = '';                 // 模型名称

    // 响应内容
    public string|array $output = '';          // 输出内容（字符串或内容块）
    public ?array $toolCalls = null;           // 工具调用
    public ?string $stopReason = null;         // 停止原因

    // 元数据
    public ?array $usage = null;               // Token使用量（input_tokens, output_tokens）
    public ?string $systemFingerprint = null; // 系统指纹
}
```

**核心方法**:
```php
// 从 Chat Completions 响应转换 ⭐
public static function fromChatCompletions(ChatCompletionResponse $chat): self

// 转换为消息数组（用于存储）
public function toMessageArray(): array

// 获取总Token数
public function getTotalTokens(): int
```

**核心转换逻辑**:
```
fromChatCompletions 流程:
  1. 复制基础字段（id, model, created, systemFingerprint）
  2. 设置 object = 'response'

  3. 提取 choices[0]:
     a. message.content → output
     b. message.tool_calls → toolCalls
     c. finish_reason → stopReason（映射转换）

  4. 转换 usage:
     prompt_tokens → input_tokens
     completion_tokens → output_tokens

  5. 返回 Responses 格式对象
```

**finish_reason 映射规则**:
```
Chat Completions → Responses API:
  'stop' → 'end_turn'
  'length' → 'max_tokens'
  'tool_calls' → 'tool_use'
  'content_filter' → 'content_filter'
```

**toMessageArray 输出**:
```
返回标准消息格式，用于存储到会话历史:
{
  role: 'assistant',
  content: '响应文本',
  tool_calls: [...] // 如果有工具调用
}
```

---

### 步骤 6: 创建 ResponsesDriver（40分钟）

**文件**: `laravel/app/Services/Protocol/Driver/ResponsesDriver.php`

**职责**: Responses API 协议驱动，处理请求解析、响应转换、状态管理

**核心属性**:
```php
class ResponsesDriver extends AbstractDriver
{
    public const PROTOCOL_NAME = 'openai_responses';

    protected ?Request $currentRequest = null;      // 当前HTTP请求（API Key上下文）
    protected ?string $previousResponseId = null;   // 上一次响应ID
    protected ?array $fullMessages = null;          // 完整消息历史（用于存储）
    protected string $accumulatedOutput = '';       // 流式响应累计输出
}
```

**核心方法签名**:
```php
// 协议管理
public function getProtocolName(): string
public function setRequest(Request $request): void

// 请求处理
public function parseRequest(array $rawRequest): ProtocolRequest
public function extractModel(array $rawRequest): string

// 响应处理
public function buildResponse(ProtocolResponse $response): array
public function buildErrorResponse(string $message, string $type = 'error', int $code = 500): array

// 流式处理
public function buildStreamChunk(StreamChunk $chunk): string
public function buildStreamDone(): string
public function storeStreamSession(string $responseId, string $model, ?array $usage = null): void
```

**核心流程逻辑**:

#### parseRequest 流程:
```
1. 解析 ResponsesRequest
   - 验证必需字段（model, input）
   - 提取 previous_response_id

2. 检索历史消息
   - 如果有 previous_response_id:
     a. 从 ResponseStateManager 检索
     b. 使用 API Key ID 进行隔离验证
     c. 如果检索失败 → 记录警告，开始新对话

3. 转换为 Chat Completions
   - 调用 ResponsesRequest.toChatCompletions(historyMessages)
   - 保存完整消息历史（用于后续存储）

4. 返回 ChatCompletionRequest
```

#### buildResponse 流程:
```
1. 转换响应格式
   - 如果是 ChatCompletionResponse → ResponsesResponse.fromChatCompletions()
   - 否则 → 从 SharedDTO 转换

2. 存储会话状态 ⭐
   - 添加助手回复到消息历史
   - 调用 ResponseStateManager.store()
   - 传递参数:
     * response_id
     * fullMessages（完整历史+新回复）
     * api_key_id
     * model
     * totalTokens
     * previous_response_id（对话链）

3. 返回 Responses 格式数组
```

#### buildStreamChunk 流程:
```
1. 累计输出内容
   - 将 delta/contentDelta 添加到 accumulatedOutput

2. 构建 stream chunk
   - 设置基础字段（id, object='response.chunk', model）
   - 设置输出增量（output）
   - 映射 finish_reason → stop_reason
   - 转换 usage 字段名

3. 返回 SSE 格式字符串
   "data: {json}\n\n"
```

#### storeStreamSession 流程:
```
流式响应完成后调用:

1. 构建完整消息历史
   - fullMessages + [role: 'assistant', content: accumulatedOutput]

2. 存储会话
   - 调用 ResponseStateManager.store()
   - 传递 previous_response_id

3. 清理状态
   - 重置 fullMessages, accumulatedOutput
```

**API Key 获取逻辑**:
```php
private function getCurrentApiKeyId(): ?int
{
    // ⭐ 从 Request attributes 获取（中间件已注入）
    $apiKey = $this->currentRequest->attributes->get('api_key');
    return $apiKey?->id;
}
```

**finish_reason 映射**:
```
Chat Completions → Responses:
  'stop' → 'end_turn'
  'length' → 'max_tokens'
  'tool_calls' → 'tool_use'
  'content_filter' → 'content_filter'
```

---

### 步骤 7: 注册 Driver（10分钟）

**通过 AppServiceProvider 注册新驱动**：

```php
// laravel/app/Providers/AppServiceProvider.php

use App\Services\Protocol\DriverManager;
use App\Services\Protocol\Driver\OpenAiChatCompletionsDriver;
use App\Services\Protocol\Driver\AnthropicMessagesDriver;
use App\Services\Protocol\Driver\ResponsesDriver; // ⭐ 新增

public function boot(): void
{
    // 注册协议驱动
    $this->app->singleton(DriverManager::class, function ($app) {
        $manager = new DriverManager();

        // 注册现有驱动
        $manager->register('openai', new OpenAiChatCompletionsDriver());
        $manager->register('anthropic', new AnthropicMessagesDriver());

        // ⭐ 注册 Responses 驱动
        $manager->register('openai_responses', new ResponsesDriver());

        return $manager;
    });
}
```

---

### 步骤 8: 配置路由和控制器（15分钟）

**步骤 8.1: 在 ProxyController 中添加新方法**：

```php
// laravel/app/Http/Controllers/Api/ProxyController.php

/**
 * Responses API 端点
 */
public function responses(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
{
    // 标记请求为 Responses 协议
    $request->attributes->set('protocol', 'openai_responses');

    return $this->handleRequest($request, 'openai_responses');
}
```

**步骤 8.2: 更新路由配置**：

```php
// laravel/routes/api.php

Route::middleware([AuthenticateApiKey::class])->group(function () {
    Route::prefix('openai/v1')->group(function () {
        Route::post('/chat/completions', [ProxyController::class, 'chatCompletions']);
        Route::post('/completions', [ProxyController::class, 'completions']);
        Route::post('/embeddings', [ProxyController::class, 'embeddings']);
        Route::get('/models', [ProxyController::class, 'models']);

        // ⭐ 新增 Responses API 端点
        Route::post('/responses', [ProxyController::class, 'responses']);
    });
});
```

**路由说明**：
- 端点：`POST /api/openai/v1/responses`
- 认证：通过 `AuthenticateApiKey` 中间件验证 API Key
- 控制器：`ProxyController::responses` 方法
- 协议标识：`openai_responses`

---

## 六、测试计划

### 6.1 单元测试

#### 测试 ResponsesRequest DTO

```php
// tests/Unit/Protocol/Driver/Responses/ResponsesRequestTest.php

/** @test */
public function it_parses_responses_format_request()
{
    $data = [
        'model' => 'gpt-4',
        'input' => '你好',
        'previous_response_id' => 'resp_abc123',
        'max_tokens' => 100,
    ];

    $request = ResponsesRequest::fromArray($data);

    $this->assertEquals('gpt-4', $request->model);
    $this->assertEquals('你好', $request->input);
    $this->assertEquals('resp_abc123', $request->previousResponseId);
    $this->assertEquals(100, $request->maxTokens);
}

/** @test */
public function it_converts_to_chat_completions_without_history()
{
    $request = new ResponsesRequest;
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
    $request = new ResponsesRequest;
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

#### 测试 ResponsesResponse DTO

```php
// tests/Unit/Protocol/Driver/Responses/ResponsesResponseTest.php

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

    $responsesResponse = ResponsesResponse::fromChatCompletions($chatResponse);

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

### 6.2 集成测试

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

## 七、使用示例

### 7.1 基础请求

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

### 7.2 状态管理

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

### 7.3 流式请求

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

## 八、注意事项

### 8.1 性能考虑

**存储影响**：
- 每次响应存储 ~1-5KB JSON
- 24小时过期自动清理
- 预估：1000次请求/天 → 5MB/天

**优化建议**：
- 使用 Redis 缓存活跃会话
- 异步清理过期会话
- 监控存储空间

### 8.2 安全考虑

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

### 8.3 错误处理

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

## 九、时间估算

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

## 十、验收标准

- [ ] 数据库表创建成功
- [ ] Model 和 Service 实现完整
- [ ] ResponsesRequest DTO 转换逻辑正确
- [ ] ResponsesResponse DTO 转换逻辑正确
- [ ] ResponsesDriver 实现完整
- [ ] 流式响应状态存储实现完整
- [ ] Driver 成功注册到 DriverManager
- [ ] 路由配置正确，`/v1/responses` 端点可访问
- [ ] 状态管理功能正常（存储、检索、过期）
- [ ] 单元测试覆盖率 > 80%
- [ ] 集成测试通过
- [ ] 文档完整

---

**下一步**: 开始实施步骤 1，创建数据库表

**重要提醒**:
- 本方案新增独立的 `/api/openai/v1/responses` 端点
- 不修改现有的 `/api/openai/v1/chat/completions` 端点
- 底层通过 ResponsesDriver 转换为 Chat Completions 格式
- 状态管理通过 ResponseStateManager 实现
- Responses 是独立协议，DTO 放在 `Responses/` 目录下
- 已实现：instructions 支持、完整 input 类型支持、流式状态存储
- 已修正：API Key 获取方式、usage 字段命名、数据库索引优化