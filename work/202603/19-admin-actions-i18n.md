# Admin Actions 多语言处理

## 任务
为 `laravel/app/Admin/Actions` 目录下的所有 Action 文件添加多语言支持。

## 修改内容

### 1. 新增辅助函数
在 `app/Helpers/helpers.php` 中添加以下函数：

```php
// Admin Action 翻译
function admin_trans_action(string $key, array $replace = []): string

// 控制器语言包翻译（自动检测控制器名）
function admin_trans(string $key, array $replace = [], ?string $controller = null): string

// 字段翻译
function admin_trans_field(string $field, array $replace = [], ?string $controller = null): string

// 标签翻译
function admin_trans_label(string $label, array $replace = [], ?string $controller = null): string

// 选项翻译
function admin_trans_option(string $value, string $group, array $replace = [], ?string $controller = null): string
```

### 2. 更新语言包

#### admin-actions.php (Action 操作翻译)
更新 `lang/zh_CN/admin-actions.php` 和 `lang/en/admin-actions.php`，按照类名转小写下划线的命名规则添加翻译：

| Action 类 | 翻译键 |
|-----------|--------|
| ViewRequestLog | view_request_log |
| ViewChannelRequestLog | view_channel_request_log |
| ViewResponseLog | view_response_log |
| ViewAffinityHit | view_affinity_hit |
| CopyChannel | copy_channel |
| CopyChannelAffinityRule | copy_channel_affinity_rule |
| RefreshModelCache | refresh_model_cache |
| RefreshSettingCache | refresh_setting_cache |
| ResetApiKey | reset_api_key |

#### admin-api-key.php (ApiKeyController 控制器翻译)
新建 `lang/zh_CN/admin-api-key.php` 和 `lang/en/admin-api-key.php`，结构如下：

```php
return [
    'fields' => [
        // 字段翻译
        'name' => '名称',
        'key' => '密钥',
        // ...
    ],
    'labels' => [
        // 标签翻译
        'title' => 'API密钥管理',
        'no_limit' => '不限制',
        // ...
    ],
    'options' => [
        // 选项翻译
        'status' => [
            'active' => '激活',
            'revoked' => '已撤销',
            'expired' => '已过期',
        ],
    ],
];
```

### 3. 修改的文件
**Actions 目录：**
- [ViewRequestLog.php](../../laravel/app/Admin/Actions/ViewRequestLog.php)
- [ViewChannelRequestLog.php](../../laravel/app/Admin/Actions/ViewChannelRequestLog.php)
- [ViewResponseLog.php](../../laravel/app/Admin/Actions/ViewResponseLog.php)
- [ViewAffinityHit.php](../../laravel/app/Admin/Actions/ViewAffinityHit.php)
- [CopyChannel.php](../../laravel/app/Admin/Actions/CopyChannel.php)
- [CopyChannelAffinityRule.php](../../laravel/app/Admin/Actions/CopyChannelAffinityRule.php)
- [RefreshModelCache.php](../../laravel/app/Admin/Actions/RefreshModelCache.php)
- [RefreshSettingCache.php](../../laravel/app/Admin/Actions/RefreshSettingCache.php)
- [ResetApiKey.php](../../laravel/app/Admin/Actions/ResetApiKey.php)

**Controllers 目录：**
- [ApiKeyController.php](../../laravel/app/Admin/Controllers/ApiKeyController.php)

### 4. 实现方式
- Action: 将 `protected $title` 属性改为 `public function title()` 方法
- 使用 `admin_trans_action()` 函数获取 Action 翻译
- 使用 `admin_trans_field()` 获取字段翻译
- 使用 `admin_trans_label()` 获取标签翻译
- 使用 `admin_trans_option()` 获取选项翻译
- 确认对话框和响应消息也使用多语言

## 参考
- [dcat-admin-i18n 技能文档](../../.claude/skills/dcat-admin-i18n/SKILL.md)