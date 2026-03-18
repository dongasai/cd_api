# 渠道 CodingPlan Status 支持

## 一、功能概述

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                           渠道费用管理模式扩展 (V2)                                   │
└─────────────────────────────────────────────────────────────────────────────────────┘

核心定位:
┌─────────────────────────────────────────────────────────────────────────────────────┐
│ 为上游渠道的费用管理增加 'Coding Plan 模式' 支持                                      │
│                                                                                     │
│ 本质: 渠道绑定Coding账户，Coding账户绑定CodingStatus驱动，驱动管理配额并调控渠道状态   │
└─────────────────────────────────────────────────────────────────────────────────────┘

新架构关系:
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                                                                     │
│   ┌─────────┐      ┌─────────────┐      ┌─────────────────┐      ┌─────────────┐   │
│   │  渠道   │◀────▶│ Coding账户  │◀────▶│ CodingStatus驱动 │◀────▶│  配额管理    │   │
│   │ Channel │      │   Account   │      │    Driver       │      │             │   │
│   └────┬────┘      └─────────────┘      └─────────────────┘      └─────────────┘   │
│        │                                                                            │
│        │ 状态调控                                                                   │
│        ▼                                                                            │
│   ┌─────────┐                                                                       │
│   │ 开启/关闭 │                                                                      │
│   └─────────┘                                                                       │
│                                                                                     │
└─────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 二、核心概念

### 2.1 名词解释

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              核心概念定义                                             │
└─────────────────────────────────────────────────────────────────────────────────────┘

Coding 账户 (CodingAccount):
├── 代表一个 Coding 套餐账户 (如阿里云百炼、火山方舟、智谱GLM等)
├── 包含平台凭证 (API Key、Secret 等)
├── 绑定一个特定的计费驱动
└── 拥有独立的配额管理逻辑

CodingStatus 驱动 (CodingStatusDriver):
├── 负责配额管理的核心组件
├── 实现不同平台的计费逻辑差异
├── 提供统一的状态判断接口
└── 支持多种计费模式: Token/Request/Prompt/滑动窗口等

配额周期:
├── 5小时周期 (5h): 阿里云百炼、火山方舟、智谱GLM等平台常用
├── 日周期 (daily): 每日重置配额
├── 周周期 (weekly): 每周重置配额
└── 月周期 (monthly): 每月重置配额

阈值状态:
├── active: 正常 - 配额充足
├── warning: 警告 - 配额使用超过警告阈值 (默认80%)
├── critical: 临界 - 配额使用超过临界阈值 (默认90%)
├── exhausted: 耗尽 - 配额已耗尽
├── expired: 过期 - 账户已过期
├── suspended: 暂停 - 账户已暂停
└── error: 错误 - 账户状态异常
```

---

## 三、架构设计

### 3.1 整体架构

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              系统架构图                                               │
└─────────────────────────────────────────────────────────────────────────────────────┘

                              ┌─────────────────┐
                              │   用户请求       │
                              └────────┬────────┘
                                       │
                                       ▼
                              ┌─────────────────┐
                              │   Channel 渠道   │
                              │  (上游API代理)   │
                              └────────┬────────┘
                                       │
                                       │ belongsTo
                                       ▼
                              ┌─────────────────┐
                              │  CodingAccount  │
                              │   Coding账户     │
                              │                 │
                              │ - name          │  账户名称
                              │ - platform      │  平台类型
                              │ - driver_class  │  驱动类名
                              │ - credentials   │  账户凭证 (加密存储)
                              │ - config        │  配额配置
                              │ - status        │  账户状态
                              └────────┬────────┘
                                       │
                                       │ uses
                                       ▼
                    ┌──────────────────────────────────┐
                    │      CodingStatusDriver          │
                    │         (驱动接口)                │
                    │                                  │
                    │  + getStatus(): Status           │
                    │  + checkQuota(): QuotaResult     │
                    │  + consume(): void               │
                    │  + shouldDisable(): bool         │
                    │  + shouldEnable(): bool          │
                    │  + sync(): void                  │
                    │  + getQuotaInfo(): array         │
                    │  + getCheckInterval(): int       │
                    └────────────┬─────────────────────┘
                                 │ implements
                    ┌────────────┼────────────┬─────────────────┬─────────────────┐
                    ▼            ▼            ▼                 ▼                 ▼
         ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐
         │   Token     │ │  Request    │ │   Prompt    │ │    GLM      │ │  Sliding    │
         │   Coding    │ │   Coding    │ │   Coding    │ │   Coding    │ │   Request   │
         │   Status    │ │   Status    │ │   Status    │ │   Status    │ │   Status    │
         └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘

 状态调控流程:
 ┌─────────────────────────────────────────────────────────────────────────────────────┐
 │                                                                                     │
 │  1. 请求到达渠道                                                                     │
 │       │                                                                             │
 │       ▼                                                                             │
 │  2. 获取渠道绑定的CodingAccount                                                      │
 │       │                                                                             │
 │       ▼                                                                             │
 │  3. 根据 account.driver_class 实例化对应驱动                                         │
 │       │                                                                             │
 │       ▼                                                                             │
 │  4. 调用 driver.checkQuota() 检查配额                                                │
 │       │                                                                             │
 │       ├─── 配额充足 → 允许请求 → 执行请求 → driver.consume() 更新配额                │
 │       │                                                                             │
 │       └─── 配额不足 → driver.shouldDisable() → 触发渠道禁用                          │
 │                                                                                     │
 │  5. 定时任务调用 driver.sync() 同步配额状态                                          │
 │       │                                                                             │
 │       └─── 配额重置 → driver.shouldEnable() → 触发渠道启用                           │
 │                                                                                     │
 └─────────────────────────────────────────────────────────────────────────────────────┘
```

### 3.2 CodingStatus驱动接口

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                         CodingStatusDriver 接口定义                                  │
└─────────────────────────────────────────────────────────────────────────────────────┘

接口方法:
┌─────────────────────────┬──────────────────────────────────────────────────────────┐
│ 方法                    │ 说明                                                     │
├─────────────────────────┼──────────────────────────────────────────────────────────┤
│ getName()               │ 获取驱动名称                                             │
│ getDescription()        │ 获取驱动描述                                             │
│ getSupportedMetrics()   │ 获取支持的计费维度                                       │
│ setAccount(account)     │ 设置Coding账户                                           │
│ getStatus()             │ 获取当前配额状态                                         │
│ checkQuota(context)     │ 检查配额是否充足                                         │
│ consume(usage)          │ 消耗配额                                                 │
│ shouldDisable()         │ 判断是否应该禁用渠道                                     │
│ shouldEnable()          │ 判断是否应该启用渠道                                     │
│ sync()                  │ 同步配额信息                                             │
│ getQuotaInfo()          │ 获取配额详细信息                                         │
│ getPeriodInfo()         │ 获取周期信息                                             │
│ validateCredentials()   │ 验证账户凭证                                             │
│ getConfigFields()       │ 获取配置表单字段                                         │
│ getDefaultQuotaConfig() │ 获取默认配额配置                                         │
│ getCheckInterval()      │ 获取检查间隔(秒)                                         │
└─────────────────────────┴──────────────────────────────────────────────────────────┘

状态枚举:
┌─────────────────┬──────────────────────────────────────────────────────────────────┐
│ 状态            │ 说明                                                             │
├─────────────────┼──────────────────────────────────────────────────────────────────┤
│ active          │ 正常 - 配额充足，正常使用                                        │
│ warning         │ 警告 - 配额使用超过警告阈值 (默认80%)                            │
│ critical        │ 临界 - 配额使用超过临界阈值 (默认90%)                            │
│ exhausted       │ 耗尽 - 配额已耗尽                                                │
│ expired         │ 过期 - 账户已过期                                                │
│ suspended       │ 暂停 - 账户已暂停                                                │
│ error           │ 错误 - 账户状态异常                                              │
└─────────────────┴──────────────────────────────────────────────────────────────────┘
```

---

## 四、数据库设计

### 4.1 Coding账户表 (coding_accounts)

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned | 主键 |
| name | varchar(255) | 账户名称 |
| platform | varchar(50) | 平台类型: aliyun/volcano/zhipu/github/cursor/infini/custom |
| driver_class | varchar(255) | 驱动类名 |
| credentials | json | 平台凭证: {api_key, api_secret, access_token} |
| status | enum | 账户状态: active/warning/critical/exhausted/expired/suspended/error |
| config | json | 驱动特定配置 |
| last_sync_at | timestamp | 最后同步时间 |
| sync_error | text | 同步错误信息 |
| sync_error_count | int unsigned | 连续同步错误次数 |
| expires_at | timestamp | 账户过期时间 |
| disabled_at | timestamp | 禁用时间 |

### 4.2 渠道关联字段 (channels表)

| 字段 | 类型 | 说明 |
|------|------|------|
| coding_account_id | bigint unsigned | 关联Coding账户ID |
| coding_status_override | json | 渠道级别的Coding状态覆盖配置 |
| coding_last_check_at | timestamp | 最后检查时间 |

渠道Coding状态覆盖配置示例:
```json
{
    "auto_disable": true,
    "auto_enable": true,
    "disable_threshold": 0.95,
    "warning_threshold": 0.80,
    "priority": 1,
    "fallback_channel_id": null
}
```

### 4.3 配额使用表

#### 4.3.1 通用配额使用表 (coding_quota_usage)

用于固定周期驱动(Token/Request/Prompt/GLM):

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned | 主键 |
| account_id | bigint unsigned | Coding账户ID |
| metric | varchar(50) | 指标名称: prompts, tokens, requests等 |
| period_key | varchar(50) | 周期标识: Y-m-d, Y-W, Y-m等 |
| period_type | varchar(20) | 周期类型: 5h, daily, weekly, monthly |
| used | bigint unsigned | 已使用量 |
| period_starts_at | timestamp | 周期开始时间 |
| period_ends_at | timestamp | 周期结束时间 |

#### 4.3.2 滑动窗口表 (coding_sliding_windows)

用于滑动窗口驱动:

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned | 主键 |
| account_id | bigint unsigned | Coding账户ID |
| window_type | varchar(20) | 窗口类型: 5h/1d/7d/30d |
| window_seconds | int unsigned | 窗口时长(秒) |
| started_at | timestamp | 窗口开始时间 |
| ends_at | timestamp | 窗口结束时间 |
| status | varchar(20) | 状态: active/expired |

#### 4.3.3 5ZM驱动专用表 (coding_5zm_quotas)

用于三维度请求计费驱动:

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned | 主键 |
| account_id | bigint unsigned | Coding账户ID |
| limit_5h | int unsigned | 5小时周期限额 |
| limit_weekly | int unsigned | 周限额 |
| limit_monthly | int unsigned | 月限额 |
| used_5h | int unsigned | 5小时周期已用 |
| used_weekly | int unsigned | 周已用 |
| used_monthly | int unsigned | 月已用 |
| period_5h | varchar(20) | 当前5小时周期标识 |
| period_weekly | varchar(10) | 当前周周期标识 |
| period_monthly | varchar(7) | 当前月周期标识 |
| threshold_warning | decimal(4,3) | 警告阈值 |
| threshold_critical | decimal(4,3) | 临界阈值 |
| threshold_disable | decimal(4,3) | 禁用阈值 |
| period_offset | smallint unsigned | 5小时周期偏移量(秒) |
| reset_day | tinyint unsigned | 月重置日期 |

### 4.4 使用日志表

#### 4.4.1 coding_usage_logs
通用使用日志表，记录requests/tokens/prompts/credits等

#### 4.4.2 coding_sliding_usage_logs
滑动窗口使用日志表

#### 4.4.3 coding_5zm_usage_logs
5ZM驱动使用日志表，记录三个维度的配额变化

### 4.5 状态变更日志表 (coding_status_logs)

记录账户和渠道状态变更历史

---

## 五、CodingStatus驱动详解

### 5.1 TokenCodingStatus 驱动

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                         TokenCodingStatus 驱动                                       │
│                        (按Token计费模式)                                             │
└─────────────────────────────────────────────────────────────────────────────────────┘

适用场景:
├── 按输入/输出Token计费的平台
├── 需要精确追踪Token消耗的场景
└── 支持多种周期: 日/周/月

配额维度:
┌─────────────────┬──────────────────────────────────────────────────────────────────┐
│ 维度            │ 说明                                                             │
├─────────────────┼──────────────────────────────────────────────────────────────────┤
│ tokens_input    │ 输入Token限制                                                    │
│ tokens_output   │ 输出Token限制                                                    │
│ tokens_total    │ 总Token限制                                                      │
│ credits         │ 积分限制 (按Token换算)                                           │
└─────────────────┴──────────────────────────────────────────────────────────────────┘

配置示例:
{
    "limits": {
        "tokens_input": 10000000,
        "tokens_output": 5000000,
        "tokens_total": 15000000
    },
    "thresholds": {
        "warning": 0.80,
        "critical": 0.90,
        "disable": 0.95
    },
    "cycle": "monthly",
    "reset_day": 1
}

默认配置:
- tokens_input: 10,000,000
- tokens_output: 5,000,000
- tokens_total: 15,000,000
- cycle: monthly
```

### 5.2 RequestCodingStatus 驱动

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                        RequestCodingStatus 驱动                                      │
│                       (按请求次数计费模式)                                           │
└─────────────────────────────────────────────────────────────────────────────────────┘

适用场景:
├── 按API请求次数计费的平台 (如阿里云百炼、火山方舟)
├── 5小时周期重置配额的场景
└── 需要追踪请求次数而非Token的场景

配额维度:
┌─────────────────┬──────────────────────────────────────────────────────────────────┐
│ 维度            │ 说明                                                             │
├─────────────────┼──────────────────────────────────────────────────────────────────┤
│ requests        │ 请求次数限制 (月度/周度周期)                                      │
│ requests_per_5h │ 5小时周期请求限制 (百炼/火山特有)                                │
└─────────────────┴──────────────────────────────────────────────────────────────────┘

配置示例 (阿里云百炼):
{
    "limits": {
        "requests_per_5h": 1200
    },
    "thresholds": {
        "warning": 0.80,
        "critical": 0.90,
        "disable": 0.95
    },
    "cycle": "5h",
    "period_offset": 0
}

默认配置:
- requests_per_5h: 1200
- cycle: 5h
- check_interval: 60秒
```

### 5.3 PromptCodingStatus 驱动

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                        PromptCodingStatus 驱动                                       │
│                       (按Prompt次数计费模式)                                         │
└─────────────────────────────────────────────────────────────────────────────────────┘

适用场景:
├── 按用户提问次数(Prompt)计费的平台
├── 智谱GLM、MiniMax等平台
└── 每次Prompt触发多次模型调用的场景

配额维度:
┌─────────────────┬──────────────────────────────────────────────────────────────────┐
│ 维度            │ 说明                                                             │
├─────────────────┼──────────────────────────────────────────────────────────────────┤
│ prompts         │ Prompt次数限制 (月度/周度周期)                                    │
│ prompts_per_5h  │ 5小时周期Prompt限制                                              │
│ prompts_per_day │ 日周期Prompt限制                                                 │
└─────────────────┴──────────────────────────────────────────────────────────────────┘

配置示例:
{
    "limits": {
        "prompts_per_5h": 80
    },
    "thresholds": {
        "warning": 0.75,
        "critical": 0.85,
        "disable": 0.90
    },
    "cycle": "5h"
}

默认配置:
- prompts_per_5h: 80
- cycle: 5h
```

### 5.4 GLMCodingStatus 驱动

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                          GLMCodingStatus 驱动                                        │
│                    (智谱GLM官方API获取状态)                                          │
└─────────────────────────────────────────────────────────────────────────────────────┘

设计特点:
├── 通过智谱官方API获取实时配额状态
├── 支持GLM-4/GLM-3等不同模型的配额管理
├── 支持5小时周期重置
└── 支持Prompt次数和Token双重计量

配额维度:
┌─────────────────┬──────────────────────────────────────────────────────────────────┐
│ 维度            │ 说明                                                             │
├─────────────────┼──────────────────────────────────────────────────────────────────┤
│ prompts         │ Prompt次数 (官网显示值)                                          │
│ prompts_per_5h  │ 5小时周期Prompt限制                                              │
│ tokens          │ 实际Token消耗 (底层计费)                                         │
│ balance         │ 账户余额 (如适用)                                                │
└─────────────────┴──────────────────────────────────────────────────────────────────┘

同步策略:
1. 定时同步: 每5分钟调用官方API获取最新配额
2. 实时同步: 每次请求后异步推送使用量
3. 异常处理: 同步失败时使用本地缓存，超过阈值告警

配置示例:
{
    "limits": {
        "prompts_per_5h": 80,
        "tokens": 1000000
    },
    "thresholds": {
        "warning": 0.75,
        "critical": 0.85,
        "disable": 0.90
    },
    "cycle": "5h",
    "api_config": {
        "sync_interval": 300,
        "timeout": 10000,
        "retry_attempts": 3
    }
}

默认配置:
- prompts_per_5h: 80
- tokens: 1,000,000
- cycle: 5h
```

### 5.5 SlidingRequestCodingStatus 驱动

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                     SlidingRequestCodingStatus 驱动                                  │
│                       (滑动窗口请求计费模式)                                         │
└─────────────────────────────────────────────────────────────────────────────────────┘

设计特点:
├── 统计过去N小时/天内的请求次数
├── 不依赖固定周期重置
├── 配额持续滑动计算
└── 适用于无问芯穹等平台

窗口类型:
┌─────────────────┬──────────────────────────────────────────────────────────────────┐
│ 窗口类型        │ 说明                                                             │
├─────────────────┼──────────────────────────────────────────────────────────────────┤
│ 5h              │ 统计过去5小时的请求次数                                          │
│ 1d              │ 统计过去1天的请求次数                                            │
│ 7d              │ 统计过去7天的请求次数                                            │
│ 30d             │ 统计过去30天的请求次数                                           │
└─────────────────┴──────────────────────────────────────────────────────────────────┘

配置示例:
{
    "limits": {
        "requests": 1200
    },
    "thresholds": {
        "warning": 0.80,
        "critical": 0.90,
        "disable": 0.95
    },
    "window_type": "5h"
}

默认配置:
- requests: 1200
- window_type: 5h
- check_interval: 300秒 (滑动窗口驱动推荐较长检查间隔)
```

### 5.6 SlidingTokenCodingStatus 驱动

```
滑动窗口Token计费驱动，与SlidingRequestCodingStatus类似，但统计Token消耗
支持 tokens_input/tokens_output/tokens_total 维度
```

### 5.7 Request5ZMCodingStatus 驱动

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                       Request5ZMCodingStatus 驱动                                    │
│                    (三维度请求计费模式)                                              │
└─────────────────────────────────────────────────────────────────────────────────────┘

设计特点:
├── 同时监控三个周期的配额使用
├── 5小时/周/月三个维度并行限制
├── 任意维度超限都会触发状态变化
└── 配额数据存储在专用表 coding_5zm_quotas

配额维度:
┌─────────────────┬──────────────────────────────────────────────────────────────────┐
│ 维度            │ 说明                                                             │
├─────────────────┼──────────────────────────────────────────────────────────────────┤
│ requests_5h     │ 5小时周期请求限制                                                │
│ requests_weekly │ 周请求限制                                                       │
│ requests_monthly│ 月请求限制                                                       │
└─────────────────┴──────────────────────────────────────────────────────────────────┘

配置示例:
{
    "limits": {
        "requests_5h": 300,
        "requests_weekly": 1000,
        "requests_monthly": 5000
    },
    "thresholds": {
        "warning": 0.80,
        "critical": 0.90,
        "disable": 0.95
    },
    "period_offset": 0,
    "reset_day": 1
}

默认配置:
- requests_5h: 300
- requests_weekly: 1000
- requests_monthly: 5000
```

---

## 六、平台支持与驱动推荐

### 6.1 支持的平台

| 平台 | 标识 | 说明 |
|------|------|------|
| 阿里云百炼 | aliyun | 阿里云大模型平台 |
| 火山方舟 | volcano | 字节跳动大模型平台 |
| 智谱GLM | zhipu | 智谱AI官方API |
| 无问芯穹 | infini | 无问芯穹大模型平台 |
| 自定义 | custom | 自定义平台 |

### 6.2 平台推荐驱动

| 平台 | 推荐驱动 | 说明 |
|------|----------|------|
| 阿里云百炼 | Request5ZMCodingStatus, SlidingRequestCodingStatus | 三维度请求限制(5h/周/月)或滑动窗口 |
| 火山方舟 | Request5ZMCodingStatus, SlidingRequestCodingStatus | 三维度请求限制(5h/周/月)或滑动窗口 |
| 智谱GLM | GLMCodingStatus, PromptCodingStatus | 智谱GLM官方API驱动，实时获取配额状态 |
| 无问芯穹 | Request5ZMCodingStatus | 三维度请求限制(5h/7天/月) |
| 自定义 | TokenCodingStatus, RequestCodingStatus, SlidingRequestCodingStatus | 按需选择 |

---

## 七、渠道状态调控

### 7.1 状态流转

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              渠道状态流转图                                          │
└─────────────────────────────────────────────────────────────────────────────────────┘

状态机:
                                    配额重置/账户恢复
    ┌─────────────┐ ◀─────────────────────────────────────────────────────┐
    │   active    │                                                      │
    │   (正常)    │                                                      │
    └──────┬──────┘                                                      │
           │                                                            │
           │ 驱动返回 shouldDisable() = true                             │
           ▼                                                            │
    ┌─────────────┐                                                      │
    │  disabled   │ ───── 自动禁用渠道 ────▶                             │
    │  (禁用)     │                                                      │
    └─────────────┘                                                      │
           │                                                            │
           │ 驱动 shouldEnable() = true                                  │
           └────────────────────────────────────────────────────────────┘

渠道状态与Coding账户状态关系:
┌─────────────────────────────────────────────────────────────────────┐
│                                                                     │
│  CodingAccount 状态      Channel 状态      说明                    │
│  ─────────────────────────────────────────────────────────────     │
│  active                  active           正常服务                 │
│  warning                 active           正常服务+通知            │
│  critical                active           正常服务+通知            │
│  exhausted               disabled         配额耗尽，渠道禁用       │
│  expired                 disabled         账户过期，渠道禁用       │
│  suspended               disabled         账户暂停，渠道禁用       │
│  error                   disabled         账户异常，渠道禁用       │
│                                                                     │
│  渠道可覆盖配置:                                                     │
│  - 当 channel.coding_status_override.auto_disable = false 时       │
│    即使账户 exhausted，渠道也不会自动禁用                           │
│  - 当 channel.coding_status_override.auto_enable = false 时        │
│    即使账户恢复，渠道也不会自动启用                                 │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### 7.2 渠道调控服务 (ChannelCodingStatusService)

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                         渠道Coding状态调控服务                                       │
└─────────────────────────────────────────────────────────────────────────────────────┘

核心职责:
├── 检查渠道Coding状态并触发调控
├── 根据驱动返回结果启用/禁用渠道
├── 记录状态变更日志
└── 发送状态变更通知

主要方法:
┌─────────────────────────┬──────────────────────────────────────────────────────────┐
│ 方法                    │ 说明                                                     │
├─────────────────────────┼──────────────────────────────────────────────────────────┤
│ checkAndUpdateChannel   │ 检查并更新渠道状态                                       │
│ disableChannel          │ 禁用渠道并记录日志                                       │
│ enableChannel           │ 启用渠道并记录日志                                       │
│ manualDisableChannel    │ 手动禁用渠道                                             │
│ manualEnableChannel     │ 手动启用渠道                                             │
│ checkRequestAllowed     │ 检查请求是否允许 (配额检查)                              │
│ recordUsage             │ 记录配额使用                                             │
│ getChannelCodingStatus  │ 获取渠道Coding状态                                       │
│ batchCheckAndUpdate     │ 批量检查并更新所有渠道状态                                │
└─────────────────────────┴──────────────────────────────────────────────────────────┘
```

### 7.3 请求检查中间件 (CheckCodingQuota)

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                         Coding配额检查中间件                                         │
└─────────────────────────────────────────────────────────────────────────────────────┘

职责:
├── 在请求处理前检查Coding配额
├── 配额不足时返回错误
├── 请求成功后记录配额使用
└── 异步更新配额消耗

处理流程:
1. 获取请求中的渠道信息
2. 检查渠道是否绑定Coding账户
3. 未绑定则直接放行
4. 构建检查上下文 (model, messages, 预估tokens)
5. 调用ChannelCodingStatusService.checkRequestAllowed()
6. 配额不足返回错误
7. 配额充足继续处理请求
8. 请求成功后异步记录使用
```

---

## 八、定时任务

### 8.1 任务列表

| 命令 | 执行频率 | 说明 |
|------|----------|------|
| `coding:sync-quota` | 每5分钟 | 同步所有Coding账户配额 |
| `coding:check-channels` | 每1分钟 | 检查渠道状态并触发调控 |
| `coding:auto-reopen` | 每小时 | 自动重新开启被禁用的账户 |
| `coding:cleanup-sliding-window` | 每日 | 清理过期滑动窗口数据 |
| `coding:reset-period` | 每分钟 | 检查并执行周期配额重置 |

### 8.2 任务详情

#### SyncCodingQuota (同步Coding配额)

```bash
# 同步所有活跃账户
php artisan coding:sync-quota

# 指定账户同步
php artisan coding:sync-quota --account=1

# 指定平台同步
php artisan coding:sync-quota --platform=aliyun
```

处理逻辑:
- 遍历所有 active/warning/critical 状态的 CodingAccount
- 调用对应驱动的 sync() 方法
- 更新账户 quota_cached 和 last_sync_at
- 处理同步失败 (记录错误，超过5次告警)
- 更新渠道状态缓存

#### CheckChannelCodingStatus (检查渠道状态)

```bash
# 检查所有渠道
php artisan coding:check-channels

# 指定渠道检查
php artisan coding:check-channels --channel=1
```

处理逻辑:
- 查询所有绑定 coding_account_id 的渠道
- 根据 check_interval 判断是否需要检查
- 调用 ChannelCodingStatusService::checkAndUpdateChannel()
- 根据驱动返回结果触发渠道启用/禁用
- 记录状态变更日志

#### AutoReopenCodingAccounts (自动重新开启)

```bash
php artisan coding:auto-reopen
```

处理逻辑:
- 查询所有 exhausted/suspended 状态的账户
- 检查 disabled_at + auto_reopen_hours 是否已过
- 符合条件则重新开启账户
- 同时启用关联渠道

---

## 九、后台管理

### 9.1 Coding账户管理 (CodingAccountController)

列表页功能:
- 显示账户名称、平台、状态、配额使用、最后同步时间
- 支持按平台、状态筛选
- 支持手动同步配额
- 支持编辑、删除账户

表单字段:
| 字段 | 说明 |
|------|------|
| name | 账户名称 |
| platform | 平台选择 |
| driver_class | 驱动类名 (根据平台自动填充) |
| credentials | 凭证信息 |
| config | 驱动特定配置 |

### 9.2 渠道绑定Coding账户

配置项:
| 配置项 | 说明 |
|--------|------|
| coding_account_id | 绑定的Coding账户 |
| auto_disable | 是否自动禁用 (默认true) |
| auto_enable | 是否自动启用 (默认true) |
| disable_threshold | 禁用阈值 (默认0.95) |
| warning_threshold | 警告阈值 (默认0.80) |
| fallback_channel_id | 备用渠道ID |

---

## 十、最佳实践

### 10.1 驱动选择建议

| 场景 | 推荐驱动 | 说明 |
|------|----------|------|
| 按Token计费 | TokenCodingStatus | 适用于按Token量计费的平台 |
| 按请求次数计费 | RequestCodingStatus | 适用于阿里云百炼、火山方舟等 |
| 按Prompt次数计费 | PromptCodingStatus | 适用于智谱GLM、MiniMax等 |
| 智谱GLM官方API | GLMCodingStatus | 需要官方API获取实时状态 |
| 滑动窗口计费 | SlidingRequestCodingStatus | 适用于无问芯穹等平台 |
| 多维度限制 | Request5ZMCodingStatus | 需要5h/周/月三维度并行限制 |

### 10.2 阈值设置建议

| 场景 | 建议配置 |
|------|----------|
| 生产环境 | warning: 0.80, critical: 0.90, disable: 0.95 |
| 开发环境 | warning: 0.90, critical: 0.95, disable: 1.00 |
| 严格限制 | warning: 0.70, critical: 0.85, disable: 0.90 |
| 宽松限制 | warning: 0.90, disable: 1.00, auto_disable: false |

### 10.3 多渠道配置策略

```
主渠道 ──▶ 高配额账户 (TokenCodingStatus)
   │
   ├── 配额耗尽 ──▶ 自动禁用
   │                    │
   │                    ▼
   │              备用渠道 ──▶ 中等配额账户 (RequestCodingStatus)
   │                 │
   │                 ├── 配额耗尽 ──▶ 自动禁用
   │                 │                    │
   │                 │                    ▼
   │                 │              兜底渠道 ──▶ PromptCodingStatus
   │                 │
   │                 └── 新周期 ──▶ 自动启用主渠道
   │
   └── 新周期 ──▶ 自动启用

配置要点:
1. 主渠道: auto_disable=true, auto_enable=true
2. 备用渠道: auto_disable=true, auto_enable=false (手动切回主渠道)
3. 兜底渠道: auto_disable=false (始终可用，但配额有限)
```

---

## 十一、监控与告警

### 11.1 监控指标

账户指标:
- `cdapi_coding_account_status{account_id, platform, status}` - 账户状态
- `cdapi_coding_quota_usage_percentage{account_id, metric}` - 配额使用率
- `cdapi_coding_quota_remaining{account_id, metric}` - 剩余配额
- `cdapi_coding_sync_success_total{platform}` - 同步成功次数
- `cdapi_coding_sync_failure_total{platform}` - 同步失败次数

渠道指标:
- `cdapi_channel_coding_status{channel_id, account_id}` - 渠道Coding状态
- `cdapi_channel_auto_disabled_total{account_id}` - 自动禁用次数
- `cdapi_channel_auto_enabled_total{account_id}` - 自动启用次数

### 11.2 告警规则

- 配额使用率超过80%: Warning
- 配额使用率超过90%: Critical
- 配额耗尽: Critical
- 同步失败: Warning
- 账户状态异常: Warning

---

## 十二、开发指南

### 12.1 添加新驱动

1. 创建驱动类，实现 `CodingStatusDriver` 接口
2. 继承 `AbstractCodingStatusDriver` 获得公共方法
3. 实现必要的抽象方法
4. 在 `CodingStatusDriverManager` 中注册驱动

示例:
```php
class MyCodingStatusDriver extends AbstractCodingStatusDriver
{
    public function getName(): string
    {
        return '自定义驱动';
    }

    public function getDescription(): string
    {
        return '自定义驱动描述';
    }

    public function getSupportedMetrics(): array
    {
        return ['custom_metric' => '自定义指标'];
    }

    // ... 实现其他方法
}
```

### 12.2 检查间隔配置

- 固定周期驱动 (Token/Request/Prompt/GLM): 默认60秒
- 滑动窗口驱动 (SlidingRequest/SlidingToken): 默认300秒
- 可通过配置覆盖默认值

---

## 十三、代码位置参考

| 组件 | 文件路径 |
|------|----------|
| CodingAccount 模型 | [app/Models/CodingAccount.php](laravel/app/Models/CodingAccount.php) |
| Channel 模型 | [app/Models/Channel.php](laravel/app/Models/Channel.php) |
| 驱动接口 | [app/Services/CodingStatus/Drivers/CodingStatusDriver.php](laravel/app/Services/CodingStatus/Drivers/CodingStatusDriver.php) |
| 抽象基类 | [app/Services/CodingStatus/Drivers/AbstractCodingStatusDriver.php](laravel/app/Services/CodingStatus/Drivers/AbstractCodingStatusDriver.php) |
| Token驱动 | [app/Services/CodingStatus/Drivers/TokenCodingStatusDriver.php](laravel/app/Services/CodingStatus/Drivers/TokenCodingStatusDriver.php) |
| Request驱动 | [app/Services/CodingStatus/Drivers/RequestCodingStatusDriver.php](laravel/app/Services/CodingStatus/Drivers/RequestCodingStatusDriver.php) |
| Prompt驱动 | [app/Services/CodingStatus/Drivers/PromptCodingStatusDriver.php](laravel/app/Services/CodingStatus/Drivers/PromptCodingStatusDriver.php) |
| GLM驱动 | [app/Services/CodingStatus/Drivers/GLMCodingStatusDriver.php](laravel/app/Services/CodingStatus/Drivers/GLMCodingStatusDriver.php) |
| 滑动窗口Request驱动 | [app/Services/CodingStatus/Drivers/SlidingRequestCodingStatusDriver.php](laravel/app/Services/CodingStatus/Drivers/SlidingRequestCodingStatusDriver.php) |
| 5ZM驱动 | [app/Services/CodingStatus/Drivers/Request5ZMCodingStatusDriver.php](laravel/app/Services/CodingStatus/Drivers/Request5ZMCodingStatusDriver.php) |
| 驱动管理器 | [app/Services/CodingStatus/CodingStatusDriverManager.php](laravel/app/Services/CodingStatus/CodingStatusDriverManager.php) |
| 渠道状态服务 | [app/Services/CodingStatus/ChannelCodingStatusService.php](laravel/app/Services/CodingStatus/ChannelCodingStatusService.php) |
| 同步命令 | [app/Console/Commands/SyncCodingQuota.php](laravel/app/Console/Commands/SyncCodingQuota.php) |
| 检查命令 | [app/Console/Commands/CheckChannelCodingStatus.php](laravel/app/Console/Commands/CheckChannelCodingStatus.php) |