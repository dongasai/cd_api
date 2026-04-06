# CodingStatus 渠道错误处理能力

## 上下文

### 任务需求
CodingStatus 增加渠道错误处理能力：当渠道请求错误时，根据返回的状态码和内容匹配规则，自动暂停 CodingAccount 并按配置时间自动恢复。

### 用户反馈要点
1. 暂停对象：暂停 CodingAccount，所有关联渠道不可用
2. 账户状态：账户状态设为 suspended，使用现有 disabled_at 字段
3. 触发条件：可配置规则（如 429状态码暂停10分钟、"达到5小时限额"暂停1小时）
4. 恢复机制：复用现有 AutoReopenCodingAccounts 命令
5. 错误匹配：HTTP状态码 + API错误类型结合

### 技术上下文
- Laravel 12 框架
- CodingStatusDriver 接口已有 shouldDisable/shouldEnable 方法
- Channel 模型有 status2（健康状态）字段，枚举值 normal/disabled
- ChannelRequestLog 记录 response_status、error_type、error_message
- ChannelCodingStatusService 管理 Channel 与 CodingAccount 状态关联
- 现有 AutoReopenCodingAccounts 命令处理账户恢复

### 字段复用设计

**现有字段**：
- `status` - 账户状态（suspended 表示暂停）
- `disabled_at` - 禁用时间点

**新增字段**：
- `pause_duration_minutes` - 暂停时长（分钟）
- `pause_reason` - 暂停原因
- `pause_rule_id` - 触发暂停的规则ID

**恢复时间计算**：
- 有 `pause_duration_minutes` → 恢复时间 = disabled_at + pause_duration_minutes
- 无 `pause_duration_minutes` → 恢复时间 = disabled_at + auto_reopen_hours

---

## 实现计划

### 步骤 1: 创建数据库迁移 - channel_error_rules 表

**文件**: `laravel/database/migrations/{timestamp}_create_channel_error_rules_table.php`

**字段设计**:
```
- id: bigint primary
- name: string 规则名称
- coding_account_id: nullable bigint 外键（账户级规则）
- driver_class: nullable string 驱动类名（驱动级规则）
- pattern_type: enum(status_code|error_message|error_type|both)
- pattern_value: string 匹配值（如 "429" 或 "rate_limit"）
- pattern_operator: enum(exact|contains|regex)
- action: enum(pause_account|alert_only)
- pause_duration_minutes: int 暂停时长（分钟）
- priority: int 规则优先级（越大越优先）
- is_enabled: boolean 是否启用
- metadata: nullable json 扩展配置
- created_at, updated_at
```

**预期结果**: 迁移文件创建完成，PHP语法检查通过

---

### 步骤 2: 创建 ChannelErrorRule 模型

**文件**: `laravel/app/Models/ChannelErrorRule.php`

**方法**:
- `codingAccount(): BelongsTo` - 关联 CodingAccount
- `matchesError(int $statusCode, string $errorType, string $errorMessage): bool` - 匹配错误
- `getActiveRules(?CodingAccount $account, ?string $driverClass): Collection` - 获取活跃规则
- `scopeEnabled($query)` - 启用规则作用域

**预期结果**: 模型创建完成，关联关系正确

---

### 步骤 3: 扩展 CodingStatusDriver 接口

**文件**: `laravel/app/Services/CodingStatus/Drivers/CodingStatusDriver.php`

**新增接口方法**:
```php
/**
 * 处理渠道错误
 *
 * @param array $errorContext 错误上下文 (channel_id, status_code, error_type, error_message, response_body)
 * @return array 处理结果 (handled, action, pause_duration, rule_matched)
 */
public function handleError(array $errorContext): array;

/**
 * 获取驱动默认错误处理规则
 *
 * @return array 规则配置数组
 */
public function getDefaultErrorRules(): array;
```

**预期结果**: 接口新增两个方法声明

---

### 步骤 4: AbstractCodingStatusDriver 默认实现

**文件**: `laravel/app/Services/CodingStatus/Drivers/AbstractCodingStatusDriver.php`

**实现内容**:
- `handleError()` - 核心错误处理逻辑
- `getDefaultErrorRules()` - 返回驱动默认规则配置
- `applyErrorRule()` - 应用规则暂停账户
- `recordErrorHandlingLog()` - 记录处理日志

**核心逻辑**:
1. 从 ChannelErrorRule 获取匹配规则
2. 按优先级匹配错误
3. 执行暂停动作（设置 status=suspended, disabled_at=now, pause_duration_minutes, pause_reason, pause_rule_id）
4. 记录处理日志
5. 返回处理结果

**预期结果**: 抽象类实现错误处理核心逻辑

---

### 步骤 5: 创建 ChannelErrorHandlingService

**文件**: `laravel/app/Services/CodingStatus/ChannelErrorHandlingService.php`

**职责**: 协调错误处理流程

**方法**:
- `handleRequestError(Channel $channel, ChannelRequestLog $log): array` - 处理请求错误
- `manualRecoverAccount(CodingAccount $account, ?int $userId): array` - 手动恢复账户
- `manualPauseAccount(CodingAccount $account, int $minutes, ?int $userId, ?string $reason): array` - 手动暂停账户

**预期结果**: 服务类封装错误处理协调逻辑

---

### 步骤 6: 扩展 CodingAccount 模型

**文件**: `laravel/app/Models/CodingAccount.php`

**新增字段**（需迁移）:
- `pause_duration_minutes: nullable int` - 暂停时长（分钟）
- `pause_reason: nullable string` - 暂停原因
- `pause_rule_id: nullable bigint` - 触发暂停的规则ID

**修改方法**:
- `shouldAutoReopen()` - 支持 pause_duration_minutes 优先
- `pauseAccount(int $minutes, string $reason, ?int $ruleId): void` - 暂停账户

**预期结果**: CodingAccount 模型新增暂停状态管理能力

---

### 步骤 7: 创建 CodingAccount 暂停字段迁移

**文件**: `laravel/database/migrations/{timestamp}_add_pause_fields_to_coding_accounts_table.php`

**字段**:
- pause_duration_minutes
- pause_reason
- pause_rule_id

**预期结果**: 迁移文件创建完成

---

### 步骤 8: 创建 ChannelErrorHandlingLog 模型

**文件**: `laravel/app/Models/ChannelErrorHandlingLog.php`

**字段**:
- id, channel_id, account_id, rule_id
- error_status_code, error_type, error_message
- action_taken, pause_duration_minutes
- triggered_by (auto/manual), user_id
- created_at

**预期结果**: 错误处理日志模型创建完成

---

### 步骤 9: 修改 AutoReopenCodingAccounts 命令

**文件**: `laravel/app/Console/Commands/AutoReopenCodingAccounts.php`

**修改点**: 支持 pause_duration_minutes 字段

**恢复逻辑**:
```php
if ($account->pause_duration_minutes) {
    // 错误暂停：disabled_at + pause_duration_minutes
    $recoverAt = $account->disabled_at->addMinutes($account->pause_duration_minutes);
} else {
    // 配额耗尽：disabled_at + auto_reopen_hours
    $recoverAt = $account->disabled_at->addHours($account->getAutoReopenHours());
}

if (now()->gte($recoverAt)) {
    $account->reopen();
}
```

**预期结果**: 命令支持两种恢复模式

---

### 步骤 10: 修改渠道路由选择逻辑

**文件**: `laravel/app/Services/Router/ChannelRouterService.php`

**修改点**: 在渠道查询时排除暂停账户的渠道

**逻辑**: 通过 Channel.codingAccount 关联检查账户状态

**预期结果**: 暂停账户的渠道不参与路由选择

---

### 步骤 11: 集成到请求处理流程

**修改文件**: `laravel/app/Services/Router/ProxyServer.php` 或相关处理器

**集成点**: 请求失败后调用 `ChannelErrorHandlingService::handleRequestError()`

**预期结果**: 请求失败自动触发错误处理

---

### 步骤 12: 创建后台管理界面

**文件**:
- `laravel/app/Admin/Controllers/ChannelErrorRuleController.php`
- 修改 `laravel/app/Admin/routes.php` 注册路由

**功能**:
- 管理错误处理规则列表
- 创建/编辑/删除规则
- 配置匹配条件和处理动作

**预期结果**: 后台可管理错误处理规则

---

### 步骤 13: 单元测试

**文件**:
- `laravel/tests/Unit/Models/ChannelErrorRuleTest.php`
- `laravel/tests/Unit/Services/CodingStatus/ChannelErrorHandlingServiceTest.php`

**测试内容**:
- 规则匹配逻辑测试
- 错误处理流程测试
- 暂停恢复机制测试

**预期结果**: 测试通过

---

### 步骤 14: 创建工作日志

**文件**: `work/{年月}/{日}-{时分}-渠道错误处理能力.md`

**预期结果**: 工作日志文档创建完成

---

## 修改文件清单

1. `laravel/database/migrations/{timestamp}_create_channel_error_rules_table.php` - 新建
2. `laravel/database/migrations/{timestamp}_add_pause_fields_to_coding_accounts_table.php` - 新建
3. `laravel/database/migrations/{timestamp}_create_channel_error_handling_logs_table.php` - 新建
4. `laravel/app/Models/ChannelErrorRule.php` - 新建
5. `laravel/app/Models/ChannelErrorHandlingLog.php` - 新建
6. `laravel/app/Models/CodingAccount.php` - 修改
7. `laravel/app/Services/CodingStatus/Drivers/CodingStatusDriver.php` - 修改
8. `laravel/app/Services/CodingStatus/Drivers/AbstractCodingStatusDriver.php` - 修改
9. `laravel/app/Services/CodingStatus/ChannelErrorHandlingService.php` - 新建
10. `laravel/app/Console/Commands/AutoReopenCodingAccounts.php` - 修改
11. `laravel/app/Services/Router/ChannelRouterService.php` - 修改
12. `laravel/app/Admin/Controllers/ChannelErrorRuleController.php` - 新建
13. `laravel/app/Admin/routes.php` - 修改
14. 测试文件 - 新建
15. 工作日志 - 新建

---

**请批准此计划后进入执行阶段**