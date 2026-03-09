# CdApi 项目规则

## 项目概述

CdApi 是一个AI大模型API代理工具,基于 Laravel 12 + Filament 4 构建。

### 核心功能特性

- **Key 级别模型映射**: 每个 API Key 可配置独立的模型别名映射,支持使用统一别名(如 `cd-coding-latest`)映射到不同的实际模型,实现 Key 级别的模型路由控制

## 技术栈

- **框架**: Laravel 12 (位于 `laravel/` 目录)
- **后台面板**: Filament 4
- **PHP版本**: 8.2+
- **数据库**: SQLite (默认)

## 开发规范

### 目录结构

- Laravel 应用位于 `laravel/` 子目录
- 设计文档存放在 `docs/` 目录
- 所有代码开发工作在 `laravel/` 目录下进行

### Filament 开发规则

- **重要**: Filament 4 不需要编写视图文件,所有UI通过PHP代码配置
- 使用 Filament Artisan 命令创建资源、页面等组件
- 遵循 Filament 的最佳实践和命名规范

### 前端资源

- **禁止使用 CDN 资源**
- **不使用 Vite 进行资源构建**
- Filament 已包含所需的前端资源

### 数据库操作

- 使用 Eloquent ORM 和模型关系
- 创建模型时同时创建对应的 Factory 和 Seeder
- 使用 Form Request 类进行验证,不要在控制器中直接验证

### 代码风格

- 运行代码格式化: `vendor/bin/pint --dirty --format agent`
- 遵循 Laravel 和 PHP 最佳实践
- 使用 PHP 8+ 特性如构造器属性提升

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

### 测试要求

- 使用 PHPUnit 编写测试 (不使用 Pest)
- 测试应覆盖正常流程、异常流程和边界情况
- 使用模型工厂创建测试数据

## Artisan 命令

### 创建文件

```bash
# 创建模型 (同时创建 migration, factory, seeder)
cd laravel && php artisan make:model ModelName --all

# 创建 Filament Resource
cd laravel && php artisan make:filament-resource ResourceName

# 创建 Form Request
cd laravel && php artisan make:request RequestName

# 创建测试
cd laravel && php artisan make:test TestName           # Feature 测试
cd laravel && php artisan make:test TestName --unit    # Unit 测试
```

### 开发命令

```bash
# 清除缓存
cd laravel && php artisan config:clear
cd laravel && php artisan cache:clear

# 数据库迁移
cd laravel && php artisan migrate
cd laravel && php artisan migrate:refresh --seed

# 启动开发服务器
cd laravel && php artisan serve
```

## 配置管理

- 环境变量只在配置文件中使用 `env()` 函数
- 在代码中使用 `config()` 函数读取配置
- 不要将敏感信息提交到版本控制

## 调试工具

- 使用 Laravel Tinker 进行调试: `cd laravel && php artisan tinker`
- 使用 Laravel Pail 查看日志: `cd laravel && php artisan pail`
- 使用 Laravel Boost MCP 工具进行数据库查询和调试

## 工作流程

1. **开发前**: 确保在 `laravel/` 目录下工作
2. **编写代码**: 遵循 Laravel 和 Filament 规范
3. **编写测试**: 为新功能编写完整的测试
4. **代码格式化**: 运行 Pint 格式化代码
5. **运行测试**: 确保所有测试通过
6. **提交代码**: 不要提交敏感信息和配置

## 注意事项

- Filament 4 使用 Livewire 3,注意版本差异
- 中间件在 `bootstrap/app.php` 中配置
- 服务提供者在 `bootstrap/providers.php` 中注册
- Console 命令放在 `app/Console/Commands/` 会自动注册
