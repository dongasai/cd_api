# Dev Container 配置指南

## 核心原则

1. **使用 localWorkspaceFolder 保持目录一致性**
   - 容器内项目路径与宿主机路径完全一致
   - 配置：`workspaceFolder: "${localWorkspaceFolder}"`
   - 配置：`workspaceMount: "source=${localWorkspaceFolder},target=${localWorkspaceFolder},type=bind"`
   - 优势：无需额外符号链接，IDE 和容器共享同一文件系统视图

2. **配置文件使用项目路径**
   - 复制 `docker/000-default.conf` 为 `docker/000-default.local.conf`
   - 复制 `docker/supervisord.conf` 为 `docker/supervisord.local.conf`
   - **使用你的实际项目路径**（例如 `/path/to/your/project`）
   - 原因：Apache/Supervisor 配置文件不支持环境变量替换
   - 映射到容器：
     - Apache: `docker/000-default.local.conf` → `/etc/apache2/sites-available/000-default.conf`
     - Supervisor: `docker/supervisord.local.conf` → `/etc/supervisor/conf.d/supervisord.conf`
   - **注意**：配置文件中的路径应与 `workspaceFolder` 保持一致

3. **服务自动启动**
   - 使用 `postStartCommand` 自动启动 Apache 和 Supervisor
   - 命令：`sudo service apache2 start && sudo service supervisor start`

## 配置文件详解

### Apache 配置 (docker/000-default.local.conf)

```apache
<VirtualHost *:80>
    DocumentRoot /path/to/your/project/public
    <Directory /path/to/your/project/public>
        AllowOverride All
        Require all granted
    </Directory>

    # 开发环境配置
    ErrorLog /path/to/your/project/storage/logs/apache-error.log
    CustomLog /path/to/your/project/storage/logs/apache-access.log combined
</VirtualHost>
```

**关键点：**
- DocumentRoot 指向项目 `public` 目录（替换 `/path/to/your/project` 为你的实际路径）
- 错误日志写入项目 `storage/logs` 目录
- `AllowOverride All` 支持 `.htaccess`
- **路径必须与 devcontainer.json 中的 workspaceFolder 一致**

### Supervisor 配置 (docker/supervisord.local.conf)

```ini
[supervisord]
nodaemon=true
user=root

[program:init-permissions]
command=bash -c "mkdir -p /path/to/your/project/storage/logs ..."
directory=/path/to/your/project

[program:queue-worker]
command=php artisan queue:listen -v --sleep=3 --tries=3
directory=/path/to/your/project
user=php
```

**关键点：**
- `nodaemon=true` 让 Supervisor 在前台运行（容器模式）
- `user=php` 确保队列进程以正确用户运行
- 所有路径替换为你的实际项目路径
- **路径必须与 devcontainer.json 中的 workspaceFolder 一致**

## 注意事项

### 1. 路径配置策略

**❌ 不要在配置文件中使用变量：**
- `${localWorkspaceFolder}` - Apache/Supervisor 不支持环境变量
- `${WORKSPACE}` - 需要额外变量注入机制
- `__WORKSPACE__` - 需要动态替换脚本

**✅ 推荐策略：**
- 在配置文件中使用实际项目路径（如 `/path/to/your/project`）
- 该路径应与 `workspaceFolder` 保持一致
- 简单、可靠、无需额外处理
- **安全提示**：不要在公开文档中暴露你的具体项目路径

### 2. postCreateCommand vs postStartCommand

- **postCreateCommand**：容器创建时执行（首次构建）
  - 用途：安装依赖、初始化项目（composer install, key:generate）
  - 执行一次后缓存

- **postStartCommand**：每次容器启动时执行
  - 用途：启动服务（apache2, supervisor）
  - 每次启动都会执行

### 3. 文件映射策略

```json
"mounts": [
    // 配置文件映射（readonly 防止容器修改宿主机配置）
    "source=${localWorkspaceFolder}/docker/000-default.local.conf,target=/etc/apache2/sites-available/000-default.conf,type=bind,readonly",
    "source=${localWorkspaceFolder}/docker/supervisord.local.conf,target=/etc/supervisor/conf.d/supervisord.conf,type=bind,readonly"
]
```

**readonly 的好处：**
- 容器内无法意外修改宿主机配置文件
- 配置文件修改在宿主机进行，容器重启生效
- 符合"配置源在宿主机"的原则

### 4. 常见问题排查

#### Apache 启动失败
```bash
# 检查配置语法
sudo apache2ctl configtest

# 查看错误日志（替换为你的项目路径）
tail -f /path/to/your/project/storage/logs/apache-error.log

# 检查进程状态
ps aux | grep apache2
```

#### Supervisor 进程异常
```bash
# 查看 Supervisor 状态
sudo supervisorctl status

# 重启进程
sudo supervisorctl restart all

# 查看队列日志（替换为你的项目路径）
tail -f /path/to/your/project/storage/logs/queue-worker.log
```

#### Laravel 应用错误
```bash
# 检查日志（替换为你的项目路径）
tail -f /path/to/your/project/storage/logs/laravel.log

# 运行迁移（如果缺少数据库表）
php artisan migrate

# 清除缓存
php artisan cache:clear
php artisan config:clear
```

### 5. 环境验证清单

启动后验证：
```bash
# 1. 检查当前用户
whoami  # 应显示: php

# 2. 检查 PHP 版本
php -v  # PHP 8.3.x

# 3. 检查 Laravel
php artisan --version

# 4. 检查 Apache
curl -I http://localhost/  # 应返回 HTTP 响应

# 5. 检查 Supervisor
sudo supervisorctl status  # 应显示进程运行状态

# 6. 检查项目路径（应显示你的项目路径）
pwd  # 例如: /path/to/your/project
ls -la public/  # 应能看到 Laravel public 目录
```

### 6. Dev Container CLI 测试

使用 `devcontainer` 命令行工具测试：

```bash
# 构建镜像
devcontainer build --workspace-folder /www/devroot/laravel_php

# 启动容器
devcontainer up --workspace-folder /www/devroot/laravel_php --remove-existing-container

# 在容器内执行命令
devcontainer exec --workspace-folder /www/devroot/laravel_php whoami
devcontainer exec --workspace-folder /www/devroot/laravel_php php -v
```

### 7. VSCode 中使用

在 VSCode 中启动 Dev Container：
1. 按 `F1` 打开命令面板
2. 输入：`Dev Containers: Rebuild and Reopen in Container`
3. 观察输出窗口（Dev Containers 频道）
4. 等待启动完成（约 2-5 分钟）

### 8. 禁止事项

- ❌ 不要修改容器内 `/etc/apache2` 或 `/etc/supervisor` 配置文件
  - 这些文件从宿主机映射，readonly 保护
  - 修改应在宿主机 `docker/*.local.conf` 文件中进行

- ❌ 不要在容器内运行 `apt-get install` 安装系统包
  - 容器用户 `php` 有 apt 权限，但不应滥用
  - 如需安装包，应修改 `Dockerfile.dev`

- ❌ 不要在容器内修改 `.devcontainer/devcontainer.json`
  - 该文件在宿主机，容器内修改会同步到宿主机
  - 可能导致配置混乱

### 9. 性能优化

- **Composer 缓存**：使用 Volume 缓存，避免每次重新下载
  ```json
  "source=laravel-composer-cache,target=/home/php/.composer,type=volume"
  ```

- **项目代码**：bind mount 模式，实时同步宿主机修改
  ```json
  "source=${localWorkspaceFolder},target=${localWorkspaceFolder},type=bind"
  ```

- **配置文件**：readonly + bind mount，减少 IO 开销

### 10. 安全建议

- SSH agent socket 映射：仅传递认证，不传递密钥文件
- Git config 映射：readonly，防止容器修改 Git 配置
- Claude 配置映射：方便开发但注意保护敏感信息

## 配置文件路径

| 宿主机文件 | 容器内路径 | 说明 |
|-----------|----------|------|
| `.devcontainer/devcontainer.json` | 容器启动配置 | 定义容器构建、启动命令 |
| `docker/000-default.local.conf` | `/etc/apache2/sites-available/000-default.conf` | Apache 虚拟主机配置 |
| `docker/supervisord.local.conf` | `/etc/supervisor/conf.d/supervisord.conf` | Supervisor 进程管理配置 |
| `Dockerfile.dev` | 构建镜像 | 定义容器基础环境 |

## 测试验证记录

最近测试时间：2026-04-29

**测试结果：**
- ✅ 容器启动成功
- ✅ Apache 正常运行
- ✅ Supervisor 正常运行
- ✅ PHP 8.3 + Laravel 12 环境正确
- ⚠️ 应用层 HTTP 500（数据库迁移问题，非容器问题）

**测试命令：**
```bash
devcontainer up --workspace-folder /www/devroot/moyuan/laravel_php --remove-existing-container
devcontainer exec --workspace-folder /www/devroot/moyuan/laravel_php curl -I http://localhost/
```

## 维护指南

当项目路径变化时：
1. 修改 `docker/000-default.local.conf` 中所有路径
2. 修改 `docker/supervisord.local.conf` 中所有路径
3. 确保路径与 `workspaceFolder` 保持一致
4. 重建容器：`devcontainer up --remove-existing-container`

当添加新服务时：
1. 在 `docker/supervisord.local.conf` 添加 `[program:xxx]` 配置
2. 使用项目路径配置 `directory` 和日志路径
3. 重启容器或手动重启 Supervisor：`sudo supervisorctl restart all`

**安全提示：**
- 不要在公开的文档、博客、代码仓库中暴露你的具体项目路径
- 使用通用示例路径 `/path/to/your/project` 或相对路径说明
- 私有项目内部文档可以使用实际路径，但要控制访问权限