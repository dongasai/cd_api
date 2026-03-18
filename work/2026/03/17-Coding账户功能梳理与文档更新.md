# Coding账户功能梳理与文档更新

**时间**: 2026-03-17
**任务**: 梳理 Coding 账户功能，更新 docs/10-渠道CodingPlan支持.md

## 工作内容

### 1. 代码分析

阅读并分析了以下核心代码文件：

#### 模型层
- `CodingAccount.php` - Coding账户模型，包含平台类型、状态、凭证管理
- `Channel.php` - 渠道模型，支持绑定CodingAccount

#### 驱动系统
- `CodingStatusDriver.php` - 驱动接口定义
- `AbstractCodingStatusDriver.php` - 抽象基类，提供公共配额管理逻辑
- `TokenCodingStatusDriver.php` - Token计费驱动
- `RequestCodingStatusDriver.php` - 请求次数计费驱动
- `PromptCodingStatusDriver.php` - Prompt次数计费驱动
- `GLMCodingStatusDriver.php` - 智谱GLM官方API驱动
- `SlidingRequestCodingStatusDriver.php` - 滑动窗口请求计费驱动
- `SlidingTokenCodingStatusDriver.php` - 滑动窗口Token计费驱动
- `Request5ZMCodingStatusDriver.php` - 三维度请求计费驱动(5h/周/月)

#### 服务层
- `CodingStatusDriverManager.php` - 驱动管理器
- `ChannelCodingStatusService.php` - 渠道状态调控服务

#### 命令
- `SyncCodingQuota.php` - 同步Coding配额命令
- `CheckChannelCodingStatus.php` - 检查渠道状态命令
- `AutoReopenCodingAccounts.php` - 自动重新开启账户命令

### 2. 功能梳理

#### 已实现的驱动类型

| 驱动名称 | 计费模式 | 适用平台 |
|----------|----------|----------|
| TokenCodingStatus | Token计费 | 按Token量计费的平台 |
| RequestCodingStatus | 请求次数计费 | 阿里云百炼、火山方舟等 |
| PromptCodingStatus | Prompt次数计费 | 智谱GLM、MiniMax等 |
| GLMCodingStatus | 智谱GLM官方API | 智谱GLM |
| SlidingRequestCodingStatus | 滑动窗口请求计费 | 无问芯穹等 |
| SlidingTokenCodingStatus | 滑动窗口Token计费 | 无问芯穹等 |
| Request5ZMCodingStatus | 三维度请求计费 | 特殊平台 |

#### 支持的平台

- aliyun - 阿里云百炼
- volcano - 火山方舟
- zhipu - 智谱GLM
- github - GitHub Models
- cursor - Cursor编辑器
- infini - 无问芯穹
- custom - 自定义平台

#### 数据库表结构

| 表名 | 用途 |
|------|------|
| coding_accounts | Coding账户主表 |
| coding_quota_usage | 通用配额使用表(固定周期) |
| coding_usage_logs | 使用日志表 |
| coding_status_logs | 状态变更日志表 |
| coding_sliding_windows | 滑动窗口表 |
| coding_sliding_usage_logs | 滑动窗口使用日志表 |
| coding_5zm_quotas | 5ZM驱动专用配额表 |
| coding_5zm_usage_logs | 5ZM驱动使用日志表 |
| coding_5zm_status_logs | 5ZM驱动状态日志表 |

### 3. 文档更新

根据代码实际实现情况，重新编写了文档 `docs/10-渠道CodingPlan支持.md`：

#### 主要更新内容

1. **驱动详解** - 添加了所有7种驱动的详细说明，包括配置示例和默认配置
2. **平台支持** - 更新了支持的平台列表和推荐驱动映射
3. **数据库设计** - 按实际表结构更新，包括固定周期表和滑动窗口表
4. **渠道调控** - 更新了状态流转逻辑和服务方法
5. **定时任务** - 更新了命令列表和参数说明
6. **最佳实践** - 添加了驱动选择建议、阈值设置建议
7. **代码位置参考** - 添加了所有核心文件的路径引用

#### 关键发现

1. **滑动窗口驱动** - 与固定周期驱动不同，统计过去N时间窗口内的使用量
2. **5ZM驱动** - 特殊的三维度限制，同时监控5h/周/月三个周期
3. **检查间隔差异** - 固定周期驱动默认60秒，滑动窗口驱动默认300秒
4. **自动重新开启** - 支持 auto_reopen_hours 配置自动恢复禁用账户

## 技术要点

### 驱动架构

```
CodingStatusDriver (接口)
    │
    └── AbstractCodingStatusDriver (抽象基类)
            │
            ├── TokenCodingStatusDriver
            ├── RequestCodingStatusDriver
            ├── PromptCodingStatusDriver
            ├── GLMCodingStatusDriver
            ├── SlidingRequestCodingStatusDriver
            ├── SlidingTokenCodingStatusDriver
            └── Request5ZMCodingStatusDriver
```

### 状态流转

```
active → warning → critical → exhausted/expired/suspended/error
                                          ↓
                                     disabled (渠道禁用)
                                          ↓
                              shouldEnable() → active (恢复)
```

### 配额周期类型

- `5h` - 5小时周期，支持偏移配置
- `daily` - 日周期
- `weekly` - 周周期
- `monthly` - 月周期，支持指定重置日期

## 后续建议

1. **测试覆盖** - 补充各驱动的单元测试和集成测试
2. **监控完善** - 添加Prometheus指标采集
3. **告警机制** - 配置配额使用率告警
4. **文档同步** - 保持文档与代码同步更新