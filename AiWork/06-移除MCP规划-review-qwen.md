# Qwen审阅报告 - 06-移除MCP规划.md

## 一、方案概述

本方案旨在移除项目中两套 MCP 实现：MCP Server（CdApiServer 对外提供 SearchTool/WebParserTool）和 MCP Client（McpClient 模型管理外部连接，BailianSearchDriver 通过它调用百炼搜索）。涉及删除核心文件、迁移文件、composer 依赖，以及修改路由/中间件/枚举/模型等引用方。

## 二、审阅结果摘要

| 类型 | 数量 |
|------|------|
| 🔴 严重问题 | 2个 |
| 🟡 重要问题 | 4个 |
| 🟢 优化建议 | 4个 |

## 三、详细审阅意见

### 3.1 架构设计

**[严重] H1: BailianSearchDriver 替代方案过于模糊**

方案仅说"改为直接调用百炼搜索（不通过 MCP Client）"，但缺少关键技术细节：
- 百炼搜索的 HTTP API 端点是什么？认证方式是什么？
- 当前 MCP Client 存储了连接配置（endpoint、api_key、transport 等），这些配置如何迁移？
- 是否需要新建配置表或在 system_settings 中新增配置项？

**现状分析**：当前 BailianSearchDriver 通过 McpClientService::callTool() 调用 MCP 协议。百炼搜索的实际 HTTP API 是阿里云 DashScope 的 `bailian_web_search` 接口，需要 DashScope API Key。移除 MCP 后，驱动需要直接使用 DashScope HTTP API。

**建议**：明确替代方案的 API 规格、认证方式、配置存储位置，并考虑是否值得保留该驱动（若使用率极低可考虑直接废弃）。

**[重要] M1: 迁移文件处理策略不一致**

方案任务2说"删除 MCP 迁移文件"，任务10说"修改迁移文件中的 MCP 引用"。但实际调查发现：
- **mcp_clients 相关的 3 个迁移文件已从磁盘删除**，但数据库中 mcp_clients 表仍然存在（有迁移记录）
- search_logs 和 seed_system_settings 的迁移文件还在，需要修改

这种"文件已删但表还在"的状态说明之前的清理不完整。对于已删除的 mcp_clients 迁移，需要明确：是保留数据库表不动（仅删除代码），还是需要新建迁移来 DROP TABLE？

**建议**：统一策略 — 新建一个迁移文件：DROP mcp_clients 表 + 删除 search_logs.mcp_client_id 列 + 清理 system_settings 中 group='mcp' 的记录。不修改旧迁移文件（保持迁移历史完整性）。

### 3.2 代码实现

**[严重] H2: 当前代码处于"半删除"状态，路由已断裂**

调查发现实际状态比方案描述的更严重：
- `app/Mcp/` 目录已删除
- `McpClient` 模型、`McpClientService`、`McpClientController`、`TestMcpConnection` 命令、语言文件、mcp 迁移文件 — **全部已不存在**
- 但 `routes/ai.php` 仍引用 `CdApiServer`，`app/Admin/routes.php` 仍注册 `mcp-clients` 路由
- **路由列表已报错**：`McpClientController.php: Failed to open stream: No such file or directory`

这意味着**项目当前已处于崩溃状态**，所有 Artisan 命令（包括 `php artisan route:list`）都无法正常工作。

**建议**：应将"修复当前崩溃状态"作为最高优先级任务，任务1应该调整为"首先修复路由引用，恢复系统正常运行"。

**[重要] M2: SearchLog 模型中 mcp_client_id 的处理需完整**

SearchLog 模型中有大量 MCP 引用：
- `use App\Mcp\Tools\SearchTool;`（文件级导入，类已删除会导致 fatal error）
- `fillable` 数组包含 `mcp_client_id`
- `recordSuccess()` 和 `recordFailure()` 方法签名包含 `$mcpClientId` 参数
- PHPDoc 中多处引用 mcp_client_id 和 SearchTool

**建议**：
1. 移除 `use App\Mcp\Tools\SearchTool;` 导入
2. 从 `fillable` 移除 `mcp_client_id`
3. `recordSuccess/recordFailure` 方法中移除 `$mcpClientId` 参数（注意：这是公共方法签名变更，需检查所有调用方）
4. 更新 PHPDoc

**[重要] M3: 方案遗漏了 SearchLog 对 SearchTool 的导入引用**

方案提到修改 SearchLog 模型"移除 mcp_client_id 相关字段和方法"，但遗漏了第5行的 `use App\Mcp\Tools\SearchTool;`。这个导入引用的类已被删除，会导致类加载时 fatal error。

### 3.3 性能

**[优化] L1: BailianSearchDriver 替代方案应考虑缓存**

当前 MCP 方式有连接池复用。改为直接 HTTP 调用后，每次搜索都需建立新连接。建议：
- 使用 Laravel HTTP Client 的连接池
- 考虑对高频搜索查询添加缓存层

### 3.4 安全

**[优化] L2: system_settings 中 webparser_api_key 的处理**

seed_system_settings 迁移中 `mcp.webparser_api_key` 存储了 API 密钥。移除 MCP 组后，WebParserService 仍需这个配置。方案提到将 `mcp.*` 改为 `webparser.*`，但：
- 现有数据库中已有 `mcp.*` 的配置数据
- 需要数据迁移：将 `group='mcp'` 的记录改为 `group='webparser'`（或其他新组名）

**建议**：新建迁移中处理配置数据迁移，而非仅修改代码中的配置键名。

### 3.5 测试

**[重要] M4: 方案缺少测试验证步骤**

任务11仅说"运行测试"，但应明确：
- 是否有现有测试覆盖 BailianSearchDriver、SearchLog、WebParserService？
- 修改后是否需要新增测试验证替代的 HTTP 调用？
- 搜索功能的 E2E 测试是否需要更新？

**建议**：在任务清单中增加"检查并更新相关测试用例"步骤。

### 3.6 可维护性

**[优化] L3: 任务执行顺序应调整**

当前任务顺序存在问题：先删文件（任务1-2），再改路由（任务4）。但路由已经引用了已删除的文件导致崩溃。

**建议执行顺序**：
1. **首先**：修复路由引用（删除 routes/ai.php 中的 MCP 路由，删除 admin routes 中的 mcp-clients 路由）→ 恢复系统正常
2. **然后**：修改 BailianSearchDriver（核心功能替代）
3. **然后**：修改 SearchLog、WebParserService、MockSearchDriver、SettingGroup 等
4. **然后**：移除 composer 依赖
5. **最后**：新建迁移清理数据库（DROP TABLE、DROP COLUMN、清理 settings）

**[优化] L4: 建议保留 `routes/ai.php` 文件但清空内容**

完全删除 `routes/ai.php` 可能需要在 `bootstrap/app.php` 或路由注册处同步修改。更安全的方式是清空文件内容保留空文件，或者确认路由注册逻辑会自动跳过缺失文件。

## 四、问题详情

### 4.1 [严重问题 H1] BailianSearchDriver 替代方案缺少技术规格

**现状**：BailianSearchDriver 通过 MCP 协议调用百炼搜索，核心代码：
```php
$result = $this->mcpService->callTool($client, 'bailian_web_search', $arguments);
```

**问题**：方案未明确替代的 HTTP API 规格。百炼搜索通过 DashScope API 提供，需要明确：
- API 端点和请求格式
- 认证方式（DashScope API Key）
- 请求参数和返回格式映射

**影响**：开发者无法按当前方案实施，需要额外调研。

**建议**：补充百炼搜索 HTTP API 的调用方式、参数映射、返回格式解析。

### 4.2 [严重问题 H2] 项目当前处于崩溃状态

**证据**：
```
php artisan route:list
→ include(app/Admin/Controllers/McpClientController.php): Failed to open stream
```

**原因**：Admin 路由注册了 `McpClientController`，但控制器文件已不存在。

**影响**：所有 Artisan 命令无法执行，包括 `php artisan migrate`、`php artisan config:clear` 等关键运维命令。

**建议**：此问题应作为"任务0"立即修复，优先于所有其他任务。

### 4.3 [重要问题 M1] 迁移策略不一致

**现状**：
- mcp_clients 迁移文件已删除，但表仍在数据库中（3条迁移记录）
- 方案说"删除迁移文件"又说"修改迁移文件"

**建议**：
- 不修改已执行的迁移文件（违反迁移不可变原则）
- 新建迁移：`drop_mcp_clients_table`、`remove_mcp_client_id_from_search_logs`、`remove_mcp_settings`

### 4.4 [重要问题 M2] SearchLog 模型包含致命引用

`use App\Mcp\Tools\SearchTool;` — 这个导入的类文件已不存在，当 SearchLog 模型被加载时会触发 class not found 错误。

### 4.5 [重要问题 M3] 方案遗漏 SearchTool 导入

方案中 SearchLog 修改范围描述不完整。

### 4.6 [重要问题 M4] 缺少测试更新计划

搜索功能、WebParser 功能的测试需同步更新。

### 4.7 [优化建议 L1] BailianSearchDriver HTTP 调用应使用连接池

### 4.8 [优化建议 L2] 配置数据需迁移

`system_settings` 表中 `group='mcp'` 的 4 条记录（webparser 配置）需要迁移到新组名，否则 WebParserService 读取新配置键名时找不到数据。

### 4.9 [优化建议 L3] 任务执行顺序需调整

优先修复路由崩溃，再处理其他清理工作。

### 4.10 [优化建议 L4] routes/ai.php 处理方式

建议确认路由注册逻辑后决定是删除文件还是清空内容。

## 五、遗漏的MCP引用清单

以下为方案中**未提及**但实际存在于代码中的 MCP 引用：

| 文件 | 行号 | 引用内容 | 严重程度 |
|------|------|----------|----------|
| `app/Models/SearchLog.php` | 5 | `use App\Mcp\Tools\SearchTool;` | 🔴 致命 |
| `app/Models/SearchLog.php` | 78 | `@see SearchTool MCP搜索工具` | 🟡 注释 |
| `app/Models/SearchLog.php` | 158-159 | `$mcpClientId` 参数 PHPDoc | 🟡 文档 |
| `app/Models/SearchLog.php` | 206-207 | `$mcpClientId` 参数 PHPDoc | 🟡 文档 |
| `app/Services/Search/Driver/BailianSearchDriver.php` | 全文 | 整个类依赖 McpClient 和 McpClientService | 🔴 核心 |
| `app/Services/Search/Driver/MockSearchDriver.php` | 50-54 | 模拟数据包含 MCP 服务介绍条目 | 🟢 低 |
| `app/Http/Middleware/AuthenticateApiKey.php` | 22-31 | `mcp/*` 路径调试日志 | 🟡 中 |
| `database/migrations/2026_07_30_205349_seed_system_settings.php` | 43-47 | MCP 组 seed 数据（4条记录） | 🟡 中 |
| `database/migrations/2026_07_30_205349_seed_system_settings.php` | 60 | down() 中包含 'mcp' 组 | 🟢 低 |
| `database/migrations/2026_04_06_153501_create_search_logs_table.php` | 31 | `mcp_client_id` 列定义 | 🟡 中 |
| `composer.lock` | 多处 | `laravel/mcp` 和 `mcp/sdk` 锁定版本 | 🟡 中 |

**注意**：`laravel/ai` 包（v0.4.3）的 require-dev 中包含 `laravel/mcp`，但这仅是开发依赖，不影响生产环境移除。

## 六、整体评价与总结

### 总体评分：6/10

### 优点
1. ✅ 文件清单覆盖较全面，核心 MCP 文件均已识别
2. ✅ 关键约束识别正确（BailianSearchDriver 替代方案、WebParserService 配置重命名）
3. ✅ 正确保留了 `.mcp.json` 和 `boost.json`（非项目功能）
4. ✅ 任务分解粒度合理，有验证步骤

### 主要风险
1. 🔴 **项目当前已崩溃** — 路由引用已删除的控制器，需立即修复
2. 🔴 **BailianSearchDriver 替代方案不可执行** — 缺少 HTTP API 规格
3. 🟡 **数据库清理策略不明确** — 迁移文件已删但表还在，需要新建迁移
4. 🟡 **SearchLog 的 SearchTool 导入会导致 fatal error** — 方案遗漏

### 建议的修正方向

1. **增加"任务0：紧急修复"** — 删除 routes/ai.php 中 MCP 路由 + admin routes 中 mcp-clients 路由，恢复系统可用
2. **细化 BailianSearchDriver 改造方案** — 明确 DashScope HTTP API 调用方式，或者决定直接废弃该驱动
3. **统一迁移策略** — 不修改已执行迁移，新建迁移处理数据库清理
4. **补充配置数据迁移** — system_settings 中 mcp 组的 4 条记录需迁移到新组
5. **调整执行顺序** — 修复崩溃 → 核心功能替代 → 代码清理 → 依赖移除 → 数据库清理
