# 修复流式响应token统计缺失问题

## 时间
2026-03-29 05:26

## 问题描述

用户发现审计日志中所有token统计数据都为0：
```
prompt_tokens=0, completion_tokens=0, total_tokens=0
```

日志显示上游返回的流式数据中`"usage":null`，导致无法统计token使用情况。

## 根本原因

### OpenAI API规范

根据OpenAI API文档，要在流式响应中获取usage统计，需要在请求中添加：

```json
{
  "stream": true,
  "stream_options": {
    "include_usage": true
  }
}
```

### 问题表现

1. **ChatCompletionRequest缺少参数**：我们的实现中没有`stream_options`字段
2. **请求未携带参数**：转发到上游的请求缺少`stream_options.include_usage=true`
3. **上游行为差异**：
   - 某些OpenAI兼容API（如阿里云GLM）即使添加参数也可能不返回usage
   - 某些API（如x-aio）会正确返回usage

## 修复方案

### 1. 添加stream_options参数支持

**修改文件：** [laravel/app/Services/Protocol/Driver/OpenAI/ChatCompletionRequest.php](laravel/app/Services/Protocol/Driver/OpenAI/ChatCompletionRequest.php)

添加`stream_options`参数：

```php
public function __construct(
    // ... 其他参数
    public ?bool $stream = null,
    public ?array $stream_options = null,  // 新增
    public ?array $stop = null,
    // ... 其他参数
) {}
```

在`toArray()`方法中添加序列化：

```php
if ($this->stream_options !== null) {
    $result['stream_options'] = $this->stream_options;
}
```

### 2. 通过渠道配置控制

**修改文件：**
- [laravel/app/Services/Provider/Driver/OpenAIProvider.php](laravel/app/Services/Provider/Driver/OpenAIProvider.php)
- [laravel/app/Services/Provider/Driver/OpenAICompatibleProvider.php](laravel/app/Services/Provider/Driver/OpenAICompatibleProvider.php)

在`buildRequestBody`方法中添加逻辑：

```php
public function buildRequestBody(ProtocolRequest $request): array
{
    if ($request instanceof ChatCompletionRequest) {
        $body = $request->toArray();

        // 检查渠道配置：是否强制附加 stream_options.include_usage
        $forceStreamOptionIncludeUsage = $this->config['force_stream_option_include_usage'] ?? false;

        // 流式请求时，根据配置决定是否附加 stream_options
        if ($forceStreamOptionIncludeUsage && ($body['stream'] ?? false) === true) {
            if (! isset($body['stream_options'])) {
                $body['stream_options'] = ['include_usage' => true];
            } elseif (! isset($body['stream_options']['include_usage'])) {
                $body['stream_options']['include_usage'] = true;
            }
        }

        return $body;
    }

    throw new \InvalidArgumentException('OpenAIProvider requires ChatCompletionRequest');
}
```

### 3. 渠道配置方法

在渠道的`config`字段中添加配置：

```json
{
  "force_stream_option_include_usage": true
}
```

## 测试验证

### 测试前（无usage）

```
请求ID: 4260
prompt_tokens=0, completion_tokens=0, total_tokens=0
```

上游响应：
```json
{"choices":[...],"usage":null}
```

### 测试后（有usage）

```
请求ID: 4262
prompt_tokens=10202, completion_tokens=119, total_tokens=10321

请求ID: 4263
prompt_tokens=10600, completion_tokens=83, total_tokens=10683
```

上游响应：
```json
{
  "choices":[...],
  "usage":{
    "prompt_tokens":10202,
    "completion_tokens":119,
    "total_tokens":10321
  }
}
```

## 协议差异说明

### OpenAI格式
- **需要**：`stream_options.include_usage=true` 参数
- **返回位置**：最后一个chunk的`usage`字段
- **兼容性**：OpenAI官方API和大多数兼容API支持

### Anthropic/Claude格式
- **不需要**：额外参数，自动返回
- **返回位置**：
  - `message_start`事件：`message.usage.input_tokens`
  - `message_delta`事件：`usage.output_tokens`
- **兼容性**：Anthropic官方API自动包含

## 更新记录

### 2026-03-29 更新

**配置名称优化**：将 `force_stream_options` 重命名为 `force_stream_option_include_usage`，更准确地描述配置含义。

**数据库更新**：已为所有 OpenAI 渠道自动添加配置：
- 渠道 ID 1: 硅基流动-Openai
- 渠道 ID 3: x-aio-openai
- 渠道 ID 4: Me
- 渠道 ID 7: 阿里coding-Openai
- 渠道 ID 9: Test Channel

所有渠道配置中均已添加 `"force_stream_option_include_usage": true`。

## 影响范围

**修改文件：**
- [laravel/app/Services/Protocol/Driver/OpenAI/ChatCompletionRequest.php](laravel/app/Services/Protocol/Driver/OpenAI/ChatCompletionRequest.php)
- [laravel/app/Services/Provider/Driver/OpenAIProvider.php](laravel/app/Services/Provider/Driver/OpenAIProvider.php)
- [laravel/app/Services/Provider/Driver/OpenAICompatibleProvider.php](laravel/app/Services/Provider/Driver/OpenAICompatibleProvider.php)

**向后兼容：**
- ✅ 完全兼容，不强制所有渠道启用
- ✅ 通过渠道配置灵活控制
- ✅ 不影响现有不返回usage的渠道

## 注意事项

### 渠道配置建议

1. **OpenAI官方API**：建议启用`force_stream_option_include_usage=true`
2. **Azure OpenAI**：建议启用`force_stream_option_include_usage=true`
3. **第三方兼容API**：
   - 支持该参数的：启用
   - 不支持的：不启用（避免请求失败）
4. **Anthropic API**：无需配置，自动返回usage

### 已知不返回usage的API

- 阿里云通义千问（GLM）部分接口
- 某些本地部署的OpenAI兼容服务

## 后续建议

1. 在渠道管理界面添加配置选项
2. 添加渠道能力检测功能，自动判断是否支持`stream_options`
3. 对于不支持usage的API，考虑使用估算方法（如按字符数估算token）
4. 添加监控告警，当token统计持续为0时通知管理员

## 相关文档

- [OpenAI API - Streaming](https://platform.openai.com/docs/api-reference/streaming)
- [StreamChunk流式响应块](../docs/StreamChunk流式响应块.md)
- [修复tool_use事件流式响应中断问题](./29-修复tool_use事件流式响应中断问题.md)