## 渠道亲和性（Channel Affinity）功能实现总结

我已在代码库中找到了完整的"渠道亲和性"功能实现。以下是所有相关文件的完整路径和关键代码片段：

### 一、后端核心实现

#### 1. 服务层 - 渠道亲和性核心逻辑
**文件路径**: `/data/ai/new-api/service/channel_affinity.go`

这是渠道亲和性功能的核心实现文件，包含以下关键功能：

```go
// 关键常量定义
const (
    ginKeyChannelAffinityCacheKey   = "channel_affinity_cache_key"
    ginKeyChannelAffinityTTLSeconds = "channel_affinity_ttl_seconds"
    ginKeyChannelAffinityMeta       = "channel_affinity_meta"
    ginKeyChannelAffinityLogInfo    = "channel_affinity_log_info"
    ginKeyChannelAffinitySkipRetry  = "channel_affinity_skip_retry_on_failure"
    
    channelAffinityCacheNamespace           = "new-api:channel_affinity:v1"
    channelAffinityUsageCacheStatsNamespace = "new-api:channel_affinity_usage_cache_stats:v1"
)

// 渠道亲和性元数据结构
type channelAffinityMeta struct {
    CacheKey       string
    TTLSeconds     int
    RuleName       string
    SkipRetry      bool
    ParamTemplate  map[string]interface{}
    KeySourceType  string
    KeySourceKey   string
    KeySourcePath  string
    KeyHint        string
    KeyFingerprint string
    UsingGroup     string
    ModelName      string
    RequestPath    string
}

// 获取首选渠道（核心函数）
func GetPreferredChannelByAffinity(c *gin.Context, modelName string, usingGroup string) (int, bool) {
    setting := operation_setting.GetChannelAffinitySetting()
    if setting == nil || !setting.Enabled {
        return 0, false
    }
    // 遍历规则匹配
    for _, rule := range setting.Rules {
        if !matchAnyRegexCached(rule.ModelRegex, modelName) {
            continue
        }
        // ... 规则匹配逻辑
        cache := getChannelAffinityCache()
        channelID, found, err := cache.Get(cacheKeySuffix)
        if found {
            return channelID, true
        }
        return 0, false
    }
}

// 记录渠道亲和性（请求成功后调用）
func RecordChannelAffinity(c *gin.Context, channelID int) {
    // 将成功使用的渠道ID缓存起来
    cache := getChannelAffinityCache()
    cache.SetWithTTL(cacheKey, channelID, time.Duration(ttlSeconds)*time.Second)
}
```

#### 2. 配置定义 - 渠道亲和性设置
**文件路径**: `/data/ai/new-api/setting/operation_setting/channel_affinity_setting.go`

```go
// 渠道亲和性规则结构
type ChannelAffinityRule struct {
    Name             string                     `json:"name"`
    ModelRegex       []string                   `json:"model_regex"`        // 模型名称正则匹配
    PathRegex        []string                   `json:"path_regex"`         // 请求路径正则匹配
    UserAgentInclude []string                   `json:"user_agent_include"` // User-Agent包含匹配
    KeySources       []ChannelAffinityKeySource `json:"key_sources"`        // Key来源配置
    
    ValueRegex string `json:"value_regex"` // 提取值的正则校验
    TTLSeconds int    `json:"ttl_seconds"` // 缓存TTL
    
    ParamOverrideTemplate map[string]interface{} `json:"param_override_template"` // 参数覆盖模板
    
    SkipRetryOnFailure bool `json:"skip_retry_on_failure"` // 失败后是否跳过重试
    
    IncludeUsingGroup bool `json:"include_using_group"` // 是否包含分组在cache key中
    IncludeRuleName   bool `json:"include_rule_name"`   // 是否包含规则名称在cache key中
}

// Key来源配置
type ChannelAffinityKeySource struct {
    Type string `json:"type"` // context_int, context_string, gjson
    Key  string `json:"key,omitempty"`
    Path string `json:"path,omitempty"`
}

// 默认配置（包含Codex CLI和Claude CLI模板）
var channelAffinitySetting = ChannelAffinitySetting{
    Enabled:           true,
    SwitchOnSuccess:   true,
    MaxEntries:        100_000,
    DefaultTTLSeconds: 3600,
    Rules: []ChannelAffinityRule{
        {
            Name:       "codex cli trace",
            ModelRegex: []string{"^gpt-.*$"},
            PathRegex:  []string{"/v1/responses"},
            KeySources: []ChannelAffinityKeySource{
                {Type: "gjson", Path: "prompt_cache_key"},
            },
            ParamOverrideTemplate: buildPassHeaderTemplate(codexCliPassThroughHeaders),
        },
        {
            Name:       "claude cli trace",
            ModelRegex: []string{"^claude-.*$"},
            PathRegex:  []string{"/v1/messages"},
            KeySources: []ChannelAffinityKeySource{
                {Type: "gjson", Path: "metadata.user_id"},
            },
            ParamOverrideTemplate: buildPassHeaderTemplate(claudeCliPassThroughHeaders),
        },
    },
}
```

#### 3. 控制器 - 缓存管理API
**文件路径**: `/data/ai/new-api/controller/channel_affinity_cache.go`

```go
// 获取缓存统计
func GetChannelAffinityCacheStats(c *gin.Context) {
    stats := service.GetChannelAffinityCacheStats()
    c.JSON(http.StatusOK, gin.H{
        "success": true,
        "data":    stats,
    })
}

// 清空缓存
func ClearChannelAffinityCache(c *gin.Context) {
    all := strings.TrimSpace(c.Query("all"))
    ruleName := strings.TrimSpace(c.Query("rule_name"))
    
    if all == "true" {
        deleted := service.ClearChannelAffinityCacheAll()
        // ...
    }
    // 按规则名称清空
    deleted, err := service.ClearChannelAffinityCacheByRuleName(ruleName)
}

// 获取使用缓存统计
func GetChannelAffinityUsageCacheStats(c *gin.Context) {
    stats := service.GetChannelAffinityUsageCacheStats(ruleName, usingGroup, keyFp)
    c.JSON(http.StatusOK, gin.H{
        "success": true,
        "data":    stats,
    })
}
```

#### 4. 中间件 - 渠道分发
**文件路径**: `/data/ai/new-api/middleware/distributor.go`

```go
func Distribute() func(c *gin.Context) {
    return func(c *gin.Context) {
        // ...
        // 检查渠道亲和性
        if preferredChannelID, found := service.GetPreferredChannelByAffinity(c, modelRequest.Model, usingGroup); found {
            preferred, err := model.CacheGetChannel(preferredChannelID)
            if err == nil && preferred != nil && preferred.Status == common.ChannelStatusEnabled {
                channel = preferred
                service.MarkChannelAffinityUsed(c, selectGroup, preferred.Id)
            }
        }
        
        // 请求成功后记录渠道亲和性
        if channel != nil && c.Writer.Status() < http.StatusBadRequest {
            service.RecordChannelAffinity(c, channel.Id)
        }
    }
}
```

#### 5. Relay控制器 - 重试逻辑
**文件路径**: `/data/ai/new-api/controller/relay.go`

```go
func shouldRetry(c *gin.Context, openaiErr *types.NewAPIError, retryTimes int) bool {
    // 检查是否应跳过重试（渠道亲和性失败时）
    if service.ShouldSkipRetryAfterChannelAffinityFailure(c) {
        return false
    }
    // ...
}

func processChannelError(c *gin.Context, channelError types.ChannelError, err *types.NewAPIError) {
    // ...
    // 添加渠道亲和性信息到日志
    service.AppendChannelAffinityAdminInfo(c, adminInfo)
}
```

#### 6. Relay兼容处理器 - 使用量统计
**文件路径**: `/data/ai/new-api/relay/compatible_handler.go`

```go
func postConsumeQuota(ctx *gin.Context, relayInfo *relaycommon.RelayInfo, usage *dto.Usage) {
    // 观察渠道亲和性使用缓存统计
    if originUsage != nil {
        service.ObserveChannelAffinityUsageCacheByRelayFormat(ctx, usage, relayInfo.GetFinalRequestRelayFormat())
    }
    // ...
}
```

#### 7. 日志信息生成
**文件路径**: `/data/ai/new-api/service/log_info_generate.go`

```go
func GenerateTextOtherInfo(ctx *gin.Context, relayInfo *relaycommon.RelayInfo, ...) map[string]interface{} {
    // ...
    // 添加渠道亲和性信息到管理日志
    AppendChannelAffinityAdminInfo(ctx, adminInfo)
    // ...
}
```

#### 8. 缓存命名空间
**文件路径**: `/data/ai/new-api/pkg/cachex/namespace.go`

```go
// Namespace isolates keys between different cache use-cases
type Namespace string

func (n Namespace) FullKey(key string) string {
    // 生成完整的缓存key
    return p + strings.TrimLeft(key, ":")
}
```

#### 9. 路由配置
**文件路径**: `/data/ai/new-api/router/api-router.go`

```go
optionRoute.GET("/channel_affinity_cache", controller.GetChannelAffinityCacheStats)
optionRoute.DELETE("/channel_affinity_cache", controller.ClearChannelAffinityCache)

// 日志相关
logRoute.GET("/channel_affinity_usage_cache", middleware.AdminAuth(), controller.GetChannelAffinityUsageCacheStats)
```

---

### 二、前端实现

#### 1. 渠道亲和性设置页面
**文件路径**: `/data/ai/new-api/web/src/pages/Setting/Operation/SettingsChannelAffinity.jsx`

这是渠道亲和性配置的主要前端界面，包含：
- 启用/禁用开关
- 最大条目数配置
- 默认TTL配置
- 成功后切换亲和选项
- 规则可视化编辑/JSON编辑
- 缓存统计和清空功能

#### 2. 渠道亲和性模板常量
**文件路径**: `/data/ai/new-api/web/src/constants/channel-affinity-template.constants.js`

```javascript
export const CHANNEL_AFFINITY_RULE_TEMPLATES = {
  codexCli: {
    name: 'codex cli trace',
    model_regex: ['^gpt-.*$'],
    path_regex: ['/v1/responses'],
    key_sources: [{ type: 'gjson', path: 'prompt_cache_key' }],
    param_override_template: CODEX_CLI_HEADER_PASSTHROUGH_TEMPLATE,
    // ...
  },
  claudeCli: {
    name: 'claude cli trace',
    model_regex: ['^claude-.*$'],
    path_regex: ['/v1/messages'],
    key_sources: [{ type: 'gjson', path: 'metadata.user_id' }],
    param_override_template: CLAUDE_CLI_HEADER_PASSTHROUGH_TEMPLATE,
    // ...
  },
};
```

#### 3. 使用缓存统计弹窗
**文件路径**: `/data/ai/new-api/web/src/components/table/usage-logs/modals/ChannelAffinityUsageCacheModal.jsx`

显示渠道亲和性缓存命中率、token统计等信息。

#### 4. 日志列定义
**文件路径**: `/data/ai/new-api/web/src/components/table/usage-logs/UsageLogsColumnDefs.jsx`

```javascript
function buildChannelAffinityTooltip(affinity, t) {
  if (!affinity) {
    return null;
  }
  const keySource = affinity.key_source || '-';
  const keyPath = affinity.key_path || affinity.key_key || '-';
  const keyHint = affinity.key_hint || '';
  const keyFp = affinity.key_fp ? `#${affinity.key_fp}` : '';
  // ...
}
```

#### 5. 日志数据Hook
**文件路径**: `/data/ai/new-api/web/src/hooks/usage-logs/useUsageLogsData.jsx`

#### 6. 日志页面入口
**文件路径**: `/data/ai/new-api/web/src/components/table/usage-logs/index.jsx`

---

### 三、测试文件

#### 1. 模板测试
**文件路径**: `/data/ai/new-api/service/channel_affinity_template_test.go`

#### 2. 使用缓存测试
**文件路径**: `/data/ai/new-api/service/channel_affinity_usage_cache_test.go`

---

### 四、功能说明

渠道亲和性功能的主要作用是：

1. **粘滞选路**: 基于从请求上下文或JSON Body提取的Key，优先复用上一次成功的渠道
2. **规则匹配**: 支持按模型正则、路径正则、User-Agent等条件匹配规则
3. **Key来源**: 支持从context_int、context_string或gjson path提取亲和Key
4. **参数覆盖**: 命中规则后可自动合并参数覆盖模板
5. **缓存统计**: 支持查看缓存命中率、token使用统计等
6. **失败后不重试**: 可配置某些规则命中后失败不重试

预设模板支持Codex CLI和Claude CLI的header透传场景。
        
          
根据搜索结果，我为您详细分析 **渠道亲和性（Channel Affinity）** 功能：

## 渠道亲和性功能分析

### 一、功能概述

渠道亲和性是一个**智能路由**功能，其核心目的是**让相同的请求（基于特定Key）优先路由到之前成功使用过的渠道**，实现"粘滞"效果。这在以下场景特别有用：
- CLI工具（如 Codex CLI、Claude CLI）的连续对话需要保持上下文一致性
- 需要Header透传的场景（如 `x-codex-*` 系列Header）

---

### 二、核心架构

```
┌─────────────────────────────────────────────────────────────┐
│                      请求进入                                │
└───────────────────────┬─────────────────────────────────────┘
                        ▼
┌─────────────────────────────────────────────────────────────┐
│  middleware/distributor.go                                   │
│  ├── 提取模型名称、分组信息                                   │
│  ├── 调用 GetPreferredChannelByAffinity() 查询缓存          │
│  │   └── 匹配规则 → 提取Key → 查缓存 → 返回渠道ID           │
│  └── 如找到有效渠道，优先使用该渠道                           │
└───────────────────────┬─────────────────────────────────────┘
                        ▼
┌─────────────────────────────────────────────────────────────┐
│  请求处理成功                                                │
│  └── RecordChannelAffinity() 记录渠道ID到缓存                │
└─────────────────────────────────────────────────────────────┘
```

---

### 三、关键组件

#### 1. 配置层 (`setting/operation_setting/channel_affinity_setting.go`)

| 配置项 | 说明 |
|--------|------|
| `Enabled` | 功能总开关 |
| `SwitchOnSuccess` | 成功后是否切换到该渠道 |
| `MaxEntries` | 缓存最大条目数（默认10万） |
| `DefaultTTLSeconds` | 默认缓存过期时间（默认1小时） |
| `Rules` | 亲和性规则数组 |

**规则结构**：
```go
type ChannelAffinityRule struct {
    Name             string                     // 规则名称
    ModelRegex       []string                   // 模型匹配正则
    PathRegex        []string                   // 请求路径匹配正则
    UserAgentInclude []string                   // User-Agent包含匹配
    KeySources       []ChannelAffinityKeySource // Key来源配置
    TTLSeconds       int                        // 该规则的TTL
    ParamOverrideTemplate map[string]interface{} // 参数覆盖模板
    SkipRetryOnFailure bool                     // 失败后是否跳过重试
}
```

#### 2. 服务层 (`service/channel_affinity.go`)

**核心流程**：

```
GetPreferredChannelByAffinity()
    │
    ├── 检查功能是否启用
    ├── 遍历所有规则进行匹配
    │       ├── 模型正则匹配
    │       ├── 路径正则匹配
    │       ├── User-Agent匹配
    │       └── 全部匹配成功 → 继续
    │
    ├── 提取亲和Key（按KeySources配置）
    │       ├── context_int: 从Gin Context取int值
    │       ├── context_string: 从Gin Context取string值
    │       └── gjson: 从JSON Body提取（支持gjson path语法）
    │
    ├── 生成缓存Key（组合多个因子）
    │       ├── Key来源值
    │       ├── 分组名（可选）
    │       ├── 规则名（可选）
    │       └── 请求路径（可选）
    │
    └── 查询缓存 → 返回渠道ID
```

#### 3. 缓存层

使用 `pkg/cachex` 包实现，支持：
- **命名空间隔离**: `new-api:channel_affinity:v1`
- **TTL过期**: 每个条目独立设置过期时间
- **统计信息**: 命中率、条目数等

---

### 四、预设模板

系统内置两个常用模板：

| 模板 | 用途 | 模型匹配 | Key来源 | Header透传 |
|------|------|----------|---------|------------|
| **Codex CLI** | OpenAI Codex命令行工具 | `^gpt-.*$` | `prompt_cache_key` | `x-codex-*` 系列 |
| **Claude CLI** | Claude命令行工具 | `^claude-.*$` | `metadata.user_id` | `anthropic-*` 系列 |

---

### 五、特殊功能

#### 1. 失败后跳过重试 (`SkipRetryOnFailure`)

当某条规则配置了 `skip_retry_on_failure: true` 时：
- 如果亲和渠道调用失败
- 系统不会尝试其他渠道重试
- 直接返回错误给客户端

适用于需要严格保持上下文一致性的场景。

#### 2. 参数覆盖模板 (`ParamOverrideTemplate`)

命中规则后，可自动合并参数到请求体。例如Codex CLI模板会透传这些Header：
```json
{
  "extra_headers": {
    "x-codex-...": "{{headers.x-codex-...}}"
  }
}
```

#### 3. 使用缓存统计

记录每个亲和Key的实际Token使用量，用于：
- 监控缓存命中率
- 分析哪些Key产生了多少流量

---

### 六、API接口

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/option/channel_affinity_cache` | 获取缓存统计 |
| DELETE | `/api/option/channel_affinity_cache` | 清空缓存 |
| GET | `/api/log/channel_affinity_usage_cache` | 获取使用统计 |

---

### 七、前端界面

位于 `web/src/pages/Setting/Operation/SettingsChannelAffinity.jsx`，提供：
- 可视化规则编辑器
- JSON模式编辑
- 模板快速应用（Codex CLI / Claude CLI）
- 缓存统计面板
- 一键清空缓存

---

### 八、总结

渠道亲和性是一个**高级路由功能**，通过缓存"请求Key → 渠道ID"的映射关系，实现：
1. **粘滞路由** - 相同Key的请求优先使用同一渠道
2. **Header透传** - 支持CLI工具的特殊Header需求
3. **灵活匹配** - 基于模型、路径、User-Agent多维度匹配
4. **可观测性** - 完整的缓存统计和日志记录