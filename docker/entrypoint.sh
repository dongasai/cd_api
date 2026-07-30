#!/bin/bash
set -e

# 输出版本信息
cd /var/www/html
su php -c "php artisan version"

# 启动 supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
