# CdApi VS Code 开发容器

## 快速启动

1. 在 VS Code 中打开项目目录
2. 按 `F1` 或 `Ctrl+Shift+P` 打开命令面板
3. 选择 **Dev Containers: Reopen in Container**
4. 等待容器构建完成

## 配置说明

### 容器配置

- **Dockerfile**: `Dockerfile.Dev`
- **Compose文件**: `compose.yml`
- **工作目录**: `/data/project/ai_proxy/coding_api/laravel`
- **运行用户**: `php` (UID:1000)

### 端口

| 服务 | 容器端口 | 主机端口 |
|------|----------|----------|
| Apache | 80 | 36126 |
| SSH | 22 | 36133 |

### 预装 VS Code 扩展

**PHP/Laravel 开发**:
- Intelephense (PHP 智能补全)
- Laravel Extra Intellisense
- Laravel Artisan
- Laravel Blade
- Laravel Goto
- Laravel Snippets
- PHP Debug (Xdebug)

**通用开发**:
- Docker
- Git Graph
- GitLens
- EditorConfig
- Prettier
- ESLint
- Error Lens
- TODO Tree
- Markdownlint

### 终端配置

默认使用 **zsh** shell，预装 Oh My Zsh。

### SSH Agent 转发

支持 SSH Agent 转发，可在容器内使用宿主机的 SSH 密钥进行 Git 操作。

## 使用方式

### 启动容器

```bash
# VS Code 命令面板
Dev Containers: Reopen in Container
```

### SSH 连接

```bash
# 设置密码
docker exec -it coding_proxy_dev passwd php

# 连接
ssh -p 36133 php@192.168.4.107
```

### Laravel 命令

```bash
# 在容器终端执行
php artisan migrate
php artisan queue:work
composer install
```

### 权限修复

如果遇到权限问题，执行：

```bash
sudo chown -R php:php storage bootstrap/cache
```

## 共享 Volumes

| Volume | 用途 |
|--------|------|
| `composer-cache` | Composer 缓存，多容器共享 |
| `ssh-config` | SSH 配置，多容器共享 |

## 注意事项

1. **Supervisor 管理服务**: Apache、SSH、Queue Worker、Schedule Worker 均由 Supervisor 管理
2. **用户权限**: 容器以 `php` 用户运行，部分命令需要 `sudo`
3. **代码挂载**: 整个项目目录挂载到 `/data/project/ai_proxy/coding_api/`
4. **日志位置**: `/data/project/ai_proxy/coding_api/laravel/storage/logs/`