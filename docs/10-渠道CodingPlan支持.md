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
│ 说明:                                                                               │
│ ├── 渠道 (Channel): 上游API代理通道                                                 │
│ ├── Coding账户 (CodingAccount): 存储Coding平台账户凭证、配置和驱动绑定               │
│ ├── CodingStatus驱动: 不同平台/计费模式采用不同驱动实现配额管理                       │
│ │   ├── TokenCodingStatus: 按Token计费模式                                          │
│ │   ├── RequestCodingStatus: 按请求次数计费模式                                     │
│ │   ├── PromptCodingStatus: 按Prompt次数计费模式                                    │
│ │   └── GLMCodingStatus: 智谱GLM官方API获取状态                                     │
│ └── 驱动负责: 配额追踪、状态判断、渠道调控                                            │
│                                                                                     │
└─────────────────────────────────────────────────────────────────────────────────────┘

使用场景:
├─── 场景1: 渠道使用通用Token计费
│    └── Coding账户绑定 TokenCodingStatus 驱动，按Token消耗管理配额
│
├─── 场景2: 渠道使用按请求次数计费 (如阿里云百炼)
│    └── Coding账户绑定 RequestCodingStatus 驱动，按API请求次数管理配额
│
├─── 场景3: 渠道使用按Prompt次数计费 (如智谱GLM)
│    └── Coding账户绑定 PromptCodingStatus 驱动，按Prompt次数管理配额
│
└─── 场景4: 渠道使用智谱GLM官方API
     └── Coding账户绑定 GLMCodingStatus 驱动，通过官方API获取实时状态

核心功能:
├─── Coding账户管理: 统一管理各平台Coding账户，绑定对应驱动
├─── 驱动绑定: 账户绑定对应计费模式的CodingStatus驱动
├─── 配额追踪: 各驱动独立实现配额追踪逻辑
├─── 状态管理: 驱动判断配额状态并触发渠道调控
├─── 自动禁用: 配额耗尽自动禁用渠道
├─── 自动启用: 配额重置后自动启用渠道
├─── 多周期支持: 各驱动支持不同重置周期
└─── 模型倍数: 支持不同模型消耗不同配额倍数

核心价值:
├─── 降低成本: 自动控制上游AI服务费用
├─── 防止超支: 配额耗尽自动停止服务
├─── 灵活扩展: 新增平台只需实现驱动接口
├─── 统一管控: 多渠道多平台统一管理
└─── 无缝管理: 自动化配额管理，无需人工干预
```

---

## 二、主流 AI Coding 工具计费参考

> 详细 Coding Plan 梳理请参阅: [11-CodingPlan梳理.md](./11-CodingPlan梳理.md)

### 2.1 国内外计费模式概览

```
┌─────────────────────────────────────────────────────────────────────┐
│                   国内外 AI Coding 计费模式对比                       │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────┬──────────────────┬──────────────────────────────────────┐
│ 维度        │ 国外工具         │ 国内工具                             │
├─────────────┼──────────────────┼──────────────────────────────────────┤
│ 计费单位    │ 积分/请求次数    │ 许可证数/用户数                     │
│             │ (Credits/Requests)│ (License/User)                      │
├─────────────┼──────────────────┼──────────────────────────────────────┤
│ 个人版价格  │ $10-20/月        │ 免费 - ¥59/月                       │
│             │ (¥70-140/月)     │                                      │
├─────────────┼──────────────────┼──────────────────────────────────────┤
│ 企业版价格  │ $19-39/用户/月   │ ¥79-159/用户/月                     │
│             │ (¥135-275/用户/月)│                                     │
├─────────────┼──────────────────┼──────────────────────────────────────┤
│ 模型差异化  │ 明确倍数机制     │ 较少公开                            │
│             │ (1x-10x)         │                                      │
├─────────────┼──────────────────┼──────────────────────────────────────┤
│ 额外购买    │ 按量付费         │ 较少支持                            │
│             │ ($0.04/request)  │                                      │
├─────────────┼──────────────────┼──────────────────────────────────────┤
│ 免费层      │ 有限配额         │ 多数免费或限免                      │
├─────────────┼──────────────────┼──────────────────────────────────────┤
│ 私有部署    │ Enterprise 定制  │ 企业版支持                          │
├─────────────┼──────────────────┼──────────────────────────────────────┤
│ 知识库      │ Enterprise 功能  │ 企业版标配                          │
└─────────────┴──────────────────┴──────────────────────────────────────┘

国内特色:
├─── 更注重企业知识库功能
├──   私有化部署需求更强
├──   价格更亲民 (免费策略普及)
├──   中文场景优化
└──   与云厂商生态深度绑定
```

### 2.2 计费单位换算说明

```
┌─────────────────────────────────────────────────────────────────────┐
│                   Coding Plan 计费单位换算                           │
└─────────────────────────────────────────────────────────────────────┘

三种计费单位对比:

1. API请求次数 (阿里云百炼、火山方舟、无问芯穹)
   ├─── 1次用户提问 = 后台触发 5-30次模型调用
   ├─── 1次调用 = 1次API请求
   └──   例: 1200次/5小时 ≈ 40-240次用户提问

2. Prompt次数 (MiniMax、智谱GLM)
   ├─── 1次Prompt = 1次用户提问
   ├─── 智谱GLM: 每次Prompt预计调用模型 15-20 次
   ├──   智谱GLM: 本质Token计费, 官网显示为估算值
   └──   MiniMax: 1次Prompt ≈ 1200-1600次API请求

3. Token计量 (Kimi)
   ├─── 按输入输出Token计费
   ├─── 仅统计未命中缓存的Token
   ├──   缓存命中率直接影响实际额度
   └──   官网展示的"Prompt次数"为估算值，实际按Token消耗

换算参考:
┌─────────────────────────────────────────────────────────────────────┐
│ 国内平台:                                                           │
│ ├─── 智谱GLM: 80次Prompt/5h ≈ 官网估算值 (实际Token计费)           │
│ ├──   MiniMax: 1次Prompt ≈ 1200-1600次API请求                      │
│ └──   百炼/火山: 1200次API请求/5h ≈ 40-240次用户提问               │
└─────────────────────────────────────────────────────────────────────┘
```

### 2.3 行业趋势总结

```
┌─────────────────────────────────────────────────────────────────────┐
│                    AI Coding 计费趋势                                │
└─────────────────────────────────────────────────────────────────────┘

核心计费模式:
├──   Token计量: Kimi, 智谱GLM (本质)
├──   请求次数制: 阿里云百炼、火山方舟
├──   Prompt次数制: 智谱GLM、MiniMax
└──   分层定价: Free → Pro → Teams → Enterprise

共同特点:
├──   免费层: 有限配额，吸引用户
├──   个人层: 月费 $10-20 / ¥29-49，适合个人开发者
├──   团队层: 月费 $30-40/用户，团队协作功能
├──   企业层: 定制价格，安全合规功能
├──   额外购买: 超出配额可按量付费
└──   周期重置: 月度/周度周期，配额定期重置

模型消耗倍数 (参考):
┌─────────────────────────┬────────────────────────────────────────────┐
│ 模型级别                │ 建议倍数                                   │
├─────────────────────────┼────────────────────────────────────────────┤
│ 基础模型 (GPT-3.5等)    │ 1x                                         │
│ 标准模型 (GPT-4o等)     │ 2x                                         │
│ 高级模型 (Claude 3.5)   │ 2-3x                                       │
│ 推理模型 (o1系列)       │ 5-10x                                      │
└─────────────────────────┴────────────────────────────────────────────┘
```

---

## 三、架构设计

### 3.1 核心架构关系

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              核心架构关系图                                          │
└─────────────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                                                                     │
│                              ┌─────────────────┐                                    │
│                              │   Channel 渠道   │                                    │
│                              │  (上游API代理)   │                                    │
│                              └────────┬────────┘                                    │
│                                       │                                             │
│                                       │ belongsTo                                     │
│                                       ▼                                             │
│                              ┌─────────────────┐                                    │
│                              │  CodingAccount  │                                    │
│                              │   Coding账户     │                                    │
│                              │                 │                                    │
│                              │ - name          │  账户名称                           │
│                              │ - platform      │  平台类型                           │
│                              │ - driver_class  │  驱动类名                           │
│                              │ - credentials   │  账户凭证 (加密存储)                │
│                              │ - quota_config  │  配额配置                           │
│                              │ - status        │  账户状态                           │
│                              └────────┬────────┘                                    │
│                                       │                                             │
│                                       │ uses                                        │
│                                       ▼                                             │
│                    ┌──────────────────────────────────┐                             │
│                    │      CodingStatusDriver          │                             │
│                    │         (驱动接口)                │                             │
│                    │                                  │                             │
│                    │  + getStatus(): Status           │                             │
│                    │  + checkQuota(): QuotaResult     │                             │
│                    │  + consume(): void               │                             │
│                    │  + shouldDisable(): bool         │                             │
│                    │  + shouldEnable(): bool          │                             │
│                    │  + sync(): void                  │                             │
│                    │  + getQuotaInfo(): array         │                             │
│                    └────────────┬─────────────────────┘                             │
│                                 │ implements                                        │
│                    ┌────────────┼────────────┬─────────────────┐                    │
│                    ▼            ▼            ▼                 ▼                    │
│         ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐            │
│         │   Token     │ │  Request    │ │   Prompt    │ │    GLM      │            │
│         │   Coding    │ │   Coding    │ │   Coding    │ │   Coding    │            │
│         │   Status    │ │   Status    │ │   Status    │ │   Status    │            │
│         └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘            │
│                                                                                     │
│ 状态调控流程:                                                                        │
│ ┌─────────────────────────────────────────────────────────────────────────────┐    │
│ │                                                                             │    │
│ │  1. 请求到达渠道                                                              │    │
│ │       │                                                                     │    │
│ │       ▼                                                                     │    │
│ │  2. 获取渠道绑定的CodingAccount                                              │    │
│ │       │                                                                     │    │
│ │       ▼                                                                     │    │
│ │  3. 根据 account.driver_class 实例化对应驱动                                  │    │
│ │       │                                                                     │    │
│ │       ▼                                                                     │    │
│ │  4. 调用 driver.checkQuota() 检查配额                                        │    │
│ │       │                                                                     │    │
│ │       ├─── 配额充足 → 允许请求 → 执行请求 → driver.consume() 更新配额         │    │
│ │       │                                                                     │    │
│ │       └─── 配额不足 → driver.shouldDisable() → 触发渠道禁用                   │    │
│ │                                                                             │    │
│ │  5. 定时任务调用 driver.sync() 同步配额状态                                   │    │
│ │       │                                                                     │    │
│ │       └─── 配额重置 → driver.shouldEnable() → 触发渠道启用                    │    │
│ │                                                                             │    │
│ └─────────────────────────────────────────────────────────────────────────────┘    │
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
│ getSupportedMetrics()   │ 获取支持的计费维度                                       │
│ getStatus()             │ 获取当前配额状态                                         │
│ checkQuota(context)     │ 检查配额是否充足                                         │
│ consume(usage)          │ 消耗配额                                                 │
│ shouldDisable()         │ 判断是否应该禁用渠道                                     │
│ shouldEnable()          │ 判断是否应该启用渠道                                     │
│ sync()                  │ 同步配额信息                                             │
│ getQuotaInfo()          │ 获取配额详细信息                                         │
│ getPeriodInfo()         │ 获取周期信息                                             │
│ validateCredentials()   │ 验证账户凭证                                             │
└─────────────────────────┴──────────────────────────────────────────────────────────┘

状态枚举:
┌─────────────────┬──────────────────────────────────────────────────────────────────┐
│ 状态            │ 说明                                                             │
├─────────────────┼──────────────────────────────────────────────────────────────────┤
│ active          │ 正常 - 配额充足，正常使用                                        │
│ warning         │ 警告 - 配额使用超过警告阈值                                      │
│ critical        │ 临界 - 配额使用超过临界阈值                                      │
│ exhausted       │ 耗尽 - 配额已耗尽                                                │
│ expired         │ 过期 - 账户已过期                                                │
│ suspended       │ 暂停 - 账户已暂停                                                │
│ error           │ 错误 - 账户状态异常                                              │
└─────────────────┴──────────────────────────────────────────────────────────────────┘
```

---

## 四、数据库设计

### 4.1 Coding账户表

```sql
-- Coding 账户表 (coding_accounts)
CREATE TABLE coding_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- 基本信息
    name VARCHAR(255) NOT NULL COMMENT '账户名称',
    platform VARCHAR(50) NOT NULL COMMENT '平台类型: aliyun/volcano/zhipu/github/cursor/custom',
    
    -- 驱动配置
    driver_class VARCHAR(255) NOT NULL COMMENT '驱动类名',
    
    -- 凭证信息 (加密存储)
    credentials JSON NOT NULL COMMENT '平台凭证: {api_key, api_secret, access_token}',
    
    -- 状态
    status ENUM('active', 'warning', 'critical', 'exhausted', 'expired', 'suspended', 'error') 
        DEFAULT 'active' COMMENT '账户状态',
    
    -- 配额配置 (各驱动通用)
    quota_config JSON NULL COMMENT '配额配置: {limits, thresholds, periods}',
    
    -- 配额缓存 (上次同步结果)
    quota_cached JSON NULL COMMENT '缓存的配额信息',
    
    -- 扩展配置
    config JSON NULL COMMENT '驱动特定配置',
    
    -- 同步相关
    last_sync_at TIMESTAMP NULL COMMENT '最后同步时间',
    sync_error TEXT NULL COMMENT '同步错误信息',
    sync_error_count INT UNSIGNED DEFAULT 0 COMMENT '连续同步错误次数',
    
    -- 时间
    expires_at TIMESTAMP NULL COMMENT '账户过期时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- 索引
    INDEX idx_platform (platform),
    INDEX idx_status (status),
    INDEX idx_driver (driver_class),
    INDEX idx_sync (last_sync_at),
    INDEX idx_expires (expires_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Coding 账户表';
```

### 4.2 渠道关联Coding账户

```sql
-- 渠道表增加Coding账户关联字段
ALTER TABLE channels 
ADD COLUMN coding_account_id BIGINT UNSIGNED NULL COMMENT '关联Coding账户ID',
ADD COLUMN coding_status_override JSON NULL COMMENT '渠道级别的Coding状态覆盖配置',
ADD INDEX idx_coding_account (coding_account_id);

-- 添加外键约束
ALTER TABLE channels
ADD FOREIGN KEY (coding_account_id) REFERENCES coding_accounts(id) ON DELETE SET NULL;

-- 渠道Coding状态覆盖配置示例:
{
    "auto_disable": true,           -- 是否自动禁用
    "auto_enable": true,            -- 是否自动启用
    "disable_threshold": 0.95,      -- 禁用阈值
    "warning_threshold": 0.80,      -- 警告阈值
    "priority": 1,                  -- 优先级
    "fallback_channel_id": 2        -- 备用渠道ID
}
```

### 4.3 配额使用记录表

```sql
-- 配额使用记录表 (coding_usage_logs)
CREATE TABLE coding_usage_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL COMMENT 'Coding账户ID',
    channel_id BIGINT UNSIGNED NULL COMMENT '渠道ID',
    request_id VARCHAR(64) NULL COMMENT '请求ID',
    
    -- 使用量
    requests INT UNSIGNED DEFAULT 1 COMMENT '请求次数',
    tokens_input INT UNSIGNED DEFAULT 0 COMMENT '输入Token数',
    tokens_output INT UNSIGNED DEFAULT 0 COMMENT '输出Token数',
    prompts INT UNSIGNED DEFAULT 0 COMMENT 'Prompt次数',
    credits DECIMAL(10, 4) DEFAULT 0 COMMENT '消耗积分',
    cost DECIMAL(10, 6) DEFAULT 0 COMMENT '金额成本',
    
    -- 模型信息
    model VARCHAR(100) NULL COMMENT '使用的模型',
    model_multiplier DECIMAL(5,2) DEFAULT 1.00 COMMENT '模型消耗倍数',
    
    -- 状态
    status ENUM('success', 'failed', 'throttled', 'rejected') DEFAULT 'success',
    
    -- 元数据
    metadata JSON NULL COMMENT '额外元数据',
    
    -- 时间
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- 索引和外键
    INDEX idx_account_created (account_id, created_at),
    INDEX idx_channel_created (channel_id, created_at),
    INDEX idx_request (request_id),
    INDEX idx_model_created (model, created_at),
    
    FOREIGN KEY (account_id) REFERENCES coding_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE SET NULL
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Coding配额使用记录表';

-- 配额状态变更日志表 (coding_status_logs)
CREATE TABLE coding_status_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL COMMENT 'Coding账户ID',
    channel_id BIGINT UNSIGNED NULL COMMENT '关联渠道ID',
    
    -- 状态变更
    from_status VARCHAR(20) NOT NULL COMMENT '原状态',
    to_status VARCHAR(20) NOT NULL COMMENT '新状态',
    reason VARCHAR(255) NULL COMMENT '变更原因',
    
    -- 配额信息 (变更时快照)
    quota_snapshot JSON NULL COMMENT '配额快照',
    
    -- 触发方式
    triggered_by ENUM('system', 'manual', 'api', 'sync') DEFAULT 'system' COMMENT '触发方式',
    user_id BIGINT UNSIGNED NULL COMMENT '操作用户 (手动触发时)',
    
    -- 时间
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- 索引和外键
    INDEX idx_account_created (account_id, created_at),
    INDEX idx_channel_created (channel_id, created_at),
    INDEX idx_status_change (from_status, to_status),
    
    FOREIGN KEY (account_id) REFERENCES coding_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE SET NULL
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Coding状态变更日志表';
```

### 4.4 模型消耗倍数表

```sql
-- 模型消耗倍数表 (model_multipliers)
CREATE TABLE model_multipliers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- 匹配规则
    platform VARCHAR(50) NULL COMMENT '适用平台 (null表示通用)',
    model_pattern VARCHAR(100) NOT NULL COMMENT '模型匹配模式 (支持通配符)',
    
    -- 倍数
    multiplier DECIMAL(5,2) NOT NULL DEFAULT 1.00 COMMENT '消耗倍数',
    
    -- 分类
    category VARCHAR(50) DEFAULT 'standard' COMMENT '模型分类: basic/standard/advanced/reasoning',
    description VARCHAR(255) NULL COMMENT '描述',
    
    -- 状态
    is_active BOOLEAN DEFAULT TRUE,
    priority INT UNSIGNED DEFAULT 0 COMMENT '优先级 (高优先匹配)',
    
    -- 时间
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- 索引
    INDEX idx_platform_pattern (platform, model_pattern),
    INDEX idx_category (category),
    INDEX idx_active (is_active)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='模型消耗倍数表';
```

---

## 五、CodingStatus驱动设计

### 5.1 TokenCodingStatus驱动

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                         TokenCodingStatus 驱动                                       │
│                        (按Token计费模式)                                             │
└─────────────────────────────────────────────────────────────────────────────────────┘

适用场景:
├── 按输入/输出Token计费的平台
├── 需要精确追踪Token消耗的场景
└── 支持多种周期: 日/周/月/无限制

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
        "tokens_total": 15000000,
        "credits": 15000
    },
    "thresholds": {
        "warning": 0.80,
        "critical": 0.90,
        "disable": 0.95
    },
    "cycle": "monthly",
    "reset_day": 1
}

状态判断逻辑:
1. 计算各维度使用率 (used / limit)
2. 取最大使用率作为整体使用率
3. 根据阈值判断状态: warning / critical / exhausted

配额消耗:
├── 根据模型获取消耗倍数
├── 计算实际消耗: tokens * multiplier
└── 累加到各维度计数器
```

### 5.2 RequestCodingStatus驱动

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
│ requests        │ 请求次数限制                                                     │
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

配置示例 (通用月付):
{
    "limits": {
        "requests": 10000
    },
    "thresholds": {
        "warning": 0.80,
        "disable": 0.95
    },
    "cycle": "monthly",
    "reset_day": 1
}

周期计算 (5小时周期):
1. 从配置获取周期起始偏移 (默认0点)
2. 计算当前时间在当天经过的秒数
3. 计算当前周期开始时间: floor(经过秒数 / 5小时) * 5小时
4. 下一周期开始时间: 当前周期 + 5小时

状态判断逻辑:
1. 获取当前周期已使用请求数
2. 计算使用率: used / limit
3. 根据阈值判断状态
```

### 5.3 PromptCodingStatus驱动

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                        PromptCodingStatus 驱动                                       │
│                       (按Prompt次数计费模式)                                         │
└─────────────────────────────────────────────────────────────────────────────────────┘

适用场景:
├── 按用户提问次数(Prompt)计费的平台
├── 每次Prompt触发多次模型调用的场景
└── 需要以用户视角计费的场景

配额维度:
┌─────────────────┬──────────────────────────────────────────────────────────────────┐
│ 维度            │ 说明                                                             │
├─────────────────┼──────────────────────────────────────────────────────────────────┤
│ prompts         │ Prompt次数限制                                                   │
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
        "disable": 0.90
    },
    "cycle": "5h"
}

与Request的区别:
┌─────────────────┬──────────────────────────────────────────────────────────────────┐
│ Request计费     │ Prompt计费                                                       │
├─────────────────┼──────────────────────────────────────────────────────────────────┤
│ 1次API调用=1次  │ 1次用户提问=1次Prompt                                            │
│ 后台可能多次调用│ 后台可能触发5-30次模型调用                                       │
│ 适合技术监控    │ 适合用户视角计费                                                 │
└─────────────────┴──────────────────────────────────────────────────────────────────┘

状态判断逻辑:
1. 统计周期内Prompt使用次数
2. 计算使用率
3. 根据阈值判断状态
```

### 5.4 GLMCodingStatus驱动

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

API接口:
┌─────────────────┬──────────────────────────────────────────────────────────────────┐
│ 接口            │ 说明                                                             │
├─────────────────┼──────────────────────────────────────────────────────────────────┤
│ 配额查询接口    │ GET /api/coding/quota - 获取当前配额使用情况                     │
│ 使用记录接口    │ GET /api/coding/usage - 获取使用记录                             │
│ 状态同步接口    │ POST /api/coding/sync - 同步配额状态                             │
└─────────────────┴──────────────────────────────────────────────────────────────────┘

配额维度:
┌─────────────────┬──────────────────────────────────────────────────────────────────┐
│ 维度            │ 说明                                                             │
├─────────────────┼──────────────────────────────────────────────────────────────────┤
│ prompts         │ Prompt次数 (官网显示值)                                          │
│ tokens          │ 实际Token消耗 (底层计费)                                         │
│ balance         │ 账户余额 (如适用)                                                │
└─────────────────┴──────────────────────────────────────────────────────────────────┘

同步策略:
1. 定时同步: 每5分钟调用官方API获取最新配额
2. 实时同步: 每次请求后异步推送使用量
3. 异常处理: 同步失败时使用本地缓存，超过阈值告警

状态判断:
1. 优先使用官方API返回的状态
2. API不可用时使用本地计算
3. 双重校验确保准确性

配置示例:
{
    "limits": {
        "prompts_per_5h": 80
    },
    "thresholds": {
        "warning": 0.75,
        "disable": 0.90
    },
    "cycle": "5h",
    "api_config": {
        "sync_interval": 300,
        "timeout": 10000,
        "retry_attempts": 3
    }
}
```

---

## 六、渠道状态调控

### 6.1 状态流转

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
           │ 驱动返回 WARNING 状态                                       │
           ▼                                                            │
    ┌─────────────┐                                                      │
    │   warning   │ ───── 发送警告通知 ────▶                             │
    │   (警告)    │                                                      │
    └──────┬──────┘                                                      │
           │                                                            │
           │ 驱动返回 CRITICAL 状态                                      │
           ▼                                                            │
    ┌─────────────┐                                                      │
    │  critical   │ ───── 发送临界通知 ────▶                             │
    │  (临界)     │                                                      │
    └──────┬──────┘                                                      │
           │                                                            │
           │ 驱动返回 EXHAUSTED 状态 / shouldDisable() = true            │
           ▼                                                            │
    ┌─────────────┐                                                      │
    │  disabled   │ ───── 自动禁用渠道 ────▶                             │
    │  (禁用)     │                                                      │
    └─────────────┘                                                      │
           │                                                            │
           │ 驱动 shouldEnable() = true                                  │
           └────────────────────────────────────────────────────────────┘

状态说明:
┌─────────────────┬──────────────────────────────────────────────────┐
│ 状态            │ 行为                                             │
├─────────────────┼──────────────────────────────────────────────────┤
│ active          │ 正常服务，无限制                                 │
│ warning         │ 正常服务，发送警告通知                           │
│ critical        │ 正常服务，发送临界通知                           │
│ disabled        │ 停止服务，返回配额超限错误                       │
└─────────────────┴──────────────────────────────────────────────────┘

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

### 6.2 渠道调控服务

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                         渠道Coding状态调控服务                                       │
└─────────────────────────────────────────────────────────────────────────────────────┘

核心职责:
├── 检查渠道Coding状态并触发调控
├── 根据驱动返回结果启用/禁用渠道
├── 记录状态变更日志
└── 发送状态变更通知

主要功能:
┌─────────────────────────┬──────────────────────────────────────────────────────────┐
│ 功能                    │ 说明                                                     │
├─────────────────────────┼──────────────────────────────────────────────────────────┤
│ checkAndUpdateChannel   │ 检查并更新渠道状态                                       │
│ disableChannel          │ 禁用渠道并记录日志                                       │
│ enableChannel           │ 启用渠道并记录日志                                       │
│ checkRequestAllowed     │ 检查请求是否允许 (配额检查)                              │
│ recordUsage             │ 记录配额使用                                             │
└─────────────────────────┴──────────────────────────────────────────────────────────┘

调控流程:
1. 获取渠道绑定的Coding账户
2. 根据driver_class实例化对应驱动
3. 调用driver.shouldDisable()检查是否需要禁用
4. 如需禁用且允许自动禁用，则禁用渠道
5. 调用driver.shouldEnable()检查是否需要启用
6. 如需启用且允许自动启用，则启用渠道
7. 记录状态变更日志
8. 发送通知
```

### 6.3 请求拦截中间件

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                         Coding配额检查中间件                                         │
└─────────────────────────────────────────────────────────────────────────────────────┘

职责:
├── 在请求处理前检查Coding配额
├── 配额不足时返回429错误
├── 请求成功后记录配额使用
└── 异步更新配额消耗

处理流程:
1. 获取请求中的渠道信息
2. 检查渠道是否绑定Coding账户
3. 未绑定则直接放行
4. 构建检查上下文 (model, messages, 预估tokens)
5. 调用ChannelCodingStatusService.checkRequestAllowed()
6. 配额不足返回429错误
7. 配额充足继续处理请求
8. 请求成功后异步记录使用
```

---

## 七、定时任务

### 7.1 任务设计

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              定时任务设计                                            │
└─────────────────────────────────────────────────────────────────────────────────────┘

任务列表:
┌─────────────────────┬──────────────────────────────────────────────────────────────┐
│ 任务                │ 执行频率         │ 说明                                      │
├─────────────────────┼──────────────────┼───────────────────────────────────────────┤
│ SyncCodingQuota     │ 每 5 分钟        │ 同步所有Coding账户配额                     │
│ CheckChannelStatus  │ 每 1 分钟        │ 检查渠道状态并触发调控                     │
│ ResetPeriodQuota    │ 每分钟检查       │ 检查并执行周期配额重置                     │
│ CleanupUsageLogs    │ 每日 03:00       │ 清理过期使用日志                           │
│ SendQuotaReport     │ 每周一 09:00     │ 发送配额使用报告                           │
└─────────────────────┴──────────────────┴───────────────────────────────────────────┘

任务详情:

SyncCodingQuota (同步Coding配额):
├─── 遍历所有 active 状态的 CodingAccount
├─── 调用 account.driver_class 对应驱动的 sync() 方法
├─── 更新账户 quota_cached 和 last_sync_at
├─── 处理同步失败 (记录错误，超过阈值告警)
└─── 更新渠道状态缓存

CheckChannelStatus (检查渠道状态):
├─── 查询所有绑定 coding_account_id 的渠道
├─── 调用 ChannelCodingStatusService::checkAndUpdateChannel()
├─── 根据驱动返回结果触发渠道启用/禁用
└─── 记录状态变更日志

ResetPeriodQuota (重置周期配额):
├─── 遍历所有 CodingAccount
├─── 调用 driver.getPeriodInfo() 获取周期信息
├─── 检查是否进入新周期
├─── 调用 driver.shouldEnable() 判断是否需要重置
├─── 重置Redis中的配额使用缓存
├─── 触发渠道自动启用
└─── 发送配额重置通知

CleanupUsageLogs (清理使用日志):
├─── 删除 90 天前的 coding_usage_logs
├─── 归档到冷存储 (可选)
└─── 更新统计表

SendQuotaReport (发送配额报告):
├─── 生成上周配额使用报告
├─── 包含各账户使用量、渠道状态变更次数
└─── 发送给管理员
```

---

## 八、Filament 后台管理

### 8.1 Coding账户管理

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                         Coding账户管理界面                                           │
└─────────────────────────────────────────────────────────────────────────────────────┘

列表页功能:
├── 显示账户名称、平台、状态、配额使用、最后同步时间
├── 支持按平台、状态筛选
├── 支持手动同步配额
└── 支持编辑、删除账户

表单字段:
┌─────────────────┬──────────────────────────────────────────────────────────────────┐
│ 字段            │ 说明                                                             │
├─────────────────┼──────────────────────────────────────────────────────────────────┤
│ name            │ 账户名称                                                         │
│ platform        │ 平台选择 (aliyun/volcano/zhipu/github/cursor/custom)             │
│ driver_class    │ 驱动类名 (根据平台自动填充)                                      │
│ credentials     │ 凭证信息 (KeyValue)                                              │
│ quota_config    │ 配额配置 (limits, thresholds)                                    │
│ config          │ 驱动特定配置                                                     │
└─────────────────┴──────────────────────────────────────────────────────────────────┘

平台与驱动映射:
┌─────────────────┬──────────────────────────────────────────────────────────────────┐
│ 平台            │ 驱动类                                                           │
├─────────────────┼──────────────────────────────────────────────────────────────────┤
│ aliyun          │ TokenCodingStatus / RequestCodingStatus                          │
│ volcano         │ RequestCodingStatus                                              │
│ zhipu           │ PromptCodingStatus / GLMCodingStatus                             │
│ github          │ RequestCodingStatus                                              │
│ cursor          │ RequestCodingStatus                                              │
│ custom          │ TokenCodingStatus                                                │
└─────────────────┴──────────────────────────────────────────────────────────────────┘
```

### 8.2 渠道绑定Coding账户

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                         渠道绑定Coding账户                                           │
└─────────────────────────────────────────────────────────────────────────────────────┘

配置项:
┌─────────────────────────┬──────────────────────────────────────────────────────────┐
│ 配置项                  │ 说明                                                     │
├─────────────────────────┼──────────────────────────────────────────────────────────┤
│ coding_account_id       │ 绑定的Coding账户                                         │
│ auto_disable            │ 是否自动禁用 (默认true)                                  │
│ auto_enable             │ 是否自动启用 (默认true)                                  │
│ disable_threshold       │ 禁用阈值 (默认0.95)                                      │
│ warning_threshold       │ 警告阈值 (默认0.80)                                      │
│ fallback_channel_id     │ 备用渠道ID                                               │
└─────────────────────────┴──────────────────────────────────────────────────────────┘

界面功能:
├── 选择Coding账户下拉框
├── 自动禁用/启用开关
├── 阈值配置输入框
└── 备用渠道选择
```

---

## 九、API 接口

### 9.1 管理接口

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              管理 API 接口                                           │
└─────────────────────────────────────────────────────────────────────────────────────┘

Coding账户管理:

GET    /api/v1/coding-accounts              获取账户列表
POST   /api/v1/coding-accounts              创建账户
GET    /api/v1/coding-accounts/{id}         获取账户详情
PUT    /api/v1/coding-accounts/{id}         更新账户
DELETE /api/v1/coding-accounts/{id}         删除账户
POST   /api/v1/coding-accounts/{id}/sync    手动同步配额
POST   /api/v1/coding-accounts/{id}/validate 验证凭证

渠道Coding状态:

GET    /api/v1/channels/{id}/coding-status  获取渠道Coding状态
POST   /api/v1/channels/{id}/coding-status  更新渠道Coding配置
POST   /api/v1/channels/{id}/disable        手动禁用渠道
POST   /api/v1/channels/{id}/enable         手动启用渠道

配额查询:

GET    /api/v1/coding-accounts/{id}/quota   获取账户配额信息
GET    /api/v1/coding-accounts/{id}/usage   获取账户使用记录
GET    /api/v1/coding-accounts/{id}/logs    获取状态变更日志
```

### 9.2 接口响应示例

```
获取账户配额信息响应:
{
    "account_id": 1,
    "name": "阿里云百炼-主账户",
    "platform": "aliyun",
    "driver": "RequestCodingStatus",
    "status": "active",
    "quota": {
        "limit": {"requests": 1200},
        "used": {"requests": 450},
        "remaining": {"requests": 750},
        "usage_percentage": 0.375
    },
    "period": {
        "type": "5h",
        "start": "2024-01-15T10:00:00Z",
        "end": "2024-01-15T15:00:00Z",
        "next_reset": "2024-01-15T15:00:00Z"
    },
    "last_sync_at": "2024-01-15T12:30:00Z"
}

获取渠道Coding状态响应:
{
    "channel_id": 1,
    "channel_status": "active",
    "account": {
        "id": 1,
        "name": "阿里云百炼-主账户",
        "platform": "aliyun",
        "status": "active"
    },
    "quota": {
        "usage_percentage": 0.375,
        "remaining": {"requests": 750}
    },
    "override": {
        "auto_disable": true,
        "auto_enable": true,
        "disable_threshold": 0.95
    }
}
```

---

## 十、监控与告警

### 10.1 监控指标

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              监控指标设计                                            │
└─────────────────────────────────────────────────────────────────────────────────────┘

账户指标:
cdapi_coding_account_status{account_id, platform, status}
cdapi_coding_quota_usage_percentage{account_id, metric}
cdapi_coding_quota_remaining{account_id, metric}
cdapi_coding_sync_success_total{platform}
cdapi_coding_sync_failure_total{platform}
cdapi_coding_sync_latency_seconds{platform}

渠道指标:
cdapi_channel_coding_status{channel_id, account_id}
cdapi_channel_auto_disabled_total{account_id}
cdapi_channel_auto_enabled_total{account_id}

使用指标:
cdapi_coding_requests_total{account_id, model, status}
cdapi_coding_tokens_total{account_id, model, type}
cdapi_coding_credits_total{account_id}
```

### 10.2 告警规则

```yaml
# 配额告警
groups:
  - name: coding_quota
    rules:
      - alert: CodingQuotaWarning
        expr: cdapi_coding_quota_usage_percentage > 0.8
        for: 1m
        labels:
          severity: warning
        annotations:
          summary: "Coding账户 {{ $labels.account_id }} 配额使用率超过80%"
          
      - alert: CodingQuotaCritical
        expr: cdapi_coding_quota_usage_percentage > 0.9
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "Coding账户 {{ $labels.account_id }} 配额使用率超过90%"
          
      - alert: CodingQuotaExhausted
        expr: cdapi_coding_quota_remaining == 0
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "Coding账户 {{ $labels.account_id }} 配额已耗尽"
          
      - alert: CodingSyncFailure
        expr: rate(cdapi_coding_sync_failure_total[5m]) > 0
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Coding账户同步失败"
          
      - alert: CodingAccountError
        expr: cdapi_coding_account_status{status="error"} == 1
        for: 1m
        labels:
          severity: warning
        annotations:
          summary: "Coding账户 {{ $labels.account_id }} 状态异常"
```

---

## 十一、最佳实践

### 11.1 配置建议

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              配置最佳实践                                            │
└─────────────────────────────────────────────────────────────────────────────────────┘

阈值设置建议:
┌─────────────────────┬──────────────────────────────────────────────────────────────┐
│ 场景                │ 建议配置                                                     │
├─────────────────────┼──────────────────────────────────────────────────────────────┤
│ 生产环境            │ warning: 0.80, critical: 0.90, disable: 0.95                │
│ 开发环境            │ warning: 0.90, critical: 0.95, disable: 1.00                │
│ 严格限制            │ warning: 0.70, critical: 0.85, disable: 0.90                │
│ 宽松限制            │ warning: 0.90, disable: 1.00, auto_disable: false           │
└─────────────────────┴──────────────────────────────────────────────────────────────┘

多渠道配置策略:
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                                                                     │
│  主渠道 ──▶ 高配额账户 (TokenCodingStatus)                                          │
│     │                                                                               │
│     ├── 配额耗尽 ──▶ 自动禁用                                                       │
│     │                    │                                                          │
│     │                    ▼                                                          │
│     │              备用渠道 ──▶ 中等配额账户 (RequestCodingStatus)                  │
│     │                 │                                                             │
│     │                 ├── 配额耗尽 ──▶ 自动禁用                                     │
│     │                 │                    │                                        │
│     │                 │                    ▼                                        │
│     │                 │              兜底渠道 ──▶ PromptCodingStatus                │
│     │                 │                                                           │
│     │                 └── 新周期 ──▶ 自动启用主渠道                                 │
│     │                                                                             │
│     └── 新周期 ──▶ 自动启用                                                        │
│                                                                                     │
│  配置要点:                                                                          │
│  1. 主渠道: auto_disable=true, auto_enable=true                                     │
│  2. 备用渠道: auto_disable=true, auto_enable=false (手动切回主渠道)                 │
│  3. 兜底渠道: auto_disable=false (始终可用，但配额有限)                             │
│                                                                                     │
└─────────────────────────────────────────────────────────────────────────────────────┘

驱动选择建议:
┌─────────────────────┬──────────────────────────────────────────────────────────────┐
│ 场景                │ 推荐驱动                                                     │
├─────────────────────┼──────────────────────────────────────────────────────────────┤
│ 按Token计费         │ TokenCodingStatus                                            │
│ 按请求次数计费      │ RequestCodingStatus                                          │
│ 按Prompt次数计费    │ PromptCodingStatus                                           │
│ 智谱GLM官方API      │ GLMCodingStatus                                              │
│ 阿里云百炼          │ RequestCodingStatus                                          │
│ 火山方舟            │ RequestCodingStatus                                          │
└─────────────────────┴──────────────────────────────────────────────────────────────┘
```

### 11.2 运维建议

```
日常运维:
├─── 每日检查Coding账户同步状态
├─── 监控渠道自动禁用/启用事件
├─── 检查配额使用趋势，提前规划
└─── 处理同步失败告警

故障处理:
├─── 同步失败: 检查凭证是否过期，网络是否正常
├─── 误禁用: 手动启用渠道，调整阈值配置
├─── 配额不准: 手动触发同步，检查API响应
└─── 驱动异常: 检查驱动配置，查看错误日志

扩展新驱动:
├─── 1. 创建新驱动类实现 CodingStatusDriver 接口
├─── 2. 实现平台特定的配额获取逻辑
├─── 3. 配置模型消耗倍数
├─── 4. 添加Filament管理界面
└─── 5. 测试驱动功能
```

---

## 十二、总结

### 12.1 架构优势

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              新架构优势                                              │
└─────────────────────────────────────────────────────────────────────────────────────┘

1. 职责分离
   ├── 渠道: 专注API代理，不关心配额管理细节
   ├── Coding账户: 统一管理各平台凭证和驱动绑定
   └── CodingStatus驱动: 各计费模式独立实现配额管理逻辑

2. 易于扩展
   ├── 新增计费模式只需实现CodingStatusDriver接口
   ├── 新增平台只需配置对应驱动
   └── 驱动可复用于多个渠道

3. 灵活配置
   ├── 渠道可覆盖账户级别的自动调控配置
   ├── 支持备用渠道切换
   └── 各驱动支持平台特定的配置

4. 统一管理
   ├── 多渠道多平台统一监控
   ├── 统一的状态流转和日志记录
   └── 统一的API接口
```

### 12.2 核心流程回顾

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              核心流程回顾                                            │
└─────────────────────────────────────────────────────────────────────────────────────┘

渠道请求处理流程:
┌─────────────────────────────────────────────────────────────────────────────────┐
│                                                                                 │
│  1. 请求到达渠道                                                                 │
│       │                                                                         │
│       ▼                                                                         │
│  2. CheckCodingQuota中间件拦截                                                  │
│       │                                                                         │
│       ├── 未绑定Coding账户 ──▶ 直接放行                                         │
│       │                                                                         │
│       └── 已绑定Coding账户                                                      │
│             │                                                                   │
│             ▼                                                                   │
│       3. 获取CodingAccount                                                      │
│             │                                                                   │
│             ▼                                                                   │
│       4. 实例化driver_class对应驱动                                             │
│             │                                                                   │
│             ▼                                                                   │
│       5. driver.checkQuota(context)                                             │
│             │                                                                   │
│             ├── 配额不足 ──▶ 返回429错误                                        │
│             │                                                                         │
│             └── 配额充足                                                          │
│                   │                                                               │
│                   ▼                                                               │
│             6. 执行请求                                                           │
│                   │                                                               │
│                   ▼                                                               │
│             7. driver.consume(usage) 更新配额                                     │
│                   │                                                               │
│                   ▼                                                               │
│             8. ChannelCodingStatusService检查并更新渠道状态                       │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘

定时任务流程:
┌─────────────────────────────────────────────────────────────────────────────────┐
│                                                                                 │
│  SyncCodingQuota (每5分钟)                                                      │
│       │                                                                         │
│       ├── 遍历所有CodingAccount                                                 │
│       ├── 调用driver.sync()同步配额                                             │
│       └── 更新账户quota_cached                                                  │
│                                                                                 │
│  CheckChannelCodingStatus (每分钟)                                              │
│       │                                                                         │
│       ├── 遍历所有绑定Coding账户的渠道                                          │
│       ├── 调用driver.shouldDisable() / shouldEnable()                           │
│       └── 触发渠道禁用/启用                                                     │
│                                                                                 │
│  ResetPeriodQuota (每分钟)                                                      │
│       │                                                                         │
│       ├── 检查各账户是否进入新周期                                              │
│       ├── 重置配额使用缓存                                                      │
│       └── 触发渠道自动启用                                                      │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### 12.3 驱动对比

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              驱动对比总结                                            │
└─────────────────────────────────────────────────────────────────────────────────────┘

┌──────────────────┬─────────────────┬─────────────────┬─────────────────┬────────────┐
│ 特性             │ TokenCoding     │ RequestCoding   │ PromptCoding    │ GLMCoding  │
├──────────────────┼─────────────────┼─────────────────┼─────────────────┼────────────┤
│ 计费维度         │ Token           │ 请求次数        │ Prompt次数      │ Prompt/Token│
│ 周期支持         │ 日/周/月/无     │ 日/周/月/5h/无  │ 日/周/月/5h/无  │ 5h (官方)  │
│ 模型倍数         │ 支持            │ 支持            │ 支持            │ 支持       │
│ 外部API同步      │ 可选            │ 可选            │ 可选            │ 必需       │
│ 适用平台         │ 通用            │ 阿里云/火山等   │ 智谱GLM等       │ 智谱GLM    │
│ 实现复杂度       │ 低              │ 低              │ 低              │ 中         │
└──────────────────┴─────────────────┴─────────────────┴─────────────────┴────────────┘
```
