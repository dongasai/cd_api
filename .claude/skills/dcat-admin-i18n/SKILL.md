---
name: dcat-admin-i18n
description: |
  Dcat Admin 后台多语言实施技能。用于创建和管理后台控制器的多语言文件。

  TRIGGER 当用户：
  - 创建新的 Admin 控制器
  - 需要添加多语言翻译
  - 询问语言包命名或结构
  - 需要翻译字段、标签或选项
  - 创建菜单需要翻译

  自动应用项目多语言规则，生成符合规范的语言包文件。
---

# Dcat Admin 后台多语言规则

## 一、语言包命名规则

### 1.1 控制器语言包

```
规则：admin-{控制器名小写中划线}.php
位置：lang/{locale}/admin-{控制器名}.php

示例：
ChannelController → admin-channel.php
CodingAccountController → admin-coding-account.php
UserProfileController → admin-user-profile.php
```

### 1.2 Admin Actions 语言包

```
固定文件名：admin-actions.php
位置：lang/{locale}/admin-actions.php
用途：所有 Action 操作的翻译，包括：
  - RowAction（行操作）
  - BatchAction（批量操作）
  - AbstractTool（工具按钮）
  - FormAction（表单操作）
```

### 1.3 菜单语言包

```
固定文件名：menu.php
位置：lang/{locale}/menu.php
```

## 二、控制器属性声明

控制器必须声明 `$translation` 属性来指定语言包文件名：

```php
class ChannelController extends AdminController
{
    /**
     * 语言包名称
     *
     * @var string
     */
    public $translation = 'admin-channel';

    // ...
}
```

**命名规则**：`admin-{控制器名小写中划线}`
- `ChannelController` → `admin-channel`
- `CodingAccountController` → `admin-coding-account`
- `AuditLogController` → `admin-audit-log`

## 三、语言包内容结构

### 3.1 控制器语言包结构

```php
<?php

return [
    'fields' => [
        // 字段翻译 - 数据库字段名
        '字段名' => '翻译',
    ],
    'labels' => [
        // 标题 - 控制器页面标题（必须）
        'title' => '页面标题',

        // 其他标签翻译 - 帮助文本、按钮等
        '标签键' => '翻译',
    ],
    'options' => [
        // 选项翻译 - 枚举值
        '选项组' => [
            '值' => '翻译',
        ],
    ],
];
```

**重要说明**：
- `labels.title` 是必须的，用于控制器的 `title()` 方法返回页面标题
- 在控制器中使用：`protected function title(): string { return admin_trans_label('title'); }`

### 2.2 Admin Actions 语言包结构

```php
<?php

return [
    // 操作翻译，key 为类名（转小写下划线）
    // RowAction
    'reset_api_key' => '重置 API Key',
    'view_affinity_hit' => '查看亲和命中',
    'copy_channel_affinity_rule' => '复制亲和规则',
    'copy_channel' => '复制渠道',
    'view_response_log' => '查看响应日志',
    'view_request_log' => '查看请求日志',

    // AbstractTool
    'refresh_model_cache' => '刷新模型缓存',
    'refresh_setting_cache' => '刷新缓存',

    // BatchAction
    'batch_delete' => '批量删除',
    'batch_enable' => '批量启用',
];
```

**命名规则**：Action 类名转小写下划线
- `ResetApiKey` → `reset_api_key`
- `RefreshModelCache` → `refresh_model_cache`
- `BatchDelete` → `batch_delete`

### 2.3 菜单语言包结构

```php
<?php

return [
    'titles' => [
        '菜单键名' => '翻译',
    ],
];
```

## 四、翻译函数使用规则

### 4.1 字段翻译（自动匹配）

当控制器声明了 `$translation` 属性后，`column()` 省略第二个参数会自动匹配语言包：

```php
// 控制器中声明
public $translation = 'admin-channel';

// 自动匹配 admin-channel.fields.name
$grid->column('name');  // 推荐，简洁
$grid->column('name', admin_trans_field('name'));  // 冗余，不推荐
```

**原理**：Dcat Admin 会自动查找 `{translation}.fields.{字段名}` 的翻译。

### 4.2 翻译函数对照表

| 场景 | 函数 | 示例 |
|------|------|------|
| 字段翻译 | 自动（省略参数） | `$grid->column('name')` |
| 标签翻译 | `admin_trans_label()` | `admin_trans_label('basic_info')` |
| 选项组数组 | `admin_trans_options()` | `$show->field('status')->using(admin_trans_options('status'))` |
| 单个选项值翻译 | `admin_trans_option()` | `admin_trans_option('active', 'status')` |
| Action 标题 | `admin_trans_action()` | `admin_trans_action('reset_api_key')` |

**重要说明**：
- `->using()` 方法需要传入数组，使用 `admin_trans_options('选项组名')` 获取整个选项组数组
- `admin_trans()` 返回字符串，不能用于 `->using()` 方法

### 3.1 Action 实现示例

**RowAction 示例：**
```php
<?php

namespace App\Admin\Actions;

use Dcat\Admin\Grid\RowAction;

class ResetApiKey extends RowAction
{
    public function title()
    {
        return admin_trans_action('reset_api_key');
    }
}
```

**AbstractTool 示例：**
```php
<?php

namespace App\Admin\Actions;

use Dcat\Admin\Grid\Tools\AbstractTool;

class RefreshModelCache extends AbstractTool
{
    protected $title;

    public function __construct()
    {
        $this->title = admin_trans_action('refresh_model_cache');
    }
}
```

## 四、菜单数据库规则

```
数据库 title 字段：使用英文键名
翻译文件：menu.php 的 titles 数组

示例：
数据库：title = 'data_statistics'
menu.php：'data_statistics' => '数据统计'
```

## 五、工作流程

### 创建新控制器语言包

1. 根据控制器名确定语言包文件名
2. 同时创建中文和英文版本
3. 按结构填充内容

### 添加新字段翻译

1. 在 `fields` 数组添加字段翻译
2. 控制器中使用 `$grid->column('field_name')` 自动获取翻译

### 添加帮助文本

1. 在 `labels` 数组添加 `{字段名}_help` 格式的帮助文本
2. 控制器中使用 `->help(admin_trans_label('field_help'))`

### 添加选项翻译

1. 在 `options` 数组添加选项组
2. 使用 `admin_trans_option()` 或 `admin_trans()` 获取翻译

## 六、常见错误

| 错误 | 正确 |
|------|------|
| `channel.php`（缺少前缀） | `admin-channel.php` |
| 菜单翻译放在 `admin.php` | 菜单翻译放在 `menu.php` |
| `$grid->column('name', '渠道名称')` | `$grid->column('name')` |

## 七、参考模板

详见 `references/language-pack-template.php` 完整模板。