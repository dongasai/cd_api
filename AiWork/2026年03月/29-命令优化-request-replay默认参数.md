# 命令优化：request:replay 默认参数为审计日志ID

## 时间
2026-03-29 04:51

## 背景
原命令 `php artisan cdapi:request:replay` 需要通过选项参数指定ID类型：
- `--request-id=1234`
- `--audit-id=4224`
- `--latest`

使用不够便捷，用户希望能直接传入ID作为参数。

## 优化内容

### 1. 添加位置参数
- 新增 `{id?}` 位置参数，默认作为审计日志ID
- 新增 `--type` 选项（默认值 `audit`），可指定ID类型为 `audit` 或 `request`

### 2. 保持向后兼容
- 保留原有的 `--request-id` 和 `--audit-id` 选项
- 原有的使用方式仍然有效

### 3. 改进提示信息
- 不带参数时显示友好的使用示例
- 错误提示更加清晰

## 使用示例

```bash
# 快速重放审计日志（推荐用法）
php artisan cdapi:request:replay 4224

# 指定为请求ID
php artisan cdapi:request:replay 123 --type=request

# 原有用法仍然有效
php artisan cdapi:request:replay --audit-id=4224
php artisan cdapi:request:replay --request-id=1234
php artisan cdapi:request:replay --latest

# 使用dry-run查看请求信息
php artisan cdapi:request:replay 4224 --dry-run
```

## 测试结果

所有场景测试通过：
- ✓ 不带参数显示友好提示
- ✓ 直接传入审计ID正常工作
- ✓ 使用 `--type=request` 指定请求ID正常工作
- ✓ `--latest` 参数正常工作
- ✓ 无效的 `--type` 参数正确报错

## 影响范围

修改文件：
- [laravel/app/Console/Commands/ReplayRequest.php](laravel/app/Console/Commands/ReplayRequest.php)

影响命令：
- `cdapi:request:replay`

向后兼容：完全兼容，原有用法不受影响