# php容器 - 基于 Apache + PHP 8.3
FROM php:8.3-apache

# 设置工作目录
WORKDIR /var/www/html

# 安装系统依赖 (仅必需的)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    libzip-dev \
    libicu-dev \
    icu-devtools \
    supervisor && \
    rm -rf /var/lib/apt/lists/*
# 增加 node
RUN echo "🔧 安装 Node.js..." && \
    curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - && \
    apt-get install -y nodejs && \
    echo "✅ Node.js 安装完成" && \
    node --version && \
    npm --version

# 安装 PHP 扩展 (仅必需的)
RUN docker-php-ext-install \
    pdo \
    pdo_sqlite \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    sockets \
    zip \
    intl \
    opcache

# 复制自定义 PHP 配置
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# 启用 Apache 模块
RUN a2enmod rewrite headers

# 安装 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 配置 Apache 使用 php 用户运行
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf && \
    sed -i 's/\${APACHE_RUN_USER:=www-data}/\${APACHE_RUN_USER:=php}/g' /etc/apache2/envvars && \
    sed -i 's/\${APACHE_RUN_GROUP:=www-data}/\${APACHE_RUN_GROUP:=php}/g' /etc/apache2/envvars

#创建 php 用户和用户组
RUN echo "📝 创建 php 用户..." && \
    groupadd -r -g 1000 php && \
    useradd -r -u 1000 -g php -s /bin/bash -d /home/php php && \
    mkdir -p /home/php && \
    chown -R php:php /home/php && \
    echo "✅ php 用户创建完成 (UID:1000, 主目录:/home/php)"
# php用户,增加apt 权限
RUN echo "🔧 安装 sudo 并为 php 用户增加 apt 权限..." && \
    apt-get update && \
    apt-get install -y sudo && \
    echo "php ALL=(ALL) NOPASSWD: /usr/bin/apt-get, /usr/bin/apt, /usr/bin/dpkg" >> /etc/sudoers && \
    echo "✅ php 用户 apt 权限配置完成"


# php用户 运行php
# 确保 php 用户有访问必要目录的权限
RUN usermod -a -G www-data php && \
    mkdir -p /var/www/html/storage /var/www/html/storage/logs /var/www/html/storage/framework/cache /var/www/html/storage/framework/sessions /var/www/html/storage/framework/testing /var/www/html/storage/framework/views /var/www/html/bootstrap/cache && \
    chown -R php:php /var/www/html/storage /var/www/html/bootstrap/cache



COPY laravel /var/www/html

# 确保 storage 和 bootstrap/cache 目录权限正确
RUN mkdir -p /var/www/html/storage/logs /var/www/html/storage/framework/cache /var/www/html/storage/framework/sessions /var/www/html/storage/framework/testing /var/www/html/storage/framework/views /var/www/html/bootstrap/cache && \
    chown -R php:php /var/www/html/storage /var/www/html/bootstrap/cache

USER php

# 安装 PHP 依赖
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 复制 supervisor 配置
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# 创建 supervisor 日志目录
USER root
RUN mkdir -p /var/log/supervisor

# 启动 supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
