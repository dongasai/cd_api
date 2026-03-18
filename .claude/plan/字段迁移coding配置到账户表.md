# 字段迁移计划：channels -> coding_accounts

## 背景

当前 `channels` 表中的 `coding_status_override` 和 `coding_last_check_at` 字段应该属于 `coding_accounts` 表。
检查应该针对账户级别，而非渠道级别。操作应该影响渠道的 `status2`（健康状态），而非 `status`（运营状态）。

## 变更概览

### 数据库变更

| 表 | 操作 | 字段 |
|---|---|---|
| coding_accounts | 新增 | `status_override` JSON |
| coding_accounts | 新增 | `last_check_at` TIMESTAMP |
| channels | 移除 | `coding_status_override` |
| channels | 移除 | `coding_last_check_at` |

### 代码变更

| 文件 | 变更类型 | 说明 |
|---|---|---|
| CodingAccount.php | 修改 | 添加新字段和方法 |
| Channel.php | 修改 | 移除废弃方法和属性 |
| ChannelCodingStatusService.php | 重构 | 改为操作 status2，使用账户级别检查 |
| ResetPeriodQuota.php | 修改 | 使用账户方法 |

## 详细步骤

### 步骤 1: 创建数据库迁移文件

**文件**: `laravel/database/migrations/2026_03_18_XXXXXX_migrate_coding_fields_to_accounts_table.php`

**内容**:
1. 在 `coding_accounts` 表新增:
   - `status_override` JSON NULL
   - `last_check_at` TIMESTAMP NULL
2. 数据迁移:
   - 从 channels 读取现有数据
   - 合并同一 account_id 的配置（取第一条）
   - 更新到 coding_accounts
3. 移除 `channels` 表字段:
   - `coding_status_override`
   - `coding_last_check_at`

### 步骤 2: 修改 CodingAccount 模型

**文件**: `laravel/app/Models/CodingAccount.php`

**新增**:
```php
// fillable 添加
'status_override',
'last_check_at',

// casts 添加
'status_override' => 'array',
'last_check_at' => 'datetime',

// 新方法
getStatusOverride(): array
allowsAutoDisable(): bool
allowsAutoEnable(): bool
getDisableThreshold(): float
getWarningThreshold(): float
updateLastCheckAt(): void
```

### 步骤 3: 修改 Channel 模型

**文件**: `laravel/app/Models/Channel.php`

**移除**:
- `$fillable` 中的 `coding_status_override`, `coding_last_check_at`
- `$casts` 中的 `coding_status_override`, `coding_last_check_at`
- `@property` 注释中的相关属性
- `getCodingStatusOverride()` 方法
- `allowsAutoDisable()` 方法
- `allowsAutoEnable()` 方法

### 步骤 4: 重构 ChannelCodingStatusService

**文件**: `laravel/app/Services/CodingStatus/ChannelCodingStatusService.php`

**变更**:
1. `checkAndUpdateChannel()`: 调用账户方法获取配置
2. `disableChannel()`: 改为 `$channel->disableHealth($reason)`
3. `enableChannel()`: 改为 `$channel->enableHealth()`
4. `checkChannelIfNeeded()`: 使用 `$account->last_check_at`
5. `batchCheckAndUpdate()`: 按账户分组检查，避免重复

### 步骤 5: 修改 ResetPeriodQuota 命令

**文件**: `laravel/app/Console/Commands/ResetPeriodQuota.php`

**变更**:
- `enableRelatedChannels()`: 使用 `$account->allowsAutoEnable()` 而非 `$channel->allowsAutoEnable()`

### 步骤 6: 创建回滚迁移

**文件**: `laravel/database/migrations/2026_03_18_XXXXXX_rollback_coding_fields_migration.php`

**内容**: 恢复 channels 表字段

## 注意事项

1. **数据合并策略**: 同一 CodingAccount 被多个 Channel 使用时，取第一条 Channel 的配置
2. **操作字段**: 使用 `status2`（健康状态），不影响 `status`（运营状态）
3. **检查粒度**: 账户级别统一检查，一次检查控制所有关联渠道

## 测试验证

1. 运行迁移: `php artisan migrate`
2. 验证数据: 检查 coding_accounts 表数据是否正确迁移
3. 功能测试: 运行 `php artisan coding:check-channels`