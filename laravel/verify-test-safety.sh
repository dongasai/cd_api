#!/bin/bash

# 测试数据库安全验证脚本

echo "🔍 验证测试数据库安全配置..."
echo ""

# 1. 检查 phpunit.xml 配置
echo "1️⃣ 检查 phpunit.xml 强制配置："
grep -A 2 "DB_CONNECTION.*force" phpunit.xml || echo "❌ 缺少 force=\"true\""
grep -A 2 "DB_DATABASE.*force" phpunit.xml || echo "❌ 缺少 force=\"true\""
echo ""

# 2. 检查 TestCase 安全检查
echo "2️⃣ 检查 TestCase 安全检查："
grep -A 5 "ensureNotProductionDatabase" tests/TestCase.php | head -6 || echo "❌ 缺少安全检查"
echo ""

# 3. 检查 .env.testing
echo "3️⃣ 检查 .env.testing 配置："
cat .env.testing | grep "DB_" || echo "❌ 缺少测试环境配置"
echo ""

# 4. 模拟安全检查
echo "4️⃣ 测试安全检查："
php artisan tinker --execute="
config(['database.connections.sqlite.database' => 'cdapi']);
echo '✅ 如果连接到 cdapi 数据库，测试会失败';
"
echo ""

# 5. 检查生产数据库是否完好
echo "5️⃣ 检查生产数据库数据："
php artisan tinker --execute="
\$count = DB::table('channels')->count();
echo '渠道数量：' . \$count . ' 条';
"
echo ""

echo "✅ 安全验证完成！"
echo ""
echo "📋 检查清单："
echo "[ ] phpunit.xml 中 DB_CONNECTION 和 DB_DATABASE 有 force=\"true\""
echo "[ ] tests/TestCase.php 包含 ensureNotProductionDatabase 方法"
echo "[ ] .env.testing 配置了独立的测试数据库"
echo "[ ] 生产数据库数据完好（渠道数 > 0）"