# User-Agent 限制逻辑梳理

**文档创建时间**: 2026-05-12 22:12
**工作记录**: work/2026-05/12-2230-user-agent-limit-logic.md

---

## 一、核心概览

### 1.1 功能定位
User-Agent 限制是 CdApi 渠道路由系统的一个过滤环节，用于根据请求的 User-Agent 头部筛选可用的渠道。

### 1.2 执行时机
在渠道路由选择流程中，User-Agent 过滤发生在以下环节之后：
1. 模型匹配
2. API Key 渠道限制
3. 透传协议匹配
4. **User-Agent 过滤** ← 本次梳理重点
5. 负载均衡选择

### 1.3 关键组件

| 组件 | 文件路径 | 职责 |
|------|---------|------|
| 渠道路由服务 | `laravel/app/Services/Router/ChannelRouterService.php` | 整体路由编排，调用 User-Agent 过滤 |
| User-Agent 过滤服务 | `laravel/app/Services/Router/UserAgentFilterService.php` | 执行具体的过滤逻辑 |
| 渠道模型 | `laravel/app/Models/Channel.php` | 判断单个渠道是否允许指定 User-Agent |
| User-Agent 规则模型 | `laravel/app/Models/UserAgent.php` | 定义正则匹配规则，记录命中统计 |

---

## 二、数据库设计

### 2.1 核心表结构

#### user_agents 表（规则定义）

```sql
CREATE TABLE user_agents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT '规则名称',
    patterns JSON NOT NULL COMMENT '正则表达式数组',
    description TEXT NULL COMMENT '规则描述',
    is_enabled TINYINT(1) DEFAULT 1 COMMENT '是否启用',
    hit_count BIGINT UNSIGNED DEFAULT 0 COMMENT '命中次数',
    last_hit_at TIMESTAMP NULL COMMENT '最后命中时间',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_enabled (is_enabled)
);
```

**字段说明**:
- `patterns`: JSON 数组，存储多个正则表达式（任意一条匹配即命中）
- `hit_count`: 统计该规则被匹配的总次数
- `is_enabled`: 启用状态，禁用的规则不参与匹配

**patterns 示例**:
```json
[
    "^Claude-Code\\/.*",
    "^Mozilla\\/5\\.0.*Chrome.*"
]
```

#### channel_user_agent 表（渠道-规则关联）

```sql
CREATE TABLE channel_user_agent (
    channel_id BIGINT UNSIGNED NOT NULL COMMENT '渠道ID',
    user_agent_id BIGINT UNSIGNED NOT NULL COMMENT 'User-Agent ID',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (channel_id, user_agent_id),
    INDEX idx_channel_id (channel_id),
    INDEX idx_user_agent_id (user_agent_id)
);
```

**关联关系**:
- 多对多关系：一个渠道可关联多条 User-Agent 规则
- 一个 User-Agent 规则可被多个渠道使用
- 只关联启用状态的规则（模型层过滤 `where('is_enabled', true)`）

#### channels 表（渠道开关）

```sql
ALTER TABLE channels ADD COLUMN
    has_user_agent_restriction TINYINT(1) DEFAULT 0 COMMENT '是否有UA限制';
```

**关键索引**:
```sql
INDEX idx_has_ua_restriction (has_user_agent_restriction)
```

---

## 三、执行流程详解

### 3.1 总体路由流程

```
请求到达
    ↓
ChannelRouterService::selectChannel()
    ↓
① 获取支持该模型的渠道列表
    ↓
② 应用 API Key 渠道限制（黑名单/白名单）
    ↓
③ 应用透传协议匹配过滤
    ↓
④ 应用 User-Agent 过滤 ← 【本次梳理重点】
    ↓
⑤ 排除已失败的渠道
    ↓
⑥ 负载均衡选择最终渠道
```

### 3.2 User-Agent 过滤详细流程

```php
// ChannelRouterService.php 第 77-89 行

// 步骤1：获取 User-Agent
$userAgent = $context['user_agent'] ?? request()->header('User-Agent', '');

// 步骤2：调用过滤服务
$channelsAfterUserAgent = $this->applyUserAgentFilter($channelsAfterProtocol, $userAgent);

// 步骤3：检查过滤结果
if ($channelsAfterUserAgent->isEmpty()) {
    // 所有渠道都被 User-Agent 限制过滤掉
    Log::warning('All candidate channels filtered by User-Agent restriction');
    throw new BadRequestHttpException("No available channel with current User-Agent");
}
```

### 3.3 applyUserAgentFilter 方法实现

```php
// ChannelRouterService.php 第 277-287 行

protected function applyUserAgentFilter($channels, string $userAgent)
{
    // 如果 User-Agent 为空，不过滤
    if (empty($userAgent)) {
        return $channels;
    }

    // 调用 UserAgentFilterService
    $userAgentFilter = app(UserAgentFilterService::class);
    return $userAgentFilter->filterChannels($channels, $userAgent);
}
```

### 3.4 UserAgentFilterService 核心逻辑

```php
// UserAgentFilterService.php 第 23-44 行

public function filterChannels(Collection $channels, string $userAgent): Collection
{
    // User-Agent 为空，不过滤
    if (empty($userAgent)) {
        return $channels;
    }

    return $channels->filter(function (Channel $channel) use ($userAgent) {
        // 调用渠道模型的判断方法
        $allowed = $channel->isUserAgentAllowed($userAgent);

        if (!$allowed) {
            Log::info('渠道User-Agent不匹配，已跳过', [
                'channel_id' => $channel->id,
                'channel_name' => $channel->name,
                'user_agent' => $userAgent,
            ]);
        }

        return $allowed;
    });
}
```

---

## 四、渠道判断逻辑

### 4.1 isUserAgentAllowed 方法（核心判断）

```php
// Channel.php 第 344-369 行

public function isUserAgentAllowed(string $userAgent): bool
{
    // 步骤1：检查是否启用了 User-Agent 限制
    if (!$this->hasUserAgentRestriction()) {
        return true; // 无限制，允许所有 User-Agent
    }

    // 步骤2：获取关联的 User-Agent 规则
    $allowedPatterns = $this->allowedUserAgents; // 多对多关联查询

    // 步骤3：有限制但未配置规则
    if ($allowedPatterns->isEmpty()) {
        return false; // 拒绝访问
    }

    // 步骤4：遍历规则，检查匹配
    foreach ($allowedPatterns as $pattern) {
        if ($pattern->matches($userAgent)) {
            $pattern->recordHit(); // 记录命中统计
            return true;
        }
    }

    // 步骤5：没有任何规则匹配
    return false;
}
```

**决策逻辑表**:

| has_user_agent_restriction | allowedUserAgents 数量 | User-Agent 匹配结果 | 最终判定 |
|---------------------------|----------------------|-------------------|---------|
| false (0) | - | - | **允许**（无限制） |
| true (1) | 0 | - | **拒绝**（有限制但无规则） |
| true (1) | ≥1 | 匹配到任意规则 | **允许** |
| true (1) | ≥1 | 未匹配任何规则 | **拒绝** |

### 4.2 hasUserAgentRestriction 方法

```php
// Channel.php 第 333-336 行

public function hasUserAgentRestriction(): bool
{
    return (bool) $this->has_user_agent_restriction;
}
```

**字段来源**: `channels.has_user_agent_restriction`（布尔值）

### 4.3 allowedUserAgents 关联

```php
// Channel.php 第 323-328 行

public function allowedUserAgents(): BelongsToMany
{
    return $this->belongsToMany(UserAgent::class, 'channel_user_agent')
        ->withTimestamps()
        ->where('is_enabled', true); // 只关联启用的规则
}
```

---

## 五、规则匹配逻辑

### 5.1 matches 方法实现

```php
// UserAgent.php 第 66-98 行

public function matches(string $userAgent): bool
{
    // 步骤1：检查规则启用状态
    if (!$this->is_enabled) {
        return false;
    }

    // 步骤2：获取正则表达式数组
    $patterns = $this->patterns ?? [];

    if (empty($patterns)) {
        return false;
    }

    // 步骤3：遍历正则，任意一条匹配即返回 true
    foreach ($patterns as $pattern) {
        try {
            if (@preg_match($pattern, $userAgent)) {
                return true; // 匹配成功
            }
        } catch (\Exception $e) {
            Log::error('User-Agent正则匹配失败', [
                'pattern' => $pattern,
                'user_agent' => $userAgent,
                'error' => $e->getMessage(),
            ]);
            continue; // 继续尝试下一个正则
        }
    }

    // 步骤4：所有正则都不匹配
    return false;
}
```

**关键特性**:
- **多正则支持**: 一条规则可包含多个正则表达式（JSON 数组）
- **任意匹配**: 任意一条正则匹配即判定为命中
- **异常容错**: 单个正则匹配失败不影响其他正则执行
- **静默匹配**: 使用 `@preg_match` 抑制警告

### 5.2 recordHit 统计方法

```php
// UserAgent.php 第 103-107 行

public function recordHit(): void
{
    $this->increment('hit_count');
    $this->update(['last_hit_at' => now()]);
}
```

**统计意义**:
- `hit_count`: 记录规则总命中次数，用于效果评估
- `last_hit_at`: 最后命中时间，用于活跃度监控

---

## 六、安全机制

### 6.1 正则表达式验证

```php
// UserAgent.php 第 128-150 行（模型保存时触发）

protected static function boot()
{
    parent::boot();

    static::saving(function ($model) {
        $patterns = $model->patterns ?? [];

        foreach ($patterns as $index => $pattern) {
            // 验证正则有效性
            if (@preg_match($pattern, '') === false) {
                throw new \InvalidArgumentException(
                    "第{$index}条正则表达式无效: {$pattern}"
                );
            }

            // 检测危险模式（性能风险警告）
            if (preg_match('/[\*\+]{2,}/', $pattern)) {
                Log::warning('User-Agent正则表达式可能存在性能风险', [
                    'pattern' => $pattern,
                    'index' => $index,
                ]);
            }
        }
    });
}
```

**验证机制**:
1. **有效性检查**: 无效正则抛出异常，阻止保存
2. **性能风险检测**: 连续的 `**` 或 `++` 模式触发警告
3. **保存前拦截**: 在 `saving` 事件中验证，确保数据质量

---

## 七、典型场景示例

### 7.1 场景1：无限制渠道

**配置**:
- `channels.has_user_agent_restriction = 0`

**执行结果**:
- 所有 User-Agent 都能访问该渠道
- 不会查询 `user_agents` 表
- 无性能损耗

### 7.2 场景2：仅允许特定客户端

**渠道配置**:
- `has_user_agent_restriction = 1`
- 关联规则：`Claude-Code UA` 规则

**规则定义**:
```json
{
    "name": "Claude-Code UA",
    "patterns": [
        "^Claude-Code\\/.*",
        "^claude-code\\/.*"
    ],
    "is_enabled": true
}
```

**请求示例**:
```
User-Agent: Claude-Code/1.0.0
→ 匹配第一条正则 → 允许访问

User-Agent: PostmanRuntime/7.32.0
→ 不匹配任何正则 → 拒绝访问
```

### 7.3 场景3：多规则组合

**渠道配置**:
- 关联两条规则：
  1. `Claude-Code UA`（正则：`^Claude-Code\\/.*`）
  2. `Browser UA`（正则：`^Mozilla\\/.*Chrome.*`）

**逻辑**:
- 任意一条规则匹配即允许
- 实现了"允许 Claude-Code 或 Chrome 浏览器"的组合策略

### 7.4 场景4：空 User-Agent 请求

**请求**:
```
User-Agent: (空或未设置)
```

**执行逻辑**:
```php
// UserAgentFilterService.php 第 25-28 行
if (empty($userAgent)) {
    return $channels; // 不过滤，保留所有渠道
}
```

**结果**: 空 User-Agent 的请求不受限制，能访问所有渠道

---

## 八、性能分析

### 8.1 数据库查询优化

**索引设计**:
```sql
-- channels 表
INDEX idx_has_ua_restriction (has_user_agent_restriction)

-- user_agents 表
INDEX idx_enabled (is_enabled)

-- channel_user_agent 表
INDEX idx_channel_id (channel_id)
INDEX idx_user_agent_id (user_agent_id)
```

**查询路径**:
1. 渠道模型查询关联规则时，自动过滤 `is_enabled = true`
2. 利用主键索引快速定位关联关系

### 8.2 正则匹配性能

**风险点**:
- 复杂正则可能导致匹配性能下降
- 大量请求并发匹配时可能影响吞吐量

**优化建议**:
1. 正则表达式尽量简洁，避免嵌套和回溯
2. 模型保存时已检测危险模式
3. 可考虑缓存匹配结果（高频 User-Agent）

### 8.3 建议改进方向

#### 改进1：User-Agent 匹配缓存

```php
// 建议实现：缓存已匹配的 User-Agent
$cacheKey = "ua_match:{$channelId}:{$userAgentHash}";
if (Cache::has($cacheKey)) {
    return Cache::get($cacheKey);
}

// 执行匹配逻辑
$result = $this->performMatch($userAgent);
Cache::put($cacheKey, $result, 60); // 缓存60秒

return $result;
```

**适用场景**: 高频 User-Agent（如标准客户端）

#### 改进2：快速白名单模式

```php
// 在 patterns JSON 中添加精确匹配选项
{
    "patterns": [
        {"type": "exact", "value": "Claude-Code/1.0.0"},
        {"type": "regex", "value": "^Claude-Code\\/.*"}
    ]
}
```

**性能提升**: 精确匹配无需正则，速度更快

---

## 九、关键代码位置索引

| 功能点 | 文件 | 行号 |
|--------|------|------|
| 路由总入口 | ChannelRouterService.php | 46-118 |
| User-Agent 过滤调用 | ChannelRouterService.php | 77-89 |
| applyUserAgentFilter 方法 | ChannelRouterService.php | 277-287 |
| 过滤服务核心逻辑 | UserAgentFilterService.php | 23-44 |
| 渠道判断方法 | Channel.php | 344-369 |
| 限制开关检查 | Channel.php | 333-336 |
| 规则关联关系 | Channel.php | 323-328 |
| 正则匹配逻辑 | UserAgent.php | 66-98 |
| 命中统计记录 | UserAgent.php | 103-107 |
| 正则验证机制 | UserAgent.php | 128-150 |

---

## 十、总结与建议

### 10.1 设计优点

1. **灵活性强**: 多对多关联，支持复杂组合策略
2. **可维护性**: 规则独立管理，渠道复用规则
3. **统计完善**: 命中次数和时间记录，便于效果分析
4. **安全机制**: 正则验证和性能风险检测
5. **容错设计**: 单个正则失败不影响整体匹配

### 10.2 待改进点

1. **性能优化**: 高频场景可增加缓存机制
2. **空 UA 策略**: 当前允许空 UA，可能需针对性控制
3. **规则优先级**: 当前无优先级，可考虑加权匹配
4. **日志增强**: 可记录未匹配的 UA 用于规则优化

### 10.3 建议配置示例

**推荐规则设计**:
```json
{
    "name": "Official Clients",
    "patterns": [
        "^Claude-Code\\/.*",           // Claude Code 客户端
        "^OpenAI-Python\\/.*",         // OpenAI Python SDK
        "^curl\\/.*",                  // curl 命令行工具
        "^PostmanRuntime\\/.*"         // Postman 测试工具
    ],
    "description": "允许官方客户端和常用测试工具"
}
```

**推荐渠道配置**:
- 生产环境渠道：`has_user_agent_restriction = 1`，关联官方客户端规则
- 测试环境渠道：`has_user_agent_restriction = 0`，无限制便于调试

---

## 附录：相关文档链接

- [认证授权设计](../../docs/05-认证授权设计.md) - 包含 User-Agent 安全措施说明
- [数据库设计](../../docs/02-数据库设计.md) - 包含 user_agents 表设计
- [渠道路由架构](../../docs/01-整体架构设计.md) - 整体路由流程

---

**文档版本**: 1.0
**最后更新**: 2026-05-12