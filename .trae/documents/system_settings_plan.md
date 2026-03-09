# 系统配置表规划方案

## 概述

创建一个灵活的系统配置表，用于存储全局系统配置项，如系统名称、功能开关、限额配置等。

## 数据库设计

### 表名: `system_settings`

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | bigint unsigned | 主键 |
| `group` | varchar(50) | 配置分组 (如: system, quota, security) |
| `key` | varchar(100) | 配置键 (组内唯一) |
| `value` | text | 配置值 |
| `type` | enum | 值类型: string, integer, float, boolean, json, array |
| `label` | varchar(100) | 显示标签 |
| `description` | text | 配置说明 |
| `is_public` | boolean | 是否公开给前端 |
| `sort_order` | int unsigned | 排序 |
| `created_at` | timestamp | 创建时间 |
| `updated_at` | timestamp | 更新时间 |

### 唯一索引
- `group` + `key` 组合唯一

## 预置配置项

### 系统设置 (system)
| key | label | type | default |
|-----|-------|------|---------|
| site_name | 系统名称 | string | CdApi |
| site_description | 系统描述 | string | AI大模型API代理工具 |
| default_model | 默认模型 | string | gpt-4 |
| request_timeout | 请求超时(秒) | integer | 60 |
| max_retries | 最大重试次数 | integer | 3 |

### 配额设置 (quota)
| key | label | type | default |
|-----|-------|------|---------|
| default_rate_limit | 默认速率限制 | json | {"rpm": 60, "tpm": 100000} |
| quota_warning_threshold | 配额警告阈值 | float | 0.8 |
| quota_critical_threshold | 配额临界阈值 | float | 0.95 |

### 安全设置 (security)
| key | label | type | default |
|-----|-------|------|---------|
| api_key_prefix | API Key前缀 | string | sk- |
| key_length | Key长度 | integer | 48 |
| enable_audit_log | 启用审计日志 | boolean | true |
| sensitive_fields | 敏感字段 | array | ["api_key", "password", "token"] |

### 功能开关 (features)
| key | label | type | default |
|-----|-------|------|---------|
| enable_streaming | 启用流式响应 | boolean | true |
| enable_cache | 启用响应缓存 | boolean | true |
| enable_model_mapping | 启用模型映射 | boolean | true |
| enable_fallback | 启用渠道降级 | boolean | true |

## 实现步骤

### 1. 创建数据库迁移
- 创建 `system_settings` 表

### 2. 创建模型
- `SystemSetting` 模型
- 包含类型转换、访问器、辅助方法

### 3. 创建工厂和填充
- `SystemSettingFactory`
- `SystemSettingSeeder` (预置默认配置)

### 4. 创建 Filament 资源
- `SystemSettingResource`
- 按分组展示配置项
- 根据类型动态渲染表单组件

### 5. 创建配置服务
- `SettingService` 服务类
- 提供便捷的配置读取方法
- 支持缓存

### 6. 创建辅助函数/门面
- `setting('key')` 辅助函数
- 或 `Setting::get('key')` 门面

## 文件清单

```
laravel/
├── app/
│   ├── Models/
│   │   └── SystemSetting.php
│   ├── Services/
│   │   └── SettingService.php
│   ├── Filament/
│   │   └── Resources/
│   │       └── SystemSettings/
│   │           ├── SystemSettingResource.php
│   │           ├── Schemas/
│   │           │   └── SystemSettingForm.php
│   │           ├── Tables/
│   │           │   └── SystemSettingsTable.php
│   │           └── Pages/
│   │               ├── ListSystemSettings.php
│   │               └── EditSystemSetting.php
│   └── Helpers/
│       └── helpers.php (添加 setting() 函数)
├── database/
│   ├── migrations/
│   │   └── xxxx_create_system_settings_table.php
│   ├── factories/
│   │   └── SystemSettingFactory.php
│   └── seeders/
│       └── SystemSettingSeeder.php
└── tests/
    └── Feature/
        └── SystemSettingTest.php
```

## 使用示例

```php
// 获取配置
$siteName = setting('system.site_name');
$timeout = setting('system.request_timeout', 60);

// 通过服务
$value = app(SettingService::class)->get('quota.default_rate_limit');

// 设置配置
app(SettingService::class)->set('system.site_name', 'New Name');
```

## 注意事项

1. 配置值存储为文本，读取时根据 `type` 字段自动转换类型
2. 敏感配置不应标记为 `is_public`
3. 配置变更后自动清除缓存
4. Filament 表单根据类型动态显示不同输入组件
