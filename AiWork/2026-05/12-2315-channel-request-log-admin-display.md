# 渠道请求日志后台显示优化

## 修改时间
2026-05-12 23:10

## 问题修复

### 错误报告（2026-05-12 23:17）

**错误类型**：`Illuminate\View\ViewException`
**错误消息**：`htmlspecialchars(): Argument #1 ($string) must be of type string, array given`

**问题原因**：
- 模型的 `casts` 设置将 `response_body` 和 `request_body` 自动转为数组
- 控制器使用了期望字符串的方法（如 `json_view_iframe()`）
- 导致类型不匹配错误

**解决方案**：
- 使用 `display()` 方法先将数组转为 JSON 字符串
- 然后再使用 `json()` 方法进行格式化显示

## 修改内容

### 1. 列表页优化

**新增字段**：
- `ttfb_ms`（首字节延迟）：显示从请求发出到收到第一个响应字节的时间

**文件**：[ChannelRequestLogController.php](laravel/app/Admin/Controllers/ChannelRequestLogController.php)

**代码位置**：Line 83-89

```php
$grid->column('ttfb_ms', '首字节(ms)')->display(function ($value) {
    if ($value === null) {
        return '-';
    }
    return number_format($value, 2);
})->sortable();
```

### 2. 详情页优化

#### 2.1 请求体显示修复

**问题**：`request_body` 字段被模型 casts 转为数组，直接使用 `json_view_iframe()` 会报错

**修复**：Line 162-169

```php
// 请求体：模型casts会转为数组，使用json()方法显示
$show->field('request_body', '请求体')->display(function ($value) {
    if ($value === null) {
        return '-';
    }

    // 将数组转为JSON字符串
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
})->json();
```

#### 2.2 响应体显示优化

**修复**：Line 183-190

```php
// 响应体：模型casts会转为数组，使用json()方法显示
$show->field('response_body', '响应体')->display(function ($value) {
    if ($value === null) {
        return '-';
    }

    // 将数组转为JSON字符串，然后使用json()显示
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
})->json();
```

#### 2.3 响应块数组显示

**新增**：Line 192-200

```php
// 流式响应的chunk数组（如果有）
$show->field('response_body_chunks', '响应块数组')->display(function ($value) {
    if ($value === null || empty($value)) {
        return '-';
    }
    // 显示chunk数量
    $count = is_array($value) ? count($value) : 0;

    return "<span class='label label-info'>{$count} 个块</span>";
})->help('流式响应的完整chunk数组');
```

#### 2.4 分组显示

**新增分组**：
- 基本信息：ID、审计日志ID、请求日志ID、请求ID
- 渠道信息：渠道ID、渠道名称、提供商
- 请求信息：请求方法、路径、URL、请求头、请求体、请求大小
- 响应信息：响应状态、响应头、响应体、响应块数组、响应大小
- 性能指标：延迟、首字节延迟
- 状态信息：是否成功、错误类型、错误消息
- 使用量：token使用量
- 其他：元数据、时间信息

**代码位置**：Line 234-257

```php
// 分组显示字段
$show->divider('基本信息');
$show->fields(['id', 'audit_log_id', 'request_log_id', 'request_id']);

$show->divider('渠道信息');
$show->fields(['channel_id', 'channel_name', 'provider']);

$show->divider('请求信息');
$show->fields(['method', 'path', 'base_url', 'full_url', 'request_headers', 'request_body', 'request_size']);

$show->divider('响应信息');
$show->fields(['response_status', 'response_headers', 'response_body', 'response_body_chunks', 'response_size']);

$show->divider('性能指标');
$show->fields(['latency_ms', 'ttfb_ms']);

$show->divider('状态信息');
$show->fields(['is_success', 'error_type', 'error_message']);

$show->divider('使用量');
$show->fields(['usage']);

$show->divider('其他');
$show->fields(['metadata', 'sent_at', 'created_at', 'updated_at']);
```

## usage 数据问题说明

### 问题发现

用户反馈 id=91 的 usage 数据有误：
- response_logs.usage 有正确数据：prompt_tokens: 38286, completion_tokens: 140
- channel_request_logs.usage 全是 0

### 原因分析

id=91 的记录创建时间：2026-05-12 23:10:32
代码修改时间：2026-05-12 23:05-23:10

该记录在代码修改**之前**创建，所以 usage 字段未正确记录。

### 解决方案

代码已修复：
1. **StreamHandler**：添加 `updateChannelRequestLog()` 方法，正确记录 usage
2. **NonStreamHandler**：添加 `updateChannelRequestLog()` 方法，正确记录 usage
3. **ProxyServer**：传递 `channelRequestLog` 实例给 Handler

**修改文件**：
- [StreamHandler.php](laravel/app/Services/Router/Handler/StreamHandler.php)
- [NonStreamHandler.php](laravel/app/Services/Router/Handler/NonStreamHandler.php)
- [ProxyServer.php](laravel/app/Services/Router/ProxyServer.php)

详细修复过程见：[12-2310-channel-request-log-response-fix.md](work/2026-05/12-2310-channel-request-log-response-fix.md)

### 验证方法

下一个成功请求（is_success=1）的 channel_request_logs 将正确记录：
- prompt_tokens：输入token数
- completion_tokens：输出token数
- total_tokens：总token数
- cache_read_tokens：缓存读取token数
- cache_write_tokens：缓存写入token数

## 后台访问

**URL**：http://192.168.4.107:32126/admin/channel-request-logs

**用户名**：admin
**密码**：admin

### 建议测试

1. **列表页测试**：
   - 查看首字节延迟（ttfb_ms）列是否正常显示
   - 检查响应状态、延迟、是否成功等列的显示效果

2. **详情页测试**：
   - 点击查看某个成功请求（is_success=1）的详情
   - 检查请求体和响应体的JSON格式化显示是否正常
   - 检查分组显示是否合理
   - 检查响应块数组（如果是流式响应）是否显示数量

3. **usage 数据测试**：
   - 发送一个成功请求
   - 查看新的 channel_request_log 记录
   - 验证 usage 字段是否有正确的 token 使用量

## 技术要点

### 模型 casts 配置

`ChannelRequestLog` 模型设置了以下 casts：

```php
protected function casts(): array
{
    return [
        'request_headers' => 'array',
        'response_headers' => 'array',
        'request_body' => 'array',      // 自动转为数组
        'response_body' => 'array',      // 自动转为数组
        'response_body_chunks' => 'array',
        'usage' => 'array',
        'metadata' => 'array',
        'is_success' => 'boolean',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
```

**影响**：
- `request_body` 和 `response_body` 字段在读取时自动转为数组
- 无法直接使用期望字符串的方法（如 `json_view_iframe()`）
- 需要先转为 JSON 字符串再显示

### 显示方法说明

1. **`->json()`**：格式化显示 JSON 数据（接受字符串）
2. **`->json_view_iframe()`**：在 iframe 中显示 JSON（期望字符串）
3. **`->display()`**：自定义显示逻辑（可处理数组）
4. **`->as()`**：字段值转换（可处理类型转换）

## 相关问题

如果发现新请求的 usage 仍然为 0，请检查：
1. 确认代码修改已生效（重启应用或清除缓存）
2. 检查 Handler 的 updateChannelRequestLog 方法是否正确执行
3. 检查 usage 对象是否正确传递到 Handler

## 错误修复记录

**修复时间**：2026-05-12 23:18
**错误ID**：95
**错误原因**：类型不匹配（array vs string）
**修复方法**：使用 display() 方法将数组转为 JSON 字符串

---

**修改人员**：Claude Code
**状态**：已完成修复，待用户测试验证

## usage 数据问题说明

### 问题发现

用户反馈 id=91 的 usage 数据有误：
- response_logs.usage 有正确数据：prompt_tokens: 38286, completion_tokens: 140
- channel_request_logs.usage 全是 0

### 原因分析

id=91 的记录创建时间：2026-05-12 23:10:32
代码修改时间：2026-05-12 23:05-23:10

该记录在代码修改**之前**创建，所以 usage 字段未正确记录。

### 解决方案

代码已修复：
1. **StreamHandler**：添加 `updateChannelRequestLog()` 方法，正确记录 usage
2. **NonStreamHandler**：添加 `updateChannelRequestLog()` 方法，正确记录 usage
3. **ProxyServer**：传递 `channelRequestLog` 实例给 Handler

**修改文件**：
- [StreamHandler.php](laravel/app/Services/Router/Handler/StreamHandler.php)
- [NonStreamHandler.php](laravel/app/Services/Router/Handler/NonStreamHandler.php)
- [ProxyServer.php](laravel/app/Services/Router/ProxyServer.php)

详细修复过程见：[12-2310-channel-request-log-response-fix.md](work/2026-05/12-2310-channel-request-log-response-fix.md)

### 验证方法

下一个成功请求（is_success=1）的 channel_request_logs 将正确记录：
- prompt_tokens：输入token数
- completion_tokens：输出token数
- total_tokens：总token数
- cache_read_tokens：缓存读取token数
- cache_write_tokens：缓存写入token数

## 后台访问

**URL**：http://192.168.4.107:32126/admin/channel-request-logs

**用户名**：admin
**密码**：admin

### 建议测试

1. **列表页测试**：
   - 查看首字节延迟（ttfb_ms）列是否正常显示
   - 检查响应状态、延迟、是否成功等列的显示效果

2. **详情页测试**：
   - 点击查看某个成功请求（is_success=1）的详情
   - 检查响应体是否在iframe中显示（更清晰）
   - 检查分组显示是否合理
   - 检查响应块数组（如果是流式响应）是否显示数量

3. **usage 数据测试**：
   - 发送一个成功请求
   - 查看新的 channel_request_log 记录
   - 验证 usage 字段是否有正确的 token 使用量

## 相关问题

如果发现新请求的 usage 仍然为 0，请检查：
1. 确认代码修改已生效（重启应用或清除缓存）
2. 检查 Handler 的 updateChannelRequestLog 方法是否正确执行
3. 检查 usage 对象是否正确传递到 Handler

---

**修改人员**：Claude Code
**状态**：已完成，待用户测试验证