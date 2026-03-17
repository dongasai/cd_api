# CdApi 项目规则

## 项目概述

CdApi 是一个AI大模型API代理工具,基于 Laravel 12 + Filament 4 构建。
本系统是一个大模型Api转发代理工具,客户端将请求发送到本系统后转到到渠道上游.

### 核心功能特性

- **Key 级别模型映射**: 每个 API Key 可配置独立的模型别名映射,支持使用统一别名(如 `cd-coding-latest`)映射到不同的实际模型,实现 Key 级别的模型路由控制

## 技术栈

- **框架**: Laravel 12 (位于 `laravel/` 目录)
- **后台面板**: dcat admin v2
- **PHP版本**: 8.2+
- **数据库**: SQLite (默认)

## 开发规范

### 目录结构

- Laravel 应用位于 `laravel/` 子目录
- 设计文档存放在 `docs/` 目录
- 所有代码开发工作在 `laravel/` 目录下进行

### 前端资源

- **禁止使用 CDN 资源**
- **不使用 Vite 进行资源构建**
- Filament 已包含所需的前端资源

### 数据库操作

- 使用 Eloquent ORM 和模型关系

### 核心表

- request_logs 请求日志
- audit_logs 审计日志
- response_logs 返回日志
- channel_request_logs 渠道请求日志

### 代码风格

- 运行代码格式化: `vendor/bin/pint --dirty --format agent`
- 遵循 Laravel 和 PHP 最佳实践
- 使用 PHP 8+ 特性如构造器属性提升
- 要有中文注释

## 测试规范

### 运行测试

```bash
# 运行所有测试
cd laravel && php artisan test --compact

# 运行特定测试文件
cd laravel && php artisan test --compact tests/Feature/ExampleTest.php

# 运行特定测试方法
cd laravel && php artisan test --compact --filter=testName
```

## 调试工具

- 使用 Laravel Tinker 进行调试: `cd laravel && php artisan tinker`
- 使用 Laravel Boost MCP 工具进行数据库查询和调试

## 工作流程

1. **开发前**: 确保在 `laravel/` 目录下工作
2. **编写代码**: 遵循 Laravel 和 Dcat Admin v2 规范
3. **编写测试**: 为新功能编写完整的测试
4. **代码格式化**: 运行 Pint 格式化代码
5. **运行测试**: 确保所有测试通过
6. **提交代码**: 不要提交敏感信息和配置

> **注意**: 服务器已启动，不需要重新启动。修改代码后 Laravel 会自动生效。

## 注意事项

- 中间件在 `bootstrap/app.php` 中配置
- 服务提供者在 `bootstrap/providers.php` 中注册
- Console 命令放在 `app/Console/Commands/` 会自动注册

## Console命令

项目包含以下自定义Artisan命令:

### 调试与测试命令

- **`proxy:test`** - 测试本站代理 API 连接
    - 支持: `php artisan proxy:test {key?} {model?}`
    - 支持: `--prompt=提示语` 和 `--stream` (流式输出)

- **`proxy:test-anthropic`** - 测试本站 Anthropic 代理 API 连接

- **`proxy:test-tool-call`** - 测试本站代理 API 的工具调用功能

- **`openai:test`** - 使用 openai-php SDK 测试 API 连接
    - 支持: `--key=API密钥`, `--base-url=基础URL`, `--model=模型名`
    - 支持: `--prompt=提示语` 和 `--stream` (流式输出)

### 请求重放命令

- **`request:replay`** - 复现请求(重新发送真实HTTP请求到本系统)
    - 支持: `--request-id=ID` 或 `--audit-id=ID` 或 `--latest` (使用最新审计日志)
    - 支持: `--timeout=超时时间` 和 `--dry-run` (仅显示请求信息)

- **`request:replay-curl`** - 使用 PHP curl 重放请求(直接发送到上游)
    - 支持: `--request-id=ID` 或 `--audit-id=ID`
    - 支持: `--channel-id=渠道ID` 和 `--timeout=超时时间`

- **`request:replay-channel`** - 直接使用渠道驱动重放请求(绕过 ProxyServer)
    - 支持: `--request-id=ID` 或 `--audit-id=ID`
    - 支持: `--channel-id=渠道ID` 和 `--show-body` (显示实际请求体)

- **`request:replay-direct`** - 直接重放请求(不经过 HTTP,直接调用 ProxyServer)
    - 支持: `--request-id=ID` 或 `--audit-id=ID`
    - 支持: `--stream` (强制流式) 和 `--no-stream` (强制非流式)

### 分析命令

- **`analyze:request-diff`** - 分析 request_log 和 channel_request_logs 的请求体差异
    - 用法: `php artisan analyze:request-diff {audit_log_id}`
    - 支持: `--limit=最大条数`, `--show-diff` (显示行级diff), `--diff-chars=字符数`

### Coding账户管理命令

- **`coding:sync-quota`** - 同步Coding账户配额状态
    - 支持: `--account=账户ID` 和 `--platform=平台类型`

- **`coding:check-channels`** - 检查渠道Coding状态并触发调控
    - 支持: `--channel=渠道ID`

- **`coding:auto-reopen`** - 自动重新开启被禁用的Coding账户

- **`coding:cleanup-sliding-window`** - 清理过期滑动窗口数据

- **`coding:reset-period`** - 检查并执行周期配额重置

### 使用示例

```bash
# 测试所有渠道
php artisan channel:test --all

# 重放特定请求
php artisan request:replay --request-id=1234

# 分析请求差异
php artisan analyze:request-diff 5678 --show-diff

# 同步Coding配额
php artisan coding:sync-quota
```
