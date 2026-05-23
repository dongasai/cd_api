# 修复 API Endpoint 核心错误

**日期**: 2026-05-12 21:40
**类型**: Bug 修复
**严重级别**: 高危（导致所有 models endpoint 返回空数据）

## 问题1: RouterServiceProvider 参数错误

**文件**: [laravel/app/Providers/RouterServiceProvider.php](laravel/app/Providers/RouterServiceProvider.php)

**错误**: ProxyServer 构造函数参数数量和类型不匹配

**修复**: 添加缺失的 `ChannelErrorHandlingService` 参数，修正参数顺序

详见：[work/2026-05/12-2130-router-service-provider-fix.md](work/2026-05/12-2130-router-service-provider-fix.md)

## 问题2: ApiKey 查询条件错误

**文件**: [laravel/app/Models/ApiKey.php:154](laravel/app/Models/ApiKey.php#L154)

**错误代码**:
```php
$query = Channel::where('status', 'active');  // ❌ 错误！
```

**问题分析**:
- `channels.status` 字段类型：`tinyint unsigned` (0=禁用, 1=启用)
- 使用了枚举 `ChannelStatus` (ACTIVE=1, DISABLED=0)
- 查询条件 `'active'` 是字符串，与字段类型不匹配
- 导致查询不到任何渠道，models endpoint 返回空数组

**修复代码**:
```php
use App\Enums\ChannelStatus;

$query = Channel::where('status', ChannelStatus::ACTIVE);  // ✓ 正确
```

## 影响范围

这两个错误会导致：
- **所有 API 请求崩溃**（RouterServiceProvider 错误）
- **models endpoint 返回空数据**（ApiKey 查询错误）

受影响的 endpoints：
- `/api/openai/v1/models`
- `/api/anthropic/models`
- `/api/anthropic/v1/models`
- 所有使用 ProxyServer 的 endpoints

## 数据库状态

| 表名 | 状态 | 说明 |
|-----|------|------|
| channels | 1 个渠道 (status=1 启用) | 渠道已正确启用 |
| channel_models | 2 个启用模型 | qwen3.6-plus 和 qwen-plus |
| model_lists | 11 个全局模型 | 全局模型数据正常 |

## 测试验证

### 修复前
```bash
curl "http://localhost/api/anthropic/v1/models" -H "x-api-key: ..."
```

**响应**:
```json
{"data": [], "has_more": false}  // ❌ 空数组
```

### 修复后
```bash
curl "http://localhost/api/anthropic/v1/models" -H "x-api-key: ..."
```

**响应**:
```json
{
    "data": [
        {
            "id": "qwen3.6-plus",
            "type": "model",
            "display_name": "qwen3.6-plus",
            "created_at": "2026-05-12T21:01:40+08:00"
        }
    ],
    "has_more": false,
    "first_id": "qwen3.6-plus",
    "last_id": "qwen3.6-plus"
}  // ✓ 返回正确数据
```

## 根因分析

1. **类型系统错误**: 字段使用整数枚举，但查询使用字符串
2. **缺少类型导入**: ApiKey 模型未导入 ChannelStatus 枚举
3. **参数传递错误**: RouterServiceProvider 传递了错误类型和数量的参数

## 相关文件

- [laravel/app/Providers/RouterServiceProvider.php](laravel/app/Providers/RouterServiceProvider.php) - 服务提供者
- [laravel/app/Models/ApiKey.php](laravel/app/Models/ApiKey.php) - API Key 模型
- [laravel/app/Enums/ChannelStatus.php](laravel/app/Enums/ChannelStatus.php) - 渠道状态枚举
- [laravel/app/Services/Router/ProxyServer.php](laravel/app/Services/Router/ProxyServer.php) - 代理服务器

## 总结

✅ **RouterServiceProvider 参数错误已修复**
✅ **ApiKey 查询条件错误已修复**
✅ **models endpoint 正常返回数据**
✅ **代码格式化完成**

⚠️ **重要提醒**: 不对前端进行兼容，API 路径保持 `/api` 前缀