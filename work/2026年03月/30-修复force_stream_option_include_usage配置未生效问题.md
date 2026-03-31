# 修复 force_stream_option_include_usage 配置未生效问题

## 问题描述

用户反馈渠道请求日志中缺少 token usage 信息，请求体中没有 `stream_options` 参数。

虽然之前已经：
1. 添加了配置字段 `force_stream_option_include_usage`
2. 更新了后台表单
3. 更新了数据库配置
4. 在 SharedRequest DTO 中添加了 `streamOptions` 字段
5. 在 ChatCompletionRequest 中处理了 `stream_options` 参数

但是实际请求中依然没有 `stream_options` 参数。

## 根本原因

问题出现在两个地方：

### 1. SharedRequest DTO 缺少 streamOptions 字段处理

**问题**: 协议转换时，SharedRequest 作为中间层，但没有 `streamOptions` 字段，导致 OpenAI → Anthropic → OpenAI 的协议转换过程中丢失了该参数。

**影响**: 即使 ChatCompletionRequest 有 `stream_options` 字段，转换时也会丢失。

### 2. ProviderManager 未传递渠道配置

**问题**: `ProviderManager::getForChannel()` 方法创建 Provider 实例时，没有合并渠道的 `config` 字段（包含 `force_stream_option_include_usage` 等高级配置）。

**影响**: Provider 无法读取到渠道的高级配置，导致 `force_stream_option_include_usage` 配置不生效。

### 3. ProxyServer 记录请求体时未使用 Provider 的 buildRequestBody

**问题**: `ProxyServer::createChannelRequestLog()` 方法直接调用 `$protocolRequest->toArray()` 获取请求体，而不是使用 `$provider->buildRequestBody()`。

**影响**: 即使 Provider 添加了 `stream_options`，记录到数据库的请求体也没有该字段。

## 修复方案

### 1. SharedRequest 添加 streamOptions 字段

**文件**: [laravel/app/Services/Shared/DTO/Request.php](laravel/app/Services/Shared/DTO/Request.php)

```php
/**
 * 流式选项（如 include_usage）
 */
public ?array $streamOptions = null;
```

在 `fromArray()` 方法中处理：
```php
$request->streamOptions = $data['stream_options'] ?? $data['streamOptions'] ?? null;
```

### 2. ChatCompletionRequest 处理 streamOptions

**文件**: [laravel/app/Services/Protocol/Driver/OpenAI/ChatCompletionRequest.php](laravel/app/Services/Protocol/Driver/OpenAI/ChatCompletionRequest.php)

**toSharedDTO()** 方法添加：
```php
$dto->streamOptions = $this->stream_options;
```

**fromSharedDTO()** 方法添加：
```php
stream_options: $dto->streamOptions,
```

**fromArray()** 方法添加参数传递（第 142 行）

### 3. ProviderManager 传递渠道配置

**文件**: [laravel/app/Services/Provider/ProviderManager.php](laravel/app/Services/Provider/ProviderManager.php:102-114)

```php
$config = [
    'base_url' => $channel->base_url,
    'api_key' => $channel->api_key,
    'name' => $providerName,
    'forward_headers' => $channel->getForwardHeaderNames(),
    'client_headers' => $clientHeaders,
];

// 合并渠道的高级配置
if (! empty($channel->config) && is_array($channel->config)) {
    $config = array_merge($config, $channel->config);
}
```

### 4. ProxyServer 使用 Provider 的 buildRequestBody

**文件**: [laravel/app/Services/Router/ProxyServer.php](laravel/app/Services/Router/ProxyServer.php:391-394)

```php
// 使用 Provider 的 buildRequestBody 方法获取最终请求体（包含渠道配置）
$requestBody = $provider->buildRequestBody($protocolRequest);
```

## 验证结果

重放最新请求后，数据库中的渠道请求日志：

```sql
SELECT id, JSON_KEYS(request_body), JSON_EXTRACT(request_body, '$.stream_options')
FROM channel_request_logs
WHERE channel_id = 7 ORDER BY id DESC LIMIT 1
```

**结果**:
```json
{
  "id": 2674,
  "body_keys": ["model", "stream", "messages", "max_tokens", "temperature", "stream_options"],
  "stream_options": "{\"include_usage\": true}"
}
```

✅ 请求体成功包含 `stream_options` 参数

## 修改文件清单

- [laravel/app/Services/Shared/DTO/Request.php](laravel/app/Services/Shared/DTO/Request.php) - 添加 streamOptions 字段
- [laravel/app/Services/Protocol/Driver/OpenAI/ChatCompletionRequest.php](laravel/app/Services/Protocol/Driver/OpenAI/ChatCompletionRequest.php) - 处理 streamOptions 转换
- [laravel/app/Services/Provider/ProviderManager.php](laravel/app/Services/Provider/ProviderManager.php) - 传递渠道配置
- [laravel/app/Services/Router/ProxyServer.php](laravel/app/Services/Router/ProxyServer.php) - 使用 Provider 的 buildRequestBody
- [laravel/app/Services/Provider/Driver/OpenAIProvider.php](laravel/app/Services/Provider/Driver/OpenAIProvider.php) - 添加 force_stream_option_include_usage 支持
- [laravel/app/Services/Provider/Driver/OpenAICompatibleProvider.php](laravel/app/Services/Provider/Driver/OpenAICompatibleProvider.php) - 同上

## 后续工作

1. 等待下一次流式请求，验证 usage 字段是否正确记录 token 统计
2. 检查 Anthropic 格式的流式响应 usage 统计是否正常
3. 考虑在审计日志中正确展示 usage 信息

## 技术要点

- **协议转换中间层**: SharedDTO 作为协议无关的中间格式，所有字段都必须完整传递
- **配置传递链**: Channel → ProviderManager → Provider → buildRequestBody
- **请求体构建**: 应使用 Provider 的 buildRequestBody 方法，而非直接调用 DTO 的 toArray
- **配置作用域**: 渠道配置（config 字段）用于存储渠道级别的特性开关