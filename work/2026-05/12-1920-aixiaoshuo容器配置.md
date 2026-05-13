# AI小说项目容器配置分析

**项目路径**: `/data/project/aixiaoshuo`
**生成日期**: 2026-05-12

---

## 一、配置文件清单

| 文件 | 位置 | 用途 |
|------|------|------|
| [docker-compose.yml](docker-compose.yml) | 根目录 | 主开发环境配置 |
| [Dockerfile](moyuanai/Dockerfile) | moyuanai/ | 生产环境镜像 |
| [Dockerfile.dev](moyuanai/Dockerfile.dev) | moyuanai/ | 开发环境镜像 |
| [docker-compose.prod.yml](moyuanai/docker-compose.prod.yml) | moyuanai/ | 生产编排配置 |
| [.devcontainer/docker-compose.yml](moyuanai/.devcontainer/docker-compose.yml) | moyuanai/.devcontainer/ | VSCode DevContainer |
| [.dockerignore](moyuanai/.dockerignore) | moyuanai/ | 构建排除规则 |

---

## 二、端口分配

| 容器 | Web端口 | SSH端口 | 状态 |
|------|---------|---------|------|
| xiaoshuo-dev | 36126 | 32217 | 主开发容器 |
| xiaoshuo-dev (DevContainer) | 36126 | 30222 | VSCode远程开发 |
| laravel_module_all | 34007 | - | 生产测试容器 |

---

## 三、镜像详解

### 3.1 Dockerfile (生产镜像)

**基础镜像**: `php:8.3-apache`

**核心组件**:
- PHP 8.3 + Apache
- Xdebug 调试扩展
- Supervisor 进程管理
- SSH Server (端口22)
- Node.js (LTS)
- Rust 工具链 (stable)
- Claude Code CLI + Intelephense
- Git, tmux, zsh

**PHP扩展**:
```text
pdo, pdo_sqlite, pdo_mysql, mbstring, exif, pcntl, bcmath, gd, zip, sockets, intl
```

**用户配置**:
- 用户: `php` (UID:1000)
- 用户组: `php` (GID:1000)
- sudo权限: apt-get/apt/dpkg/ln (无密码)

### 3.2 Dockerfile.dev (开发镜像)

**基础镜像**: `php:8.3-apache`

**精简组件**:
- PHP 8.3 + Apache
- Node.js (LTS)
- Composer

**无包含**:
- Xdebug
- SSH Server
- Supervisor
- Rust
- zsh/tmux

---

## 四、编排配置详解

### 4.1 主开发配置 (docker-compose.yml)

```yaml
services:
  xiaoshuo-dev:
    image: xiaoshuo-dev:latest
    container_name: xiaoshuo-dev-container
    ports:
      - "36126:80"   # Web
      - "32217:22"   # SSH
    user: root        # Supervisor需root运行
    command: supervisord -c /etc/supervisor/conf.d/supervisord.conf
    restart: unless-stopped
```

**卷挂载**:
- 项目代码: `./ -> /data/project/aixiaoshuo/`
- Composer缓存: 全局共享volume `composer-cache`
- Claude配置: `${HOME}/.claude`, `${HOME}/.claude.json`
- SSH Agent: `${SSH_AUTH_SOCK} -> /ssh-agent`
- SSH配置: 专用volume `xiaoshuo-ssh-config`
- Git配置: 只读挂载 `${HOME}/.gitconfig`

### 4.2 DevContainer配置

```yaml
services:
  devcontainer:
    container_name: xiaoshuo-dev
    ports:
      - "36126:80"
      - "30222:22"
    user: php         # 开发模式用php用户
    working_dir: /data/project/aixiaoshuo/moyuanai
    restart: always
```

---

## 五、构建排除规则 (.dockerignore)

**核心排除目录** (减少构建上下文大小):
- `node_modules/**` - Node依赖
- `vendor/**` - PHP依赖
- `rust-consumer/**` - Rust编译输出(3.8GB)
- `storage/logs/**` - 日志目录
- `AiWork/**` - AI工作目录

**其他排除**:
- `.git`, `.vscode`, `.idea`
- `tests/`, `docs/`, `*.md`
- `Dockerfile*`, `docker-compose*`

---

## 六、配置文件原文

### 6.1 docker-compose.yml

```yaml
version: '3.8'

services:
  xiaoshuo-dev:
    build:
      context: moyuanai
      dockerfile: Dockerfile.dev
    image: xiaoshuo-dev:latest
    container_name: xiaoshuo-dev-container
    ports:
      - "36126:80"
      - "32217:22"
    working_dir: /data/project/aixiaoshuo/moyuanai
    user: root
    environment:
      - SSH_AUTH_SOCK=/ssh-agent
      - TZ=Asia/Shanghai
    volumes:
      - ./:/data/project/aixiaoshuo/
      - composer-cache:/home/php/.composer
      - ${HOME}/.claude:/home/php/.claude
      - ${HOME}/.claude.json:/home/php/.claude.json
      - ${SSH_AUTH_SOCK:-/tmp/ssh-agent.sock}:/ssh-agent
      - xiaoshuo-ssh-config:/home/php/.ssh
      - ${HOME}/.gitconfig:/home/php/.gitconfig:ro
      - ./moyuanai/docker/000-default.local.conf:/etc/apache2/sites-available/000-default.conf:ro
      - ./moyuanai/docker/supervisord.local.conf:/etc/supervisor/conf.d/supervisord.conf:ro
    command: supervisord -c /etc/supervisor/conf.d/supervisord.conf
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s
    restart: unless-stopped

volumes:
  composer-cache:
    name: composer-cache
  xiaoshuo-ssh-config:
    name: xiaoshuo-ssh-config
```

### 6.2 Dockerfile (生产)

```dockerfile
# 开发容器 - 基于 Apache + PHP 8.3 + Xdebug + Node.js
FROM php:8.3-apache

ENV TZ=Asia/Shanghai
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

WORKDIR /var/www/html

# 配置国内镜像加速器
RUN echo "🔧 配置国内镜像加速器..." && \
    sed -i 's/deb.debian.org/mirrors.aliyun.com/g' /etc/apt/sources.list.d/debian.sources && \
    sed -i 's/security.debian.org/mirrors.aliyun.com/g' /etc/apt/sources.list.d/debian.sources && \
    echo "✅ APT 源已切换为阿里云镜像"

RUN apt-get update
RUN apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev libzip-dev zip unzip \
    sqlite3 libsqlite3-dev libicu-dev icu-devtools \
    libjpeg-dev libfreetype6-dev libwebp-dev \
    supervisor protobuf-compiler zsh zsh-common openssh-server tmux tree \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install pdo pdo_sqlite pdo_mysql mbstring exif pcntl bcmath gd zip sockets intl

RUN pecl install xdebug && docker-php-ext-enable xdebug

COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

RUN a2enmod rewrite headers

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

# SSH 配置
RUN mkdir -p /var/run/sshd && \
    sed -i 's/#PermitRootLogin prohibit-password/PermitRootLogin yes/' /etc/ssh/sshd_config && \
    sed -i 's/#PasswordAuthentication yes/PasswordAuthentication yes/' /etc/ssh/sshd_config

# php 用户
RUN groupadd -r -g 1000 php && \
    useradd -r -u 1000 -g php -s /bin/bash -d /home/php php && \
    mkdir -p /home/php && chown -R php:php /home/php

# apt 权限
RUN apt-get update && apt-get install -y sudo && \
    echo "php ALL=(ALL) NOPASSWD: /usr/bin/apt-get, /usr/bin/apt, /usr/bin/dpkg, /bin/ln" >> /etc/sudoers

RUN usermod -a -G www-data php && \
    mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache && \
    chown -R php:php /var/www/html/storage /var/www/html/bootstrap/cache

# Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - && \
    apt-get install -y nodejs

# Claude Code CLI
RUN npm install -g @anthropic-ai/claude-code intelephense

# Rust 工具链
ENV RUSTUP_HOME=/usr/local/rustup CARGO_HOME=/usr/local/cargo
RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y --default-toolchain stable --no-modify-path && \
    chmod -R a+rwX /usr/local/rustup /usr/local/cargo

USER php
ENV APACHE_RUN_USER=php

EXPOSE 80 22
```

### 6.3 Dockerfile.dev (开发)

```dockerfile
FROM php:8.3-apache

WORKDIR /var/www/html

RUN sed -i 's/deb.debian.org/mirrors.aliyun.com/g' /etc/apt/sources.list.d/debian.sources && \
    sed -i 's/security.debian.org/mirrors.aliyun.com/g' /etc/apt/sources.list.d/debian.sources

RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev libzip-dev zip unzip \
    sqlite3 libsqlite3-dev libzip-dev libicu-dev icu-devtools

RUN docker-php-ext-install pdo pdo_sqlite pdo_mysql mbstring exif pcntl bcmath gd zip sockets zip intl

COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

RUN a2enmod rewrite headers

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Apache 配置
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# php 用户
RUN groupadd -r -g 1000 php && \
    useradd -r -u 1000 -g php -s /bin/bash -d /home/php php && \
    mkdir -p /home/php && chown -R php:php /home/php

RUN apt-get update && apt-get install -y sudo && \
    echo "php ALL=(ALL) NOPASSWD: /usr/bin/apt-get, /usr/bin/apt, /usr/bin/dpkg" >> /etc/sudoers

RUN usermod -a -G www-data php && \
    mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache && \
    chown -R php:php /var/www/html/storage /var/www/html/bootstrap/cache

# Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - && \
    apt-get install -y nodejs

USER php
```

---

## 七、总结

该项目容器配置分为两个层次：

1. **生产镜像 (Dockerfile)**: 完整开发环境，包含 Xdebug、SSH、Supervisor、Rust、Claude Code CLI，适合远程开发和调试
2. **开发镜像 (Dockerfile.dev)**: 精简环境，仅包含 PHP + Apache + Node.js，适合快速迭代

端口 36126 为 Web 服务固定端口，可通过 http://192.168.4.107:36126 访问。