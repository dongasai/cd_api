# 实现 Anthropic Models API Endpoint

**日期**: 2026-05-12 21:13
**类型**: 功能实现

## 背景

日志中发现请求访问 `/anthropic/v1/models` 返回 404 错误。Anthropic API 标准中有 models endpoint，需要在网关中实现。

## 实现内容

### 1. 添加 Anthropic Models Endpoint

**文件**: [laravel/app/Http/Controllers/Api/ProxyController.php](laravel/app/Http/Controllers/Api/ProxyController.php)

新增 `anthropicModels()` 方法，返回 Anthropic 格式的模型列表：

```php
/**
 * 可用模型（Anthropic格式）
 */
public function anthropicModels(Request $request): JsonResponse
{
    $apiKey = $request->attributes->get('api_key');

    // 使用 ModelService 获取可用模型列表（OpenAI格式）
    $openaiData = ModelService::getAvailableModels($apiKey);

    // 转换为 Anthropic 格式
    $data = array_map(function ($model) {
        return [
            'id' => $model['id'],
            'type' => 'model',
            'display_name' => $model['display_name'] ?? $model['id'],
            'created_at' => isset($model['created'])
                ? date('c', $model['created'])  // ISO 8601 格式
                : date('c'),
        ];
    }, $openaiData);

    $response = [
        'data' => $data,
        'has_more' => false,
    ];

    // 如果有数据，添加 first_id 和 last_id
    if (!empty($data)) {
        $response['first_id'] = $data[0]['id'];
        $response['last_id'] = $data[count($data) - 1]['id'];
    }

    return response()->json($response);
}
```

### 2. 添加路由

**文件**: [laravel/routes/api.php](laravel/routes/api.php)

```php
// Anthropic models endpoint
Route::get('/anthropic/models', [ProxyController::class, 'anthropicModels']);
Route::get('/anthropic/v1/models', [ProxyController::class, 'anthropicModels']);
```

### 3. 更新 ModelService

**文件**: [laravel/app/Services/ModelService.php](laravel/app/Services/ModelService.php)

在 OpenAI 格式的模型返回数据中添加 `display_name` 字段：

```php
$data = $modelLists->map(function ($modelList) {
    return [
        'id' => $modelList->model_name,
        'object' => 'model',
        'created' => $modelList->created_at?->timestamp ?? time(),
        'owned_by' => $modelList->provider ?? 'system',
        'display_name' => $modelList->display_name ?? $modelList->model_name,  // 新增
    ];
})->values()->toArray();
```

### 4. 响应格式

Anthropic Models API 响应格式示例：

```json
{
    "data": [
        {
            "id": "claude-sonnet-4-6",
            "type": "model",
            "display_name": "claude-sonnet-4-6",
            "created_at": "2026-05-12T20:54:44+08:00"
        }
    ],
    "first_id": "claude-sonnet-4-6",
    "has_more": false,
    "last_id": "glm-5"
}
```

关键特性：
- `type`: 固定为 "model"
- `display_name`: 从 model_lists 表获取，若无则使用 model_name
- `created_at`: ISO 8601 格式时间戳
- `has_more`: 固定为 false（当前不支持分页）
- `first_id` 和 `last_id`: 数据存在时添加

### 5. 路由验证

```bash
php artisan route:list --path=anthropic
```

输出：
```
POST       api/anthropic/messages
GET|HEAD   api/anthropic/models
POST       api/anthropic/v1/messages
GET|HEAD   api/anthropic/v1/models
```

## 测试

创建测试命令验证格式转换：

**文件**: [laravel/app/Console/Commands/TestAnthropicModels.php](laravel/app/Console/Commands/TestAnthropicModels.php)

运行测试：
```bash
php artisan test:anthropic-models --api-key=1
```

全局模型测试：
```bash
php artisan tinker --execute="
\$globalModels = \App\Services\ModelService::getAvailableModels(null);
echo '全局启用模型数量: ' . count(\$globalModels) . PHP_EOL;
// ... 格式转换验证
"
```

测试结果：
- 全局启用模型数量: 11
- 格式转换成功
- display_name 字段正确返回

## 技术要点

1. **格式转换**: OpenAI 格式 → Anthropic 格式
   - `object` → `type` (值为 "model")
   - `created` (Unix timestamp) → `created_at` (ISO 8601)
   - 新增 `display_name` 字段

2. **数据来源**: 复用 `ModelService::getAvailableModels()`
   - API Key 级别的模型权限过滤
   - 渠道级别的模型可用性
   - model_lists 表的 display_name 字段

3. **兼容性**: 同时支持 `/anthropic/models` 和 `/anthropic/v1/models` 两个路径

## 后续优化建议

1. 支持分页参数（如 `limit`），实现 `has_more` 动态判断
2. 支持单个模型查询 `/anthropic/v1/models/{model_id}`
3. 添加模型更多元数据（如 capabilities、context_length）

## 相关文件

- [laravel/app/Http/Controllers/Api/ProxyController.php](laravel/app/Http/Controllers/Api/ProxyController.php)
- [laravel/routes/api.php](laravel/routes/api.php)
- [laravel/app/Services/ModelService.php](laravel/app/Services/ModelService.php)
- [laravel/app/Models/ModelList.php](laravel/app/Models/ModelList.php)
- [laravel/app/Console/Commands/TestAnthropicModels.php](laravel/app/Console/Commands/TestAnthropicModels.php)
- [docs/a-model.json](docs/a-model.json) - 响应格式示例