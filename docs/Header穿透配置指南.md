# Header 穿透配置指南

## 概述

CdApi支持灵活的HTTP Header穿透机制，允许将客户端请求中的特定Header转发到上游API。这在以下场景非常有用：

- **User-Agent穿透**：让上游API识别真实客户端类型
- **IP地址穿透**：传递真实客户端IP（x-real-ip, x-forwarded-for）
- **自定义标识**：转发追踪ID或认证标识

## 配置位置

**后台管理路径**：渠道管理 → 编辑渠道 → 高级配置 → 转发请求头

![配置界面](配置界面示意)

### 数据库字段

Channel模型的 `forward_headers` 字段（array类型）：

```php
protected $casts = [
    'forward_headers' => 'array',
];
```

## 配置方法

### 1. 精确匹配

配置单个header名称进行精确匹配：

```json
["user-agent"]
```

只转发名为 `user-agent` 的header。

### 2. 通配符匹配

支持三种通配符模式：

#### 前缀匹配

```json
["user-agent*"]
```

匹配所有以 `user-agent` 开头的header：
- `user-agent`
- `user-agent-custom`
- `user-agent-version` 等

#### 后缀匹配

```json
["*agent"]
```

匹配所有以 `agent` 结尾的header：
- `user-agent`
- `custom-agent`
- `browser-agent` 等

#### 完整通配（慎用）

```json
["*"]
```

转发所有header（可能包含敏感信息，需谨慎使用）。

### 3. 多header配置

可同时配置多个header：

```json
[
  "user-agent",
  "x-real-ip",
  "x-forwarded-for",
  "x-request-id"
]
```

## 实现原理

### 请求处理流程

```
客户端请求
  ↓ (携带原始headers)
ProxyServer
  ↓ ($request->headers->all())
ProviderManager::getForChannel($channel, $clientHeaders)
  ↓ (传递配置: forward_headers + client_headers)
AbstractProvider::buildForwardedHeaders()
  ↓ (模式匹配 + 黑名单过滤)
Provider Driver → 上游API
```

### 核心代码位置

#### 1. Header传递入口

`app/Services/Router/ProxyServer.php:280`

```php
$provider = $this->providerManager->getForChannel(
    $this->selectedChannel,
    $request->headers->all()  // 传递客户端所有headers
);
```

#### 2. 配置组装

`app/Services/Provider/ProviderManager.php:106-107`

```php
$config = [
    'forward_headers' => $channel->getForwardHeaderNames(),  // 从渠道读取配置
    'client_headers'  => $clientHeaders,  // 客户端原始headers
];
```

#### 3. 匹配逻辑

`app/Services/Provider/Driver/AbstractProvider.php:160-191`

```php
protected function buildForwardedHeaders(): array
{
    $result = [];
    $clientHeadersFlat = $this->flattenHeaders($this->clientHeaders);
    $blacklist = array_map('strtolower', $this->headerBlacklist);

    foreach ($this->forwardHeaders as $pattern) {
        $pattern = strtolower(trim($pattern));

        foreach ($clientHeadersFlat as $headerName => $headerValue) {
            $headerNameLower = strtolower($headerName);

            // 检查黑名单
            if (in_array($headerNameLower, $blacklist, true)) {
                continue;
            }

            // 匹配模式
            if ($this->matchHeaderPattern($pattern, $headerNameLower)) {
                $result[$headerName] = $headerValue;
            }
        }
    }

    return $result;
}
```

#### 4. 模式匹配实现

`app/Services/Provider/Driver/AbstractProvider.php:254-269`

```php
protected function matchHeaderPattern(string $pattern, string $headerName): bool
{
    // 前缀匹配: user-agent*
    if (str_ends_with($pattern, '*')) {
        $prefix = substr($pattern, 0, -1);
        return str_starts_with($headerName, $prefix);
    }

    // 后缀匹配: *agent
    if (str_starts_with($pattern, '*')) {
        $suffix = substr($pattern, 1);
        return str_ends_with($headerName, $suffix);
    }

    // 精确匹配: user-agent
    return $pattern === $headerName;
}
```

#### 5. Header合并

`app/Services/Provider/Driver/AbstractProvider.php:271-281`

```php
protected function mergeForwardedHeaders(array $headers): array
{
    $forwardedHeaders = $this->buildForwardedHeaders();
    foreach ($forwardedHeaders as $key => $value) {
        if (!isset($headers[$key])) {
            $headers[$key] = $value;  // 不覆盖已有的header
        }
    }

    return $headers;
}
```

### 黑名单机制

#### 默认黑名单

AbstractProvider初始无默认黑名单：

```php
protected array $headerBlacklist = [];
```

#### 自定义黑名单

可通过渠道高级配置（config字段）添加：

```json
{
  "header_blacklist": ["authorization", "cookie", "x-api-key"]
}
```

黑名单header即使配置了转发也会被过滤。

## 配置示例

### User-Agent穿透

**场景**：让上游API识别真实客户端类型

**配置**：

```json
["user-agent"]
```

**效果**：
- 客户端 `User-Agent: Mozilla/5.0 (Chrome/120.0)`
- 上游API收到相同User-Agent

### IP地址穿透

**场景**：传递真实客户端IP到上游

**配置**：

```json
["x-real-ip", "x-forwarded-for"]
```

**效果**：
- 客户端 `X-Real-IP: 192.168.1.100`
- 上游API收到相同IP信息

### 自定义追踪ID

**场景**：转发请求追踪标识

**配置**：

```json
["x-request-id", "x-trace-id"]
```

**效果**：
- 客户端自定义追踪ID透传到上游
- 便于日志关联和问题排查

### 多header组合

**场景**：综合信息穿透

**配置**：

```json
[
  "user-agent",
  "x-real-ip",
  "x-forwarded-for",
  "x-request-id"
]
```

## 安全建议

### 1. 避免转发敏感Header

不建议转发以下header：

- `authorization` - 认证令牌
- `cookie` - 会话信息
- `x-api-key` - API密钥
- `authentication` - 认证信息

### 2. 使用黑名单机制

渠道高级配置添加黑名单：

```json
{
  "header_blacklist": ["authorization", "cookie", "x-api-key"]
}
```

### 3. 谨慎使用通配符

避免使用 `*` 全量转发，可能泄露敏感信息。

### 4. 优先级说明

Provider的预设header不会被穿透header覆盖（第276行判断），避免破坏上游API认证。

## 性能考虑

### Header数量影响

- 少量header（1-5个）：性能影响可忽略
- 大量header（>10个）：建议评估性能影响
- 全量转发 `*`：需谨慎评估

### 建议配置

只转发业务必要的header，避免不必要的开销。

## 测试验证

### 测试步骤

1. 配置渠道的 `forward_headers`
2. 发送包含目标header的请求
3. 查看 `channel_request_logs` 表的 `request_headers` 字段
4. 验证header是否成功转发

### 测试命令

使用request replay命令验证：

```bash
cd laravel
php artisan cdapi:request:replay-channel --request-id=123 --show-body
```

### 日志查看

检查渠道请求日志：

```sql
SELECT
    request_headers,
    response_headers
FROM channel_request_logs
WHERE request_id = 123;
```

## 常见问题

### Q1: Header名称大小写？

**答**：匹配不区分大小写。系统内部转为小写比较，但转发时保留原始格式。

### Q2: 为什么header没有转发？

**可能原因**：
1. 未配置 `forward_headers`
2. Header在黑名单中
3. Header名称不匹配配置模式
4. Provider已预设该header（优先级更高）

### Q3: 如何查看实际转发的headers？

**答**：查看 `channel_request_logs.request_headers` 或使用 `cdapi:request:replay-channel --show-body`。

### Q4: 可以动态修改配置吗？

**答**：可以。配置保存在数据库，修改后立即生效，无需重启。

### Q5: 是否支持条件转发？

**答**：当前版本不支持。Header转发基于静态配置，不根据请求内容动态判断。

## 相关文档

- [09-渠道管理设计](09-渠道管理设计.md)
- [05-认证授权设计](05-认证授权设计.md)
- [01-整体架构设计](01-整体架构设计.md)

## 更新日志

| 日期 | 版本 | 变更 |
|------|------|------|
| 2026-05-12 | 1.0 | 初始版本 |

---
**文档维护**: CdApi开发团队
**适用版本**: CdApi (Laravel 12)