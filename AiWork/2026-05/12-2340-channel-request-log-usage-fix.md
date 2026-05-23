# Channel Request Log Usage 字段解析修复

## 问题背景

用户反馈：channel-request-logs/105 日志中，usage 字段显示异常。

数据库中的 usage：
```json
{
  "total_tokens": 0,
  "prompt_tokens": 0,
  "cache_read_tokens": 0,
  "completion_tokens": 0,
  "cache_write_tokens": 0
}
```

但实际响应体中的 usage：
```json
{
  "input_tokens": 39285,
  "output_tokens": 239,
  "cache_read_input_tokens": 64
}
```

## 问题分析

### 根本原因

**协议格式不匹配**：
- 渠道 provider 标记为 `openai`
- 但上游实际返回的是 **Anthropic 格式的 usage**
- OpenAI 协议解析器期望字段名：`prompt_tokens`、`completion_tokens`、`total_tokens`
- 实际响应使用的字段名：`input_tokens`、`output_tokens`、`cache_read_input_tokens`

### 解析失败位置

在 `OpenAI\Usage::fromArray()` 方法中：

```php
public static function fromArray(array $data): static
{
    return new self(
        prompt_tokens: $data['prompt_tokens'] ?? 0,  // 读取失败，默认为 0
        completion_tokens: $data['completion_tokens'] ?? 0,  // 读取失败，默认为 0
        total_tokens: $data['total_tokens'] ?? 0,  // 读取失败，默认为 0
        ...
    );
}
```

由于数组中没有 `prompt_tokens` 等字段，所以全部解析为 0。

### 渠道配置

```
ID: 5
Name: opencode-go
Provider: openai
Base URL: https://opencode.ai/zen/go/v1
```

这个渠道虽然标记为 openai provider，但上游实际使用的是 Anthropic 格式。

## 解决方案

### 修改内容

**增强 `OpenAI\Usage::fromArray()` 方法**，让它兼容两种格式：

```php
/**
 * 从数组创建
 *
 * 兼容 OpenAI 和 Anthropic 两种格式：
 * - OpenAI: prompt_tokens, completion_tokens, total_tokens
 * - Anthropic: input_tokens, output_tokens, cache_read_input_tokens, cache_creation_input_tokens
 */
public static function fromArray(array $data): static
{
    // 兼容 Anthropic 格式：将 input_tokens 映射到 prompt_tokens
    $promptTokens = $data['prompt_tokens'] ?? $data['input_tokens'] ?? 0;
    $completionTokens = $data['completion_tokens'] ?? $data['output_tokens'] ?? 0;
    $totalTokens = $data['total_tokens'] ?? ($promptTokens + $completionTokens);

    // 兼容 Anthropic 缓存字段
    $promptTokensDetails = $data['prompt_tokens_details'] ?? null;
    $completionTokensDetails = $data['completion_tokens_details'] ?? null;

    // 如果有 Anthropic 的缓存字段，转换为 OpenAI 格式
    if ($promptTokensDetails === null && isset($data['cache_read_input_tokens'])) {
        $promptTokensDetails = [
            'cached_tokens' => $data['cache_read_input_tokens'],
        ];
    }

    return new self(
        prompt_tokens: $promptTokens,
        completion_tokens: $completionTokens,
        total_tokens: $totalTokens,
        prompt_tokens_details: $promptTokensDetails,
        completion_tokens_details: $completionTokensDetails,
    );
}
```

### 字段映射关系

| Anthropic 格式 | OpenAI 格式 | Shared DTO |
|---------------|------------|------------|
| `input_tokens` | `prompt_tokens` | `inputTokens` |
| `output_tokens` | `completion_tokens` | `outputTokens` |
| `cache_read_input_tokens` | `prompt_tokens_details['cached_tokens']` | `cacheReadInputTokens` |
| `cache_creation_input_tokens` | - | `cacheCreationInputTokens` |

## 测试验证

### 单元测试

测试 Usage 类直接解析 Anthropic 格式：

```bash
php artisan tinker --execute="
$usageArray = [
    'input_tokens' => 39285,
    'output_tokens' => 239,
    'cache_read_input_tokens' => 64
];
$usage = App\Services\Protocol\Driver\OpenAI\Usage::fromArray($usageArray);
echo 'prompt_tokens: ' . $usage->prompt_tokens . PHP_EOL;
echo 'completion_tokens: ' . $usage->completion_tokens . PHP_EOL;
echo 'total_tokens: ' . $usage->total_tokens . PHP_EOL;
"
```

**结果**：✅ 所有字段正确解析

```
prompt_tokens: 39285
completion_tokens: 239
total_tokens: 39524
prompt_tokens_details: [cached_tokens] => 64
```

### 集成测试

测试完整响应解析流程：

```bash
php artisan tinker --execute="
$responseArray = [
    'id' => 'test-123',
    'object' => 'chat.completion',
    'created' => time(),
    'model' => 'glm-5.1',
    'choices' => [...],
    'usage' => [
        'input_tokens' => 39285,
        'output_tokens' => 239,
        'cache_read_input_tokens' => 64
    ]
];
$response = App\Services\Protocol\Driver\OpenAI\ChatCompletionResponse::fromArray($responseArray);
$usage = $response->getUsage();
$sharedDTO = $response->toSharedDTO();
"
```

**结果**：✅ ChatCompletionResponse 和 Shared DTO 都正确解析

```
ChatCompletionResponse 解析的 usage:
prompt_tokens: 39285
completion_tokens: 239
total_tokens: 39524
cached_tokens: 64

转换到 Shared DTO:
inputTokens: 39285
outputTokens: 239
cacheReadInputTokens: 64
```

## 影响范围

### 修改文件

- `laravel/app/Services/Protocol/Driver/OpenAI/Usage.php`

### 影响场景

1. **混合格式响应**：当 OpenAI provider 渠道返回 Anthropic 格式的响应时，usage 字段能正确解析
2. **向后兼容**：原有的 OpenAI 格式响应依然正常工作
3. **数据库保存**：`channel_request_logs.usage` 字段将正确保存 token 使用量数据

## 附加说明

### 为什么会出现混合格式？

某些第三方服务（如 opencode.ai）虽然声称兼容 OpenAI API，但实际上返回的是 Anthropic 格式的响应结构。这种情况在 AI 代理网关中比较常见。

### 其他可能的解决方案

1. **修改渠道 provider**：将渠道改为 `anthropic` provider（可能导致其他问题）
2. **保存原始 usage**：直接保存响应体中的原始 usage，不依赖解析（失去统一性）
3. **当前方案**：增强解析器兼容性（最灵活，向后兼容）

### 后续建议

可以考虑为所有协议的 Usage 解析器添加类似的兼容逻辑，以应对更多混合格式的情况。