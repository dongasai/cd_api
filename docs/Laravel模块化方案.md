# Laravel 模块化重构方案

> 基于 nwidart/laravel-modules 的 CdApi 模块化方案

## 一、技术选型

**方案**：使用 `nwidart/laravel-modules` 包

**理由**：
- 项目未来可能扩展为多团队协作
- 需要清晰的功能边界和模块独立性
- 支持模块级别的启用/禁用
- 便于复用模块到其他项目

**替代方案（已放弃）**：
- 轻量级命名空间模块：缺乏模块隔离机制
- 手动模块化：维护成本高，无标准化工具支持

## 二、模块划分

### 模块列表（8个模块）

| 模块 | 说明 | 依赖 | 核心内容 |
|------|------|------|----------|
| **Core** | 共享基础设施 | 无 | DTO, Enums, Contracts, Exceptions |
| **Protocol** | 协议转换 | Core | ProtocolConverter, Driver (OpenAI/Anthropic/Responses) |
| **Provider** | 供应商驱动 | Core | ProviderManager, 6个供应商实现 |
| **Channel** | 渠道管理 | Core | Models (Channel, ApiKey...), Services, Migrations |
| **Coding** | 配额计费 | Core, Channel | Models, Services, 8种驱动, Commands |
| **Proxy** | 请求代理核心 | Protocol, Provider, Channel | ProxyServer, Loggers, ProxyController |
| **Search** | 搜索服务 | Core | SearchDriverManager, 5个驱动 |
| **Mcp** | MCP 服务 | Core, Search | CdApiServer, Tools |
| **Admin** | Dcat 后台 | 所有模块 | Controllers, Actions, Models, Services |

### 模块依赖关系

```
┌─────────────┐
│    Core     │ ◄─── 所有模块依赖
│ (Shared DTO)│
└──────┬──────┘
       │
┌──────┼──────┬───────────┬───────────┐
│      │      │           │           │
▼      ▼      ▼           ▼           ▼
┌─────┐┌──────┐┌──────────┐┌──────────┐
│Proto││Provid││ Channel  ││  Coding  │
│col  ││er    ││          ││          │
└──┬──┘└───┬──┘└────┬─────┘└────┬─────┘
   │      │       │           │
   └──────┼───────┴─────┬─────┘
          │             │
          ▼             ▼
     ┌─────────────────────┐
     │       Proxy         │
     │  (ProxyServer 核心) │
     └──────────┬──────────┘
                │
    ┌───────────┼───────────┐
    ▼           ▼           ▼
┌───────┐  ┌───────┐  ┌───────┐
│ Admin │  │  Mcp  │  │ Search│
│       │  │       │  │       │
└───────┘  └───────┘  └───────┘
```

### 模块详细内容

#### 1. Core 模块

**最底层模块，无依赖**

```
Modules/Core/
├── CoreServiceProvider.php
├── DTO/                          # 从 app/Services/Shared/DTO 移入
│   ├── ActualRequestInfo.php
│   ├── CacheCreation.php
│   ├── Container.php
│   ├── ContentBlock.php
│   ├── Message.php
│   ├── Request.php
│   ├── Response.php
│   ├── StreamChunk.php
│   ├── Tool.php
│   ├── ToolCall.php
│   └── Usage.php
├── Enums/                        # 从 app/Services/Shared/Enums 移入
│   ├── ErrorType.php
│   ├── FinishReason.php
│   ├── MessageRole.php
│   ├── StreamEventType.php
│   └── ToolType.php
├── Contracts/                    # 协议契约接口
│   ├── ProtocolRequest.php
│   └── ProtocolResponse.php
└── Exceptions/                   # 共享异常
    └── BaseException.php
```

#### 2. Proxy 模块

**核心请求处理模块，依赖 Core**

```
Modules/Proxy/
├── ProxyServiceProvider.php
├── Http/
│   └── Controllers/
│       └── ProxyController.php   # 从 app/Http/Controllers/Api 移入
├── Models/
│   ├── RequestLog.php
│   ├── AuditLog.php
│   ├── ResponseLog.php
│   ├── ResponseSession.php
│   └── ChannelRequestLog.php
├── Services/
│   ├── ProxyServer.php
│   ├── RetryHandler.php
│   ├── Handler/
│   │   ├── StreamHandler.php
│   │   └── NonStreamHandler.php
│   └── Logger/
│       ├── RequestLogger.php
│       ├── AuditLogger.php
│       └── ResponseLogger.php
├── Console/
│   ├── TestProxy.php
│   ├── TestOpenAI.php
│   ├── TestAnthropicProxy.php
│   ├── ReplayRequest*.php        # 4个重放命令
│   ├── AnalyzeRequestDiff.php
│   ├── ValidateApiRequest.php
│   └── TestToolCall.php
├── Database/Migrations/          # 日志表迁移
│   ├── create_audit_logs_table.php
│   ├── create_request_logs_table.php
│   ├── create_response_logs_table.php
│   ├── create_channel_request_logs_table.php
│   └── create_response_sessions_table.php
└── Routes/
    └── api.php                   # 代理 API 路由
```

#### 3. Protocol 模块

**协议转换模块，依赖 Core**

```
Modules/Protocol/
├── ProtocolServiceProvider.php
├── Services/
│   ├── ProtocolConverter.php
│   ├── DriverManager.php
│   ├── Driver/
│   │   ├── DriverInterface.php
│   │   ├── AbstractDriver.php
│   │   ├── OpenAiChatCompletionsDriver.php
│   │   ├── AnthropicMessagesDriver.php
│   │   ├── OpenAIResponsesDriver.php
│   │   ├── Concerns/
│   │   │   ├── Convertible.php
│   │   │   ├── JsonSerializiable.php
│   │   │   ├── ProtocolResponseTrait.php
│   │   │   └── Validatable.php
│   │   ├── OpenAI/              # 19 个 DTO
│   │   ├── Anthropic/           # 7 个 DTO
│   │   └── OpenAIResponses/     # 3 个 DTO
│   └── Exceptions/
│       ├── ProtocolException.php
│       ├── ConversionException.php
│       └── UnsupportedProtocolException.php
└── Config/
    └── protocol.php
```

#### 4. Provider 模块

**供应商驱动模块，依赖 Core**

```
Modules/Provider/
├── ProviderServiceProvider.php
├── Services/
│   ├── ProviderManager.php
│   ├── Driver/
│   │   ├── ProviderInterface.php
│   │   ├── AbstractProvider.php
│   │   ├── OpenAIProvider.php
│   │   ├── AnthropicProvider.php
│   │   ├── AzureProvider.php
│   │   ├── DeepSeekProvider.php
│   │   └── OpenAICompatibleProvider.php
│   └── Exceptions/
│       └── ProviderException.php
└── Config/
    └── providers.php
```

#### 5. Channel 模块

**渠道管理与路由模块，依赖 Core**

```
Modules/Channel/
├── ChannelServiceProvider.php
├── Models/
│   ├── Channel.php
│   ├── ChannelGroup.php
│   ├── ChannelTag.php
│   ├── ChannelModel.php
│   ├── ChannelAffinityCache.php
│   ├── ChannelAffinityRule.php
│   ├── ChannelErrorRule.php
│   ├── ChannelErrorHandlingLog.php
│   ├── ApiKey.php
│   ├── ModelList.php
│   └── UserAgent.php
├── Services/
│   ├── ChannelRouterService.php
│   ├── ChannelSelector.php
│   ├── ChannelAffinity/
│   │   ├── ChannelAffinityService.php
│   │   ├── ChannelAffinityCache.php
│   │   ├── KeyExtractor.php
│   │   ├── RuleMatcher.php
│   │   ├── DTO/
│   │   └── Exceptions/
│   └── UserAgentFilterService.php
├── Enums/
│   ├── ChannelStatus.php
│   ├── ChannelHealthStatus.php
│   └── PathPattern.php
├── Database/Migrations/          # 渠道相关迁移
│   ├── create_channels_table.php
│   ├── create_channel_groups_table.php
│   ├── create_channel_tags_table.php
│   ├── create_channel_models_table.php
│   ├── create_api_keys_table.php
│   ├── create_model_lists_table.php
│   ├── create_user_agents_table.php
│   ├── create_channel_affinity_rules_table.php
│   ├── create_channel_affinity_caches_table.php
│   └── create_channel_error_rules_table.php
└── Config/
    └── router.php
```

#### 6. Coding 模块

**配额计费模块，依赖 Core + Channel**

```
Modules/Coding/
├── CodingServiceProvider.php
├── Models/
│   ├── CodingAccount.php
│   ├── CodingQuotaUsage.php
│   ├── CodingUsageLog.php
│   ├── Coding5ZMQuota.php
│   ├── Coding5ZMStatusLog.php
│   ├── CodingSlidingWindow.php
│   ├── CodingSlidingUsageLog.php
│   └── CodingStatusLog.php
├── Services/
│   ├── ChannelCodingStatusService.php
│   ├── ChannelErrorHandlingService.php
│   ├── CodingStatusDriverManager.php
│   ├── SlidingWindowRepository.php
│   └── Drivers/                  # 8种计费驱动
│       ├── CodingStatusDriver.php
│       ├── AbstractCodingStatusDriver.php
│       ├── RequestCodingStatusDriver.php
│       ├── TokenCodingStatusDriver.php
│       ├── SlidingRequestCodingStatusDriver.php
│       ├── SlidingTokenCodingStatusDriver.php
│       ├── Request5ZMCodingStatusDriver.php
│       ├── GLMCodingStatusDriver.php
│       └── PromptCodingStatusDriver.php
├── Http/Middleware/
│   └── CheckCodingQuota.php
├── Console/
│   ├── AutoReopenCodingAccounts.php
│   ├── SyncCodingQuota.php
│   ├── ResetPeriodQuota.php
│   └── CleanupSlidingWindowData.php
├── Database/Migrations/
│   ├── create_coding_accounts_table.php
│   ├── create_coding_usage_logs_table.php
│   ├── create_coding_status_logs_table.php
│   ├── create_coding_sliding_windows_table.php
│   ├── create_coding_5zm_quotas_table.php
│   └── create_channel_error_handling_logs_table.php
└── Config/
    └── coding.php
```

#### 7. Admin 模块

**Dcat Admin 后台，依赖所有业务模块**

```
Modules/Admin/
├── AdminServiceProvider.php
├── Admin/
│   ├── Controllers/             # 28 个控制器
│   ├── Actions/                 # 16 个 Actions
│   ├── Grids/
│   ├── Widgets/
│   ├── Extensions/
│   ├── Repositories/
│   ├── Console/
│   │   └── ResetAdminPassword.php
│   ├── bootstrap.php
│   └── routes.php
├── Models/
│   ├── Administrator.php
│   ├── User.php
│   ├── SystemSetting.php
│   ├── OperationLog.php
│   ├── PresetPrompt.php
│   ├── ModelTestLog.php
│   ├── SearchDriver.php
│   ├── SearchLog.php
│   └── McpClient.php
├── Services/
│   ├── SettingService.php
│   ├── ModelService.php
│   ├── ModelTestService.php
│   ├── OperationLogService.php
│   └── McpClientService.php
├── Http/Middleware/
│   ├── SetAdminLocale.php
│   └── SetUserInfo.php
├── Enums/
│   ├── SettingGroup.php
│   ├── OperationTarget.php
│   ├── OperationType.php
│   └── OperationSource.php
├── Database/Migrations/
│   ├── create_admin_tables.php
│   ├── create_users_table.php
│   ├── create_system_settings_table.php
│   ├── create_operation_logs_table.php
│   ├── create_preset_prompts_table.php
│   ├── create_model_test_logs_table.php
│   ├── create_search_drivers_table.php
│   ├── create_search_logs_table.php
│   └── create_mcp_clients_table.php
└── routes.php                   # Admin 路由
```

#### 8. Mcp 模块

**MCP 服务，依赖 Core + Search**

```
Modules/Mcp/
├── McpServiceProvider.php
├── Mcp/
│   ├── Servers/
│   │   └── CdApiServer.php
│   └── Tools/
│       ├── SearchTool.php
│       └── WebParserTool.php
├── Console/
│   └── TestMcpConnection.php
└── Routes/
    └── ai.php                   # MCP 路由
```

#### 9. Search 模块

**搜索服务，依赖 Core**

```
Modules/Search/
├── SearchServiceProvider.php
├── Services/
│   ├── SearchDriverManager.php
│   ├── Contracts/
│   │   ├── SearchRequest.php
│   │   ├── SearchResult.php
│   │   └── SearchItem.php
│   ├── Driver/
│   │   ├── SearchDriverInterface.php
│   │   ├── AbstractSearchDriver.php
│   │   ├── DuckDuckGoSearchDriver.php
│   │   ├── SerperSearchDriver.php
│   │   ├── BailianSearchDriver.php
│   │   └── MockSearchDriver.php
│   └── Exceptions/
│       └── SearchDriverException.php
└── Config/
    └── search.php
```

## 三、模块间通信机制

### 1. 依赖注入方式

模块通过 ServiceProvider 注册服务，其他模块通过容器获取：

```php
// Modules/Proxy/Services/ProxyServer.php
namespace Modules\Proxy\Services;

use Modules\Protocol\Services\ProtocolConverter;
use Modules\Provider\Services\ProviderManager;
use Modules\Channel\Services\ChannelRouterService;

class ProxyServer
{
    public function __construct(
        ProtocolConverter $protocolConverter,
        ProviderManager $providerManager,
        ChannelRouterService $channelRouter,
    ) {
        // ...
    }
}
```

### 2. 事件驱动（跨模块解耦）

对于松耦合场景，使用 Laravel Events：

```php
// 定义事件
// Modules/Channel/Events/ChannelSelected.php
namespace Modules\Channel\Events;

class ChannelSelected
{
    public function __construct(public Channel $channel) {}
}

// 监听事件
// Modules/Coding/Listeners/RecordChannelUsage.php
namespace Modules\Coding\Listeners;

class RecordChannelUsage
{
    public function handle(ChannelSelected $event)
    {
        // 记录 Coding 使用量
    }
}

// 在 ServiceProvider 中注册
class CodingServiceProvider extends ServiceProvider
{
    protected $listen = [
        \Modules\Channel\Events\ChannelSelected::class => [
            \Modules\Coding\Listeners\RecordChannelUsage::class,
        ],
    ];
}
```

### 3. 五条架构红线

| # | 规则 | 说明 |
|---|------|------|
| 1 | **禁止跨模块直接调用 Model** | 多租户数据隔离的核心防线 |
| 2 | **禁止跨模块直接调用 Service** | 必须通过依赖注入或事件 |
| 3 | **Handler 禁止直接操作 DB** | 必须委托给 Service 层 |
| 4 | **Service 优先使用静态方法** | 便于跨模块调用，减少实例化 |
| 5 | **统一异常处理** | 使用模块内的 Exception 类 |

**错误示例**：
```php
// ❌ 错误：Proxy 模块直接调用 Coding 模块的 Model
use Modules\Coding\Models\CodingAccount;
$account = CodingAccount::find(1);
```

**正确示例**：
```php
// ✅ 正确：通过事件
event(new ChannelSelected($channel));

// ✅ 正确：通过依赖注入
$codingService = app(Modules\Coding\Services\CodingStatusService::class);
```

## 四、Dcat Admin v2 集成方案

### 兼容性问题处理

基于 nengtan_laravel 项目经验：

1. **BatchAction 命名空间**：`Dcat\Admin\Grid\BatchAction`（不是 `Tools\BatchAction`）
2. **BatchAction 方法**：使用 `add()`（不是 `append()`）
3. **翻译文件位置**：Laravel 12 使用 `lang/` 目录（不是 `resources/lang/`）
4. **Grid getSort()**：返回索引数组 `[column, type, cast]`，用 `$sort[0]` / `$sort[1]`
5. **Action 类**：继承 Action 类，只覆写 `title()`，不覆写 `html()`

### Admin 模块特殊处理

Admin 模块作为独立入口，遵循 Dcat Admin 约定：

```php
// Modules/Admin/AdminServiceProvider.php
namespace Modules\Admin;

use Illuminate\Support\ServiceProvider;
use Dcat\Admin\Admin;

class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // 注册 Admin 路由
        $this->loadRoutesFrom(__DIR__.'/Admin/routes.php');

        // 加载视图
        $this->loadViewsFrom(__DIR__.'/Admin/views', 'admin');

        // 加载翻译
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'admin');

        // Dcat Admin 扩展注册
        Admin::booting(function () {
            // 注册扩展
        });
    }
}
```

### Admin 控制器命名空间

```php
// config/admin.php
return [
    'route' => [
        'namespace' => 'Modules\\Admin\\Admin\\Controllers',
    ],
];
```

### Admin 按功能域组织（可选）

```
Admin/Controllers/
├── Channel/              # 渠道相关（6个控制器）
├── Coding/               # 计费相关（1个控制器）
├── Log/                  # 日志相关（6个控制器）
├── Mcp/                  # MCP 相关（1个控制器）
└── ...                   # 其他保持原位
```

## 五、迁移步骤（10阶段，约12天）

### 阶段 0：准备工作（0.5天）

```bash
# 创建 Git 分支
git checkout -b feature/modularization

# 安装 nwidart/laravel-modules
cd laravel
composer require nwidart/laravel-modules

# 发布配置文件
php artisan vendor:publish --provider="Nwidart\Modules\LaravelModulesServiceProvider"
```

配置 `config/modules.php`：
```php
return [
    'namespace' => 'Modules',
    'stubs' => [
        'enabled' => false,  // 禁用默认 stubs，手动创建
    ],
    'paths' => [
        'modules' => base_path('Modules'),
        'manifest' => base_path('Modules/modules.json'),
    ],
    'register' => [
        'scan_autoload' => true,
    ],
];
```

### 阶段 1：Core 模块（1天）

```bash
# 创建 Core 模块
php artisan module:make Core
```

迁移内容：
- `app/Services/Shared/DTO/*` → `Modules/Core/DTO/`
- `app/Services/Shared/Enums/*` → `Modules/Core/Enums/`
- `app/Services/Protocol/Contracts/*` → `Modules/Core/Contracts/`

命名空间替换：
```bash
find app -name "*.php" -exec sed -i 's/App\\Services\\Shared\\DTO/Modules\\Core\\DTO/g' {} \;
find app -name "*.php" -exec sed -i 's/App\\Services\\Shared\\Enums/Modules\\Core\\Enums/g' {} \;
```

**验证点**：`composer test` 全部通过

### 阶段 2：Protocol 模块（1天）

```bash
php artisan module:make Protocol
```

迁移内容：
- `app/Services/Protocol/*` → `Modules/Protocol/Services/`
- `app/Providers/ProtocolServiceProvider.php` → `Modules/Protocol/ProtocolServiceProvider.php`

**验证点**：协议相关测试通过

### 阶段 3：Provider 模块（1天）

```bash
php artisan module:make Provider
```

迁移内容：
- `app/Services/Provider/*` → `Modules/Provider/Services/`
- `app/Providers/ProviderServiceProvider.php` → `Modules/Provider/ProviderServiceProvider.php`

**验证点**：Provider 相关测试通过

### 阶段 4：Channel 模块（2天）

```bash
php artisan module:make Channel
```

迁移内容：
- `app/Models/Channel*.php` → `Modules/Channel/Models/`
- `app/Models/ApiKey.php` → `Modules/Channel/Models/`
- `app/Models/ModelList.php` → `Modules/Channel/Models/`
- `app/Models/UserAgent.php` → `Modules/Channel/Models/`
- `app/Services/ChannelAffinity/*` → `Modules/Channel/Services/ChannelAffinity/`
- `app/Enums/Channel*.php` → `Modules/Channel/Enums/`
- `app/Enums/PathPattern.php` → `Modules/Channel/Enums/`
- 渠道相关迁移文件 → `Modules/Channel/Database/Migrations/`

**验证点**：渠道选择功能测试

### 阶段 5：Coding 模块（2天）

```bash
php artisan module:make Coding
```

迁移内容：
- `app/Models/Coding*.php` → `Modules/Coding/Models/`
- `app/Services/CodingStatus/*` → `Modules/Coding/Services/`
- `app/Console/Commands/AutoReopenCodingAccounts.php` → `Modules/Coding/Console/`
- `app/Console/Commands/SyncCodingQuota.php` → `Modules/Coding/Console/`
- `app/Console/Commands/ResetPeriodQuota.php` → `Modules/Coding/Console/`
- `app/Console/Commands/CleanupSlidingWindowData.php` → `Modules/Coding/Console/`
- `app/Http/Middleware/CheckCodingQuota.php` → `Modules/Coding/Http/Middleware/`
- Coding 相关迁移文件 → `Modules/Coding/Database/Migrations/`

**验证点**：配额计算功能测试

### 阶段 6：Search 模块（1天）

```bash
php artisan module:make Search
```

迁移内容：
- `app/Services/Search/*` → `Modules/Search/Services/`
- `app/Providers/SearchServiceProvider.php` → `Modules/Search/SearchServiceProvider.php`

**验证点**：搜索功能测试

### 阶段 7：Proxy 模块（2天）

```bash
php artisan module:make Proxy
```

迁移内容：
- `app/Http/Controllers/Api/ProxyController.php` → `Modules/Proxy/Http/Controllers/`
- `app/Services/Router/*` → `Modules/Proxy/Services/`
- `app/Models/RequestLog.php` → `Modules/Proxy/Models/`
- `app/Models/AuditLog.php` → `Modules/Proxy/Models/`
- `app/Models/ResponseLog.php` → `Modules/Proxy/Models/`
- `app/Models/ResponseSession.php` → `Modules/Proxy/Models/`
- `app/Models/ChannelRequestLog.php` → `Modules/Proxy/Models/`
- `app/Console/Commands/TestProxy*.php` → `Modules/Proxy/Console/`
- `app/Console/Commands/ReplayRequest*.php` → `Modules/Proxy/Console/`
- `app/Console/Commands/AnalyzeRequestDiff.php` → `Modules/Proxy/Console/`
- `app/Console/Commands/ValidateApiRequest.php` → `Modules/Proxy/Console/`
- `app/Console/Commands/TestToolCall.php` → `Modules/Proxy/Console/`
- 日志相关迁移文件 → `Modules/Proxy/Database/Migrations/`
- `routes/api.php` → `Modules/Proxy/Routes/api.php`

**验证点**：核心代理功能完整测试

### 阶段 8：Mcp 模块（0.5天）

```bash
php artisan module:make Mcp
```

迁移内容：
- `app/Mcp/*` → `Modules/Mcp/Mcp/`
- `app/Console/Commands/TestMcpConnection.php` → `Modules/Mcp/Console/`
- `routes/ai.php` → `Modules/Mcp/Routes/ai.php`

**验证点**：MCP 功能测试

### 阶段 9：Admin 模块（2天）

```bash
php artisan module:make Admin
```

迁移内容：
- `app/Admin/*` → `Modules/Admin/Admin/`
- `app/Models/Administrator.php` → `Modules/Admin/Models/`
- `app/Models/User.php` → `Modules/Admin/Models/`
- `app/Models/SystemSetting.php` → `Modules/Admin/Models/`
- `app/Models/OperationLog.php` → `Modules/Admin/Models/`
- `app/Models/PresetPrompt.php` → `Modules/Admin/Models/`
- `app/Models/ModelTestLog.php` → `Modules/Admin/Models/`
- `app/Models/SearchDriver.php` → `Modules/Admin/Models/`
- `app/Models/SearchLog.php` → `Modules/Admin/Models/`
- `app/Models/McpClient.php` → `Modules/Admin/Models/`
- `app/Services/SettingService.php` → `Modules/Admin/Services/`
- `app/Services/ModelService.php` → `Modules/Admin/Services/`
- `app/Services/ModelTestService.php` → `Modules/Admin/Services/`
- `app/Services/OperationLogService.php` → `Modules/Admin/Services/`
- `app/Services/McpClientService.php` → `Modules/Admin/Services/`
- `app/Http/Middleware/SetAdminLocale.php` → `Modules/Admin/Http/Middleware/`
- `app/Http/Middleware/SetUserInfo.php` → `Modules/Admin/Http/Middleware/`
- `app/Enums/SettingGroup.php` → `Modules/Admin/Enums/`
- `app/Enums/Operation*.php` → `Modules/Admin/Enums/`
- Admin 相关迁移文件 → `Modules/Admin/Database/Migrations/`

**验证点**：后台管理功能测试

### 阶段 10：清理优化（1天）

1. 删除 `app/` 目录下已迁移的代码
2. 清理 `app/Providers/` 中已迁移的 ServiceProvider
3. 更新 `composer.json` autoload
4. 更新 `phpunit.xml` 测试路径
5. 运行全部测试套件：`composer test`
6. 性能测试（请求延迟应 <5%）

## 六、风险点与缓解措施

### 风险 1：命名空间变更导致运行时错误

**缓解措施**：
- 使用 `sed` 批量替换时，先在测试环境验证
- 每阶段迁移后立即运行测试套件
- 使用 `php artisan optimize:clear` 清除所有缓存

### 风险 2：Dcat Admin 兼容性问题

**缓解措施**：
- 参考 nengtan_laravel 项目的经验
- Admin 模块保持 Dcat Admin 原有命名空间约定
- BatchAction 使用正确的命名空间和方法名
- 翻译文件放在 `lang/` 目录

### 风险 3：模块间循环依赖

**缓解措施**：
- 严格遵守依赖图，禁止跨层级依赖
- 使用 Event/Listener 解耦
- 定期检查依赖关系

### 风险 4：数据库迁移冲突

**缓解措施**：
- 每个模块的迁移文件保持原有命名
- 迁移时按依赖顺序执行
- 测试环境先验证，生产环境备份数据库

### 风险 5：Shared DTO 多模块依赖

**缓解措施**：
- Core 模块作为最底层，所有模块依赖 Core
- Core 模块保持稳定，避免频繁修改
- DTO 类使用纯数据容器，不含业务逻辑

### 风险 6：测试覆盖不足

**缓解措施**：
- 现有 26 个测试文件同步迁移
- 每阶段迁移后补充模块测试
- 使用 `composer test` 作为强制验证点

## 七、验收标准

1. **功能完整性**：所有 API 端点正常工作
2. **测试通过**：`composer test` 全绿
3. **代码风格**：`vendor/bin/pint --dirty --format agent` 无错误
4. **模块独立性**：`php artisan module:list` 显示 8 个模块
5. **依赖正确**：无循环依赖警告
6. **性能无损**：请求延迟无明显增加（<5%）

## 八、后续优化方向

1. **模块级别测试**：为每个模块创建独立的 PHPUnit 配置
2. **模块文档**：每个模块维护独立的 README.md
3. **模块 API 文档**：使用 Swagger/OpenAPI 生成模块间接口文档
4. **模块版本管理**：每个模块维护独立的 CHANGELOG.md
5. **模块发布**：将稳定模块发布为独立的 Composer 包

---

**创建日期**：2026-07-28
**预计工期**：12 天
**风险等级**：中等