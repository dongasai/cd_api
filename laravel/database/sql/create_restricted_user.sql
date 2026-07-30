-- ==================================================
-- 创建受限数据库用户 - 彻底防止数据丢失
-- ==================================================

-- 1. 创建应用用户（受限权限）
CREATE USER IF NOT EXISTS 'cdapi_app'@'%' IDENTIFIED BY 'CdapiApp2026!@#';

-- 2. 只授予 DML 权限（无法执行 TRUNCATE/DROP/ALTER）
GRANT SELECT, INSERT, UPDATE, DELETE ON cdapi.* TO 'cdapi_app'@'%';

-- 3. 创建管理用户（完整权限，仅用于迁移）
CREATE USER IF NOT EXISTS 'cdapi_admin'@'%' IDENTIFIED BY 'CdapiAdmin2026!@#';

-- 4. 授予完整权限（包括 DDL）
GRANT ALL PRIVILEGES ON cdapi.* TO 'cdapi_admin'@'%';

-- 5. 刷新权限
FLUSH PRIVILEGES;

-- 6. 显示权限验证
SHOW GRANTS FOR 'cdapi_app'@'%';
SHOW GRANTS FOR 'cdapi_admin'@'%';

-- ==================================================
-- 执行后请立即更新 .env 文件：
-- DB_USERNAME=cdapi_app
-- DB_PASSWORD=CdapiApp2026!@#
-- ==================================================

-- 验证权限限制（应该失败）
-- 使用 cdapi_app 登录后执行：
-- TRUNCATE TABLE channels;  ← 应该报错：Access denied
-- DROP TABLE channels;      ← 应该报错：Access denied