# Actions 目录划分重构

## 重构时间
2026-07-28

## 重构目的
将 `app/Admin/Actions/` 目录下的 Action 文件按功能模块进行组织，提高代码可维护性和可读性。

## 目录结构

重构后的目录结构：

```
app/Admin/Actions/
├── ApiKey/              # API密钥管理
│   └── ResetApiKey.php
├── Cache/               # 缓存管理
│   ├── RefreshModelCache.php
│   └── RefreshSettingCache.php
├── Channel/             # 渠道相关操作
│   ├── CopyChannel.php
│   ├── CopyChannelModel.php
│   ├── CopyChannelAffinityRule.php
│   ├── ViewChannelRequestLog.php
│   └── ViewAffinityHit.php
├── Log/                 # 日志查看
│   ├── ViewRequestLog.php
│   ├── ViewResponseLog.php
│   └── CompareRequestDiff.php
├── McpClient/           # MCP客户端
│   └── TestMcpConnection.php
└── Migration/           # 数据库迁移
    ├── MigrateAll.php
    ├── MigrateOne.php
    ├── BatchMigrate.php
    └── RollbackOne.php
```

## 命名空间变更

所有 Action 类的命名空间从 `App\Admin\Actions` 更新为 `App\Admin\Actions\{子目录}`：

| 原命名空间 | 新命名空间 | 文件数 |
|-----------|-----------|-------|
| `App\Admin\Actions` | `App\Admin\Actions\Channel` | 5 |
| `App\Admin\Actions` | `App\Admin\Actions\Migration` | 4 |
| `App\Admin\Actions` | `App\Admin\Actions\Log` | 3 |
| `App\Admin\Actions` | `App\Admin\Actions\McpClient` | 1 |
| `App\Admin\Actions` | `App\Admin\Actions\Cache` | 2 |
| `App\Admin\Actions` | `App\Admin\Actions\ApiKey` | 1 |

## 影响的文件

### Controller 文件（use 语句更新）

以下 Controller 的 use 语句已更新：

- `app/Admin/Controllers/MigrationController.php` - 4个 Action
- `app/Admin/Controllers/ChannelController.php` - 1个 Action
- `app/Admin/Controllers/ChannelAffinityRuleController.php` - 1个 Action
- `app/Admin/Controllers/ChannelModelController.php` - 1个 Action
- `app/Admin/Controllers/AuditLogController.php` - 5个 Action
- `app/Admin/Controllers/McpClientController.php` - 1个 Action
- `app/Admin/Controllers/ApiKeyController.php` - 2个 Action
- `app/Admin/Controllers/SystemSettingController.php` - 1个 Action

## 划分原则

按功能模块划分，便于：

1. **功能定位** - 根据模块快速定位相关 Action
2. **职责分离** - 不同模块的 Action 物理隔离
3. **团队协作** - 不同团队成员可在不同子目录工作，减少冲突
4. **代码维护** - 相关功能的 Action 集中管理，便于重构和优化

## 后续规范

新增 Action 时，应根据功能模块放入对应子目录：

- 渠道相关 → `Channel/`
- 迁移相关 → `Migration/`
- 日志查看 → `Log/`
- MCP客户端 → `McpClient/`
- 缓存管理 → `Cache/`
- API密钥 → `ApiKey/`

如果新功能不属于以上任何模块，可创建新的子目录。

## 验证

- ✅ 所有 Action 文件已移动到对应子目录
- ✅ 所有命名空间已更新
- ✅ 所有 Controller 的 use 语句已更新
- ✅ 代码格式化已执行（Pint）
- ✅ 无旧的命名空间引用

## 注意事项

此次重构仅涉及文件位置和命名空间，不影响功能逻辑。