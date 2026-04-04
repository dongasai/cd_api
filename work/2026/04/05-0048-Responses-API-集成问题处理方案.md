# Responses API 集成问题处理方案

**审查日期**: 2026-04-05
**审查对象**: docs/Responses-API-集成方案.md
**处理优先级**: P0 (阻塞) / P1 (重要) / P2 (建议)

---

## 一、P0 级问题（阻塞性问题，必须解决）

### 1.1 DTO 类名命名不当

**问题描述**:
- `ResponseRequest` 和 `ResponseResponse` 容易与 HTTP Response 混淆
- 不符合 OpenAI 官方命名规范

**处理方案**:

```php
// ❌ 错误命名
class ResponseRequest implements ProtocolRequest { }
class ResponseResponse implements ProtocolResponse { }

// ✅ 正确命名（复数形式）
class ResponsesRequest implements ProtocolRequest { }
class ResponsesResponse implements ProtocolResponse { }
```

**文件路径**:
```
laravel/app/Services/Protocol/Driver/Responses/ResponsesRequest.php
laravel/app/Services/Protocol/Driver/Responses/ResponsesResponse.php
```

---

### 1.2 DTO 目录结构设计

**问题描述**:
- Responses API 是独立协议，不应放在 OpenAI/ 目录下
- 与 Chat Completions 驱动混淆

**处理方案**:

创建独立目录结构：

```
laravel/app/Services/Protocol/Driver/
├── AbstractDriver.php
├── DriverInterface.php
├── OpenAiChatCompletionsDriver.php
├── AnthropicMessagesDriver.php
├── OpenAI/                          # Chat Completions DTO
│   ├── ChatCompletionRequest.php
│   └── ChatCompletionResponse.php
├── Anthropic/                       # Anthropic DTO
│   ├── MessagesRequest.php
│   └── MessagesResponse.php
└── Responses/                       # 新增：Responses API DTO ⭐
    ├── ResponsesRequest.php
    └── ResponsesResponse.php
```

**命名空间调整**:
```php
namespace App\Services\Protocol\Driver\Responses;
```

---

### 1.3 路由与控制器方法不匹配

**问题描述**:
- ProxyController 没有 `handle` 方法
- 需要新增 `responses()` 方法

**处理方案**:

**步骤 1**: 在 ProxyController 中添加新方法

```php
// laravel/app/Http/Controllers/Api/ProxyController.php

/**
 * Responses API 端点
 */
public function responses(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
{
    return $this->handleRequest($request, 'openai_responses');
}
```

**步骤 2**: 更新路由配置

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

    // ...
});
```

**步骤 3**: 更新 streamResponse 方法支持 Responses 协议

```php
protected function streamResponse(Generator $generator, string $protocol): \Symfony\Component\HttpFoundation\StreamedResponse
{
    $headers = [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'X-Accel-Buffering' => 'no',
        'Connection' => 'keep-alive',
    ];

    if ($protocol === 'anthropic') {
        $headers['anthropic-version'] = '2023-06-01';
    }

    // ⭐ Responses API 可能需要特定的头信息
    if ($protocol === 'openai_responses') {
        $headers['OpenAI-Version'] = '2024-02-15';
    }

    // ... rest of the method
}
```

---

### 1.4 Driver 注册方式修正

**问题描述**:
- 文档使用构造函数注册，与现有代码风格不一致

**处理方案**:

在 DriverManager 初始化时注册（通过服务提供者）：

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

## 二、P1 级问题（重要问题，建议解决）

### 2.1 API Key ID 获取方式修正

**问题描述**:
- 文档使用 `auth()->user()?->api_key_id` 不可靠
- 实际 API Key 信息存储在 Request attributes 中

**处理方案**:

**方案 A**: 通过 Request 对象传递

```php
// laravel/app/Services/Protocol/Driver/ResponsesDriver.php

class ResponsesDriver extends AbstractDriver
{
    /**
     * 当前请求对象（用于获取 API Key 上下文）
     */
    protected ?Request $currentRequest = null;

    /**
     * 设置当前请求上下文
     */
    public function setRequest(Request $request): void
    {
        $this->currentRequest = $request;
    }

    /**
     * 获取当前 API Key ID
     */
    private function getCurrentApiKeyId(): ?int
    {
        if (!$this->currentRequest) {
            return null;
        }

        $apiKey = $this->currentRequest->attributes->get('api_key');

        return $apiKey?->id;
    }
}
```

**方案 B**: 通过 Shared DTO 传递上下文

```php
// laravel/app/Services/Shared/DTO/Request.php

class Request
{
    // 现有字段...

    /**
     * API Key ID（用于状态管理）
     */
    public ?int $apiKeyId = null;

    /**
     * Response ID（用于状态管理）
     */
    public ?string $responseId = null;

    /**
     * 历史消息（从 previous_response_id 检索）
     */
    public ?array $historyMessages = null;
}
```

**推荐方案 B**，因为更符合现有架构设计。

---

### 2.2 流式响应状态存储

**问题描述**:
- 流式响应结束后需要存储完整会话状态
- 当前 `buildStreamChunk` 只处理增量数据

**处理方案**:

**步骤 1**: 在 ResponsesDriver 中维护完整消息

```php
// laravel/app/Services/Protocol/Driver/ResponsesDriver.php

class ResponsesDriver extends AbstractDriver
{
    /**
     * 完整消息历史（包含用户输入和助手回复）
     */
    protected ?array $fullMessages = null;

    /**
     * 当前响应的完整输出内容（流式时累计）
     */
    protected string $accumulatedOutput = '';

    /**
     * 解析请求时初始化状态
     */
    public function parseRequest(array $rawRequest): ProtocolRequest
    {
        $responsesRequest = ResponsesRequest::fromArrayValidated($rawRequest);

        // 检索历史消息
        $historyMessages = null;
        if ($responsesRequest->previousResponseId) {
            $historyMessages = $this->retrieveHistory($responsesRequest->previousResponseId);
        }

        // 转换为 Chat Completions 格式
        $chatRequest = $responsesRequest->toChatCompletions($historyMessages);

        // ⭐ 保存完整消息历史（用于后续存储）
        $this->fullMessages = $chatRequest->messages;
        $this->accumulatedOutput = '';

        return $chatRequest;
    }
}
```

**步骤 2**: 在流式块中累计内容

```php
/**
 * 从标准格式构建 Responses 流式块
 */
public function buildStreamChunk(StreamChunk $chunk): string
{
    // ⭐ 累计输出内容
    if ($chunk->contentDelta !== null) {
        $this->accumulatedOutput .= $chunk->contentDelta;
    } elseif ($chunk->delta !== '') {
        $this->accumulatedOutput .= $chunk->delta;
    }

    // 构建响应块...
    $result = [
        'id' => $chunk->id ?: 'resp_'.uniqid(),
        'object' => 'response.chunk',
        'created' => time(),
        'model' => $chunk->model,
    ];

    if ($chunk->contentDelta !== null || $chunk->delta !== '') {
        $result['output'] = $chunk->contentDelta ?? $chunk->delta;
    }

    // 完成时存储状态
    if ($chunk->finishReason !== null) {
        $result['stop_reason'] = $this->mapFinishReason($chunk->finishReason->value);

        // ⭐ 流式结束时存储会话（在实际场景中应该在服务端完成）
        // 注意：这里只是标记，实际存储需要在 ProxyServer 流结束后调用
    }

    return 'data: '.$this->safeJsonEncode($result)."\n\n";
}
```

**步骤 3**: 添加流式完成后的状态存储方法

```php
/**
 * 流式响应完成后存储会话状态
 *
 * @param string $responseId 响应 ID
 * @param string $model 模型名称
 * @param array $usage 使用情况
 */
public function storeStreamSession(
    string $responseId,
    string $model,
    ?array $usage = null
): void {
    if ($this->fullMessages === null) {
        return;
    }

    // 添加助手回复到消息历史
    $fullMessages = $this->fullMessages;
    $fullMessages[] = [
        'role' => 'assistant',
        'content' => $this->accumulatedOutput,
    ];

    // 存储会话
    $manager = app(ResponseStateManager::class);
    $apiKeyId = $this->getCurrentApiKeyId();

    $manager->store(
        $responseId,
        $fullMessages,
        $apiKeyId,
        $model,
        $usage['total_tokens'] ?? 0
    );

    // 清理状态
    $this->fullMessages = null;
    $this->accumulatedOutput = '';
}
```

---

### 2.3 previous_response_id 状态共享安全

**问题描述**:
- 仅使用 `api_key_id` 隔离可能不够
- 需要考虑 Response ID 的唯一性生成

**处理方案**:

**步骤 1**: 确保 Response ID 全局唯一

```php
// laravel/app/Services/Response/ResponseStateManager.php

/**
 * 生成全局唯一的 Response ID
 */
public static function generateResponseId(): string
{
    return 'resp_'.bin2hex(random_bytes(12)); // 24位十六进制字符
}

/**
 * 存储会话状态
 */
public function store(
    ?string $responseId, // 允许 null，自动生成
    array $messages,
    ?int $apiKeyId = null,
    string $model = '',
    int $totalTokens = 0
): ResponseSession {

    // ⭐ 自动生成 Response ID
    if (empty($responseId)) {
        $responseId = self::generateResponseId();
    }

    // ... rest of the method
}
```

**步骤 2**: 强化查询条件

```php
/**
 * 检索会话历史
 */
public function retrieve(string $responseId, ?int $apiKeyId = null): ?array
{
    $query = ResponseSession::where('response_id', $responseId)
        ->where('expires_at', '>', now());

    // ⭐ 严格隔离：必须提供 api_key_id
    if ($apiKeyId !== null) {
        $query->where('api_key_id', $apiKeyId);
    } else {
        // 没有 api_key_id 上下文，拒绝访问
        Log::warning('Response session retrieve attempted without API key context', [
            'response_id' => $responseId,
        ]);
        return null;
    }

    $session = $query->first();

    if (!$session) {
        return null;
    }

    // 更新最后访问时间
    $session->touch();

    return $session->messages;
}
```

---

## 三、P2 级问题（建议改进）

### 3.1 input 字段完整类型支持

**问题描述**:
- Responses API 的 `input` 支持多种格式：
  - `string`: 纯文本输入
  - `array`: 消息数组 `[{"role": "user", "content": "..."}]`
  - 内容块数组（图片、文件等）

**处理方案**:

```php
// laravel/app/Services/Protocol/Driver/Responses/ResponsesRequest.php

/**
 * input 转换为消息数组
 */
private function inputToMessages(): array
{
    // 字符串类型
    if (is_string($this->input)) {
        return [['role' => 'user', 'content' => $this->input]];
    }

    // 数组类型
    if (is_array($this->input)) {
        // 检查是否是内容块格式
        if ($this->isContentBlockArray($this->input)) {
            return $this->convertContentBlocksToMessages($this->input);
        }

        // 已经是消息数组格式
        return $this->input;
    }

    return [];
}

/**
 * 检查是否是内容块数组
 */
private function isContentBlockArray(array $input): bool
{
    if (empty($input)) {
        return false;
    }

    // 内容块格式包含 type 字段
    return isset($input[0]['type']) && in_array($input[0]['type'], ['text', 'image', 'file']);
}

/**
 * 转换内容块为消息格式
 */
private function convertContentBlocksToMessages(array $contentBlocks): array
{
    $content = [];

    foreach ($contentBlocks as $block) {
        switch ($block['type']) {
            case 'text':
                $content[] = [
                    'type' => 'text',
                    'text' => $block['text'] ?? '',
                ];
                break;

            case 'image':
                $content[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $block['source'] ?? $block['url'] ?? '',
                        'detail' => $block['detail'] ?? 'auto',
                    ],
                ];
                break;

            case 'file':
                // 文件类型可能需要特殊处理
                $content[] = [
                    'type' => 'text',
                    'text' => '[File: '.($block['filename'] ?? 'unknown').']',
                ];
                break;
        }
    }

    return [['role' => 'user', 'content' => $content]];
}
```

---

### 3.2 usage 字段修正

**问题描述**:
- OpenAI Responses API 返回 `input_tokens` 和 `output_tokens`，没有 `total_tokens`

**处理方案**:

```php
// laravel/app/Services/Protocol/Driver/Responses/ResponsesResponse.php

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

    // ... 其他字段转换

    // usage 转换（修正字段名）
    if ($chat->usage !== null) {
        $response->usage = [
            'input_tokens' => $chat->usage['prompt_tokens'] ?? 0,
            'output_tokens' => $chat->usage['completion_tokens'] ?? 0,
            // ⭐ 注意：Responses API 没有 total_tokens，需要前端自行计算
        ];
    }

    return $response;
}

/**
 * 获取总 Token 数（辅助方法）
 */
public function getTotalTokens(): int
{
    if ($this->usage === null) {
        return 0;
    }

    return ($this->usage['input_tokens'] ?? 0) + ($this->usage['output_tokens'] ?? 0);
}
```

---

### 3.3 instructions 字段支持

**问题描述**:
- Responses API 支持 `instructions` 字段（类似 system message）
- 需要转换为 Chat Completions 的 `messages[0]`（role: system）

**处理方案**:

```php
// laravel/app/Services/Protocol/Driver/Responses/ResponsesRequest.php

/**
 * 系统指令
 */
public ?string $instructions = null;

/**
 * 从数组创建
 */
public static function fromArray(array $data): self
{
    $request = new self;
    $request->model = $data['model'] ?? '';
    $request->input = $data['input'] ?? '';
    $request->previousResponseId = $data['previous_response_id'] ?? null;
    $request->instructions = $data['instructions'] ?? null; // ⭐ 新增
    // ... 其他字段

    return $request;
}

/**
 * ⭐ 转换为 Chat Completions 格式
 */
public function toChatCompletions(?array $historyMessages = null): ChatCompletionRequest
{
    $chatRequest = new ChatCompletionRequest();
    $chatRequest->model = $this->model;
    $chatRequest->maxTokens = $this->maxTokens;
    // ... 其他字段

    // 构建消息数组
    $messages = [];

    // ⭐ 添加 instructions 作为 system message
    if ($this->instructions !== null && $this->instructions !== '') {
        $messages[] = ['role' => 'system', 'content' => $this->instructions];
    }

    // 添加历史消息
    if ($historyMessages !== null) {
        $messages = array_merge($messages, $historyMessages);
    }

    // 添加当前 input
    $newMessages = $this->inputToMessages();
    $messages = array_merge($messages, $newMessages);

    $chatRequest->messages = $messages;

    return $chatRequest;
}
```

---

### 3.4 数据库索引优化

**问题描述**:
- `response_id` 已有 `unique` 索引，不需要额外的 `index`

**处理方案**:

```php
// ❌ 错误的索引定义
$table->string('response_id', 255)->unique()->comment('...');
$table->index('response_id'); // 冗余

// ✅ 正确的索引定义
$table->string('response_id', 255)->unique()->comment('...');
// 不需要额外的 index

// ✅ 合理的索引组合
$table->index(['api_key_id', 'expires_at']); // 用于清理和查询
$table->index('expires_at'); // 用于过期清理
```

---

## 四、架构调整建议

### 4.1 驱动选择逻辑

**当前 ProxyServer 调用方式**:
```php
$result = $this->proxyServer->proxy($request, $protocol);
```

**问题**: 如何根据路由自动选择 `openai_responses` 驱动？

**处理方案**:

**方案 A**: 在 ProxyController 中根据路由确定协议

```php
public function responses(Request $request): JsonResponse|StreamedResponse
{
    // 标记请求为 Responses 协议
    $request->attributes->set('protocol', 'openai_responses');

    return $this->handleRequest($request, 'openai_responses');
}
```

**方案 B**: 在 ProxyServer 中自动检测

```php
// laravel/app/Services/Router/ProxyServer.php

public function proxy(Request $request, string $protocol): array|Generator
{
    // ⭐ 自动检测 Responses API
    if ($request->is('*/responses') || $protocol === 'openai_responses') {
        $protocol = 'openai_responses';
    }

    // ... rest of the method
}
```

---

### 4.2 状态清理命令实现

**完整的 Artisan 命令实现**:

```php
// laravel/app/Console/Commands/CleanupResponseSessions.php

namespace App\Console\Commands;

use App\Services\Response\ResponseStateManager;
use Illuminate\Console\Command;

class CleanupResponseSessions extends Command
{
    protected $signature = 'cdapi:cleanup-response-sessions
                            {--hours=24 : 清理超过指定小时数的会话}';

    protected $description = '清理过期的 Responses API 会话';

    public function handle(ResponseStateManager $manager): int
    {
        $hours = $this->option('hours');

        $this->info("开始清理超过 {$hours} 小时的过期会话...");

        $count = $manager->cleanupExpired();

        $this->info("成功清理 {$count} 个过期会话");

        return self::SUCCESS;
    }
}
```

**注册定时任务**:

```php
// laravel/routes/console.php

use Illuminate\Support\Facades\Schedule;

// 每小时清理过期会话
Schedule::command('cdapi:cleanup-response-sessions')->hourly();
```

---

## 五、修正后的完整 DTO 实现

### 5.1 ResponsesRequest.php（修正版）

```php
<?php

namespace App\Services\Protocol\Driver\Responses;

use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionRequest;
use App\Services\Shared\DTO\Request as SharedRequest;
use App\Services\Shared\DTO\Message;

/**
 * OpenAI Responses API 请求 DTO
 */
class ResponsesRequest implements ProtocolRequest
{
    public string $model = '';
    public string|array $input = '';
    public ?string $previousResponseId = null;
    public ?string $instructions = null; // ⭐ 新增
    public ?int $maxTokens = null;
    public ?float $temperature = null;
    public ?float $topP = null;
    public ?bool $stream = false;
    public ?array $tools = null;
    public $toolChoice = null;
    public ?array $metadata = null; // ⭐ 新增

    public static function fromArray(array $data): self
    {
        $request = new self;
        $request->model = $data['model'] ?? '';
        $request->input = $data['input'] ?? '';
        $request->previousResponseId = $data['previous_response_id'] ?? null;
        $request->instructions = $data['instructions'] ?? null;
        $request->maxTokens = $data['max_tokens'] ?? null;
        $request->temperature = $data['temperature'] ?? null;
        $request->topP = $data['top_p'] ?? null;
        $request->stream = $data['stream'] ?? false;
        $request->tools = $data['tools'] ?? null;
        $request->toolChoice = $data['tool_choice'] ?? null;
        $request->metadata = $data['metadata'] ?? null;

        return $request;
    }

    public static function fromArrayValidated(array $data): self
    {
        $request = self::fromArray($data);

        if (empty($request->model)) {
            throw new \InvalidArgumentException('model is required');
        }

        if (empty($request->input)) {
            throw new \InvalidArgumentException('input is required');
        }

        return $request;
    }

    public function toArray(): array
    {
        $result = [
            'model' => $this->model,
            'input' => $this->input,
        ];

        if ($this->previousResponseId !== null) {
            $result['previous_response_id'] = $this->previousResponseId;
        }

        if ($this->instructions !== null) {
            $result['instructions'] = $this->instructions;
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

        if ($this->metadata !== null) {
            $result['metadata'] = $this->metadata;
        }

        return $result;
    }

    /**
     * ⭐ 转换为 Chat Completions 格式
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

        // 构建消息数组
        $messages = [];

        // 添加 instructions 作为 system message
        if ($this->instructions !== null && $this->instructions !== '') {
            $messages[] = ['role' => 'system', 'content' => $this->instructions];
        }

        // 添加历史消息
        if ($historyMessages !== null) {
            $messages = array_merge($messages, $historyMessages);
        }

        // 添加当前 input
        $newMessages = $this->inputToMessages();
        $messages = array_merge($messages, $newMessages);

        $chatRequest->messages = $messages;

        return $chatRequest;
    }

    /**
     * input 转换为消息数组
     */
    private function inputToMessages(): array
    {
        if (is_string($this->input)) {
            return [['role' => 'user', 'content' => $this->input]];
        }

        if (is_array($this->input)) {
            if ($this->isContentBlockArray($this->input)) {
                return $this->convertContentBlocksToMessages($this->input);
            }
            return $this->input;
        }

        return [];
    }

    /**
     * 检查是否是内容块数组
     */
    private function isContentBlockArray(array $input): bool
    {
        if (empty($input)) {
            return false;
        }
        return isset($input[0]['type']) && in_array($input[0]['type'], ['text', 'image', 'file']);
    }

    /**
     * 转换内容块为消息格式
     */
    private function convertContentBlocksToMessages(array $contentBlocks): array
    {
        $content = [];

        foreach ($contentBlocks as $block) {
            switch ($block['type']) {
                case 'text':
                    $content[] = [
                        'type' => 'text',
                        'text' => $block['text'] ?? '',
                    ];
                    break;

                case 'image':
                    $content[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $block['source'] ?? $block['url'] ?? '',
                            'detail' => $block['detail'] ?? 'auto',
                        ],
                    ];
                    break;

                case 'file':
                    $content[] = [
                        'type' => 'text',
                        'text' => '[File: '.($block['filename'] ?? 'unknown').']',
                    ];
                    break;
            }
        }

        return [['role' => 'user', 'content' => $content]];
    }
}
```

### 5.2 ResponsesResponse.php（修正版）

```php
<?php

namespace App\Services\Protocol\Driver\Responses;

use App\Services\Protocol\Contracts\ProtocolResponse;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionResponse;
use App\Services\Shared\DTO\Response as SharedResponse;

/**
 * OpenAI Responses API 响应 DTO
 */
class ResponsesResponse implements ProtocolResponse
{
    public string $id = '';
    public string $object = 'response';
    public int $created = 0;
    public string $model = '';
    public string|array $output = '';
    public ?array $toolCalls = null;
    public ?string $stopReason = null;
    public ?array $usage = null;
    public ?string $systemFingerprint = null; // ⭐ 新增

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
        $response->systemFingerprint = $data['system_fingerprint'] ?? null;

        return $response;
    }

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

        if ($this->systemFingerprint !== null) {
            $result['system_fingerprint'] = $this->systemFingerprint;
        }

        return $result;
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
        $response->systemFingerprint = $chat->systemFingerprint ?? null;

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

            // 停止原因映射
            $finishReason = $choice['finish_reason'] ?? null;
            if ($finishReason !== null) {
                $response->stopReason = self::mapFinishReason($finishReason);
            }
        }

        // usage 转换（修正字段名）
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
            'content_filter' => 'content_filter',
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

    /**
     * 获取总 Token 数
     */
    public function getTotalTokens(): int
    {
        if ($this->usage === null) {
            return 0;
        }
        return ($this->usage['input_tokens'] ?? 0) + ($this->usage['output_tokens'] ?? 0);
    }
}
```

---

## 六、实施检查清单

### Phase 1: 基础架构（必须先完成）

- [ ] 创建 `Responses/` 目录
- [ ] 创建 `ResponsesRequest.php`（修正类名和命名空间）
- [ ] 创建 `ResponsesResponse.php`（修正类名和命名空间）
- [ ] 更新 `ProxyController`，添加 `responses()` 方法
- [ ] 更新 `routes/api.php`，添加 `/openai/v1/responses` 路由
- [ ] 在 `AppServiceProvider` 中注册 `openai_responses` 驱动

### Phase 2: 状态管理

- [ ] 创建数据库迁移文件
- [ ] 创建 `ResponseSession` Model
- [ ] 创建 `ResponseStateManager` Service
- [ ] 实现 `generateResponseId()` 方法
- [ ] 实现 API Key 隔离逻辑
- [ ] 创建 `cdapi:cleanup-response-sessions` 命令

### Phase 3: 驱动实现

- [ ] 创建 `ResponsesDriver.php`
- [ ] 实现 `parseRequest()` 方法
- [ ] 实现 `buildResponse()` 方法
- [ ] 实现 `buildStreamChunk()` 方法
- [ ] 实现流式完成后的状态存储
- [ ] 实现错误响应格式

### Phase 4: 测试验证

- [ ] 编写 `ResponsesRequestTest` 单元测试
- [ ] 编写 `ResponsesResponseTest` 单元测试
- [ ] 编写 `ResponseStateManagerTest` 单元测试
- [ ] 编写集成测试 `ResponsesApiTest`
- [ ] 手动测试基础请求
- [ ] 手动测试状态管理（previous_response_id）
- [ ] 手动测试流式响应

---

**文档作者**: Claude Code
**审查完成时间**: 2026-04-05 00:48
