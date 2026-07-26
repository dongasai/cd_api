# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 项目概述

CdApi 是一个 AI 大模型 API 网关工具，基于 Laravel 12 + Dcat Admin v2 构建。系统作为客户端与上游 AI 服务之间的代理层，提供统一的 API 接入点。

### 核心功能特性

- **Key 级别模型映射**: 每个 API Key 可配置独立的模型别名映射，支持使用统一别名（如 `cd-coding-latest`）映射到不同的实际模型
- **智能路由**: 多渠道负载均衡、故障自动切换、加权随机分发
- **协议转换**: 支持 OpenAI 和 Anthropic Claude Messages API 互相转换
- **渠道亲和性**: 来自指定来源的请求匹配同一渠道
- **安全管控**: 令牌权限管理、模型访问控制、API 调用审计

### API 端点

**OpenAI 兼容端点**:
- `/api/openai/v1/chat/completions` - Chat completions
- `/api/openai/v1/completions` - Legacy completions
- `/api/openai/v1/embeddings` - Embeddings
- `/api/openai/v1/models` - Models list
- `/api/openai/v1/responses` - Responses API

**Anthropic 兼容端点**:
- `/api/anthropic/messages` 或 `/api/anthropic/v1/messages` - Messages API
- `/api/anthropic/models` 或 `/api/anthropic/v1/models` - Models list

## 技术栈

- **框架**: Laravel 12（位于 `laravel/` 目录）
- **后台面板**: Dcat Admin v2
- **PHP 版本**: 8.2+
- **数据库**: SQLite（默认）
- **关键依赖**:
  - `anthropic-ai/sdk`: Anthropic PHP SDK
  - `openai-php/laravel`: OpenAI Laravel 集成
  - `laravel/mcp`: Laravel MCP 支持
  - `opis/json-schema`: JSON Schema 验证

## 目录结构

```
.
├── laravel/              # Laravel 应用主目录
│   ├── app/
│   │   ├── Admin/        # Dcat Admin 控制器
│   │   ├── Http/Controllers/Api/  # API 控制器
│   │   ├── Services/     # 核心业务逻辑
│   │   │   ├── Router/   # ProxyServer 和路由服务
│   │   │   ├── Provider/ # Provider Driver 管理
│   │   │   ├── Protocol/ # Protocol Driver 管理
│   │   │   └── ChannelAffinity/  # 渠道亲和性服务
│   │   └── Models/       # Eloquent 模型
│   ├── routes/           # 路由定义
│   ├── tests/            # 测试
│   │   ├── Unit/         # 单元测试
│   │   ├── Feature/      # 功能测试
│   │   └── E2E/          # 端到端测试
│   └── database/migrations/  # 数据库迁移
├── AiWork/               # 工作文档目录
│   ├── Work.md           # 进度总览（必须维护）
│   └── {年月}/           # 归档文档和日志
└── demo/                 # 演示文件
```

## 核心架构

### 服务层架构

**ProxyServer** (`app/Services/Router/ProxyServer.php`):
- 核心 AI 请求代理服务
- 协调协议转换、渠道选择、响应处理、日志记录、自动重试

**Provider Driver** (`app/Services/Provider/Driver/`):
- 上游服务提供商驱动（OpenAI、Anthropic、Azure 等）
- 通过 `ProviderManager` 管理

**Protocol Driver** (`app/Services/Protocol/Driver/`):
- 协议驱动（OpenAI、Anthropic）
- 处理请求/响应格式转换
- 通过 `ProtocolConverter` 管理

**Channel Router** (`app/Services/Router/ChannelRouterService.php`):
- 渠道选择与路由逻辑
- 支持加权随机、故障转移、渠道亲和性

### 核心数据表

- `request_logs` - 请求日志
- `audit_logs` - 审计日志
- `response_logs` - 响应日志
- `channel_request_logs` - 渠道请求日志
- `channels` - 渠道配置
- `api_keys` - API Key 配置

## 开发工作流

### 常用命令

```bash
# 进入 Laravel 目录
cd laravel

# 安装依赖
composer install

# 运行测试
composer test
# 或
php artisan test

# 运行单个测试文件
php artisan test --filter TestClassName

# 代码格式化
vendor/bin/pint --dirty --format agent

# 进入 Tinker 调试
php artisan tinker

# 查看所有 cdapi 命令
php artisan list | grep cdapi
```

### 测试配置

- PHPUnit 配置: `phpunit.xml`
- 使用内存 SQLite 数据库（`:memory:`）
- 测试隔离: `processIsolation="true"`
- 内存限制: 512M

## 自定义 Artisan 命令

所有项目命令统一使用 `cdapi:` 前缀。

### 请求重放命令

```bash
# 复现请求（重新发送真实HTTP请求到本系统）
php artisan cdapi:request:replay --latest
php artisan cdapi:request:replay --request-id=1234
php artisan cdapi:request:replay --audit-id=5678 --timeout=30

# 使用 PHP curl 重放请求（直接发送到上游）
php artisan cdapi:request:replay-curl --request-id=1234 --channel-id=1

# 直接使用渠道驱动重放请求（绕过 ProxyServer）
php artisan cdapi:request:replay-channel --request-id=1234 --show-body

# 直接重放请求（不经过 HTTP）
php artisan cdapi:request:replay-direct --audit-id=5678 --stream
```

### 分析命令

```bash
# 分析请求体差异
php artisan cdapi:analyze:request-diff {audit_log_id} --show-diff --limit=10
```

### Coding 账户管理命令

```bash
# 同步 Coding 账户配额状态
php artisan cdapi:coding:sync-quota --account=1

# 检查渠道 Coding 状态
php artisan cdapi:coding:check-channels --channel=1

# 自动重新开启被禁用的 Coding 账户
php artisan cdapi:coding:auto-reopen

# 清理过期滑动窗口数据
php artisan cdapi:coding:cleanup-sliding-window

# 检查并执行周期配额重置
php artisan cdapi:coding:reset-period
```

### 备份命令

```bash
# 备份核心表
php artisan cdapi:backup:table --group=core

# 备份指定表
php artisan cdapi:backup:table --tables=request_logs,audit_logs --path=/backup
```

### 测试命令

```bash
# 测试所有渠道
php artisan cdapi:channel:test --all

# 测试 MCP 客户端连接
php artisan cdapi:mcp:test

# 测试 OpenAI SDK 连接
php artisan cdapi:openai:test

# 测试本站代理 API
php artisan cdapi:proxy:test
php artisan cdapi:proxy:test-anthropic
php artisan cdapi:proxy:test-tool-call
```

## 开发规范

### 代码风格

- 遵循 Laravel 和 PHP 最佳实践
- 使用 PHP 8+ 特性（如构造器属性提升）
- 要有中文注释
- 运行 Pint 格式化: `vendor/bin/pint --dirty --format agent`

### 前端资源

- **禁止使用 CDN 资源**
- **不使用 Vite 进行资源构建**
- Dcat Admin 已包含所需的前端资源

### 数据库操作

- 使用 Eloquent ORM 和模型关系
- 创建迁移文件进行数据库变更

### 服务提供者

- 在 `bootstrap/providers.php` 中注册
- Console 命令放在 `app/Console/Commands/` 会自动注册

### 中间件

- 在 `bootstrap/app.php` 中配置

## 调试工具

### Laravel Tinker

```bash
cd laravel
php artisan tinker
```

### Laravel Boost MCP

项目集成了 Laravel Boost MCP 工具，用于数据库查询和调试：

```json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "php",
            "args": ["artisan", "boost:mcp"]
        }
    }
}
```

### 日志查看

- 应用日志: `storage/logs/laravel.log`
- 使用 Laravel Pail 实时查看日志: `php artisan pail`

## Docker 部署

### 生产环境

```bash
# 构建镜像
docker build -t cdapi:latest .

# 运行容器
docker run -d -p 80:80 cdapi:latest
```

### 开发环境

```bash
# 使用开发配置
docker-compose -f docker-compose.dev.yml up -d
```

**Docker 配置说明**:
- 基于 PHP 8.3-apache 镜像
- 包含 Chromium 和 ChromeDriver（用于 Panther 无头浏览器）
- 使用 Supervisor 管理 Apache 和 Queue Worker 进程
- 使用 php 用户运行（UID: 1000）

## 文档管理规则

### 项目文档（图书馆）→ `docs/`, `Modules/{模块}/docs`

- 模块设计文档、技术方案文档
- API 文档、数据库设计文档
- 长期使用的团队文档
- 命名: `{主题}.md`（中文优先）

### 临时追踪文档（工作台）→ `AiWork/`

- 任务进行中: 审查报告、开发记录、问题排查（在 `AiWork/` 无年月）
- 任务已完成: 归档到 `AiWork/{年月}/`（带年月）
- 统一任务必须单文档维护: 全过程在同一文档记录，变更状态
- 命名: `{日-时分-主题}.md`（如 `30-0934-Novel审查报告.md`）

### 工作日志（日志）→ `AiWork/{年月}/`

- 日常工作日志、决策记录、临时事项
- 命名: `{年月}/{日-时分-主题}.md`（如 `202605/01-1030-工作日志.md`）

### 进度总览 → `AiWork/Work.md`

- 工作进度总览，任务追踪，要维护
- 固定文件名: `Work.md`

## 注意事项

- 服务器已启动，不需要重新启动。修改代码后 Laravel 会自动生效
- 工作目录始终在 `laravel/` 目录下
- 不要提交敏感信息和配置（.env 文件已在 .gitignore）
- 使用 `php artisan config:clear` 清除配置缓存
- 使用 `php artisan route:clear` 清除路由缓存
- 使用 `php artisan view:clear` 清除视图缓存

## 相关链接

- 网址: http://192.168.4.107:32126
- GitHub 仓库: 项目使用 Git 进行版本控制