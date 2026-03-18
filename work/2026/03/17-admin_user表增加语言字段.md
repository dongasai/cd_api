# admin_user 表增加语言字段 & 菜单多语言实现

## 完成时间
2026-03-18 00:05

## 需求
1. 在 admin_user 表中增加语言字段，用于控制后台多语言
2. 实现菜单多语言功能
3. 在用户设置页面添加语言选择

## 实现内容

### 一、数据库迁移
- 创建迁移文件：`database/migrations/2026_03_17_234002_add_language_to_admin_users_table.php`
- 添加 `language` 字段（varchar(10)，默认值 'zh_CN'）
- 字段位置：在 `avatar` 字段之后

### 二、自定义 Administrator 模型
- 创建文件：`app/Models/Administrator.php`
- 继承 `Dcat\Admin\Models\Administrator`
- 添加语言相关方法：
  - `getLanguage()`: 获取用户界面语言
  - `setLanguage()`: 设置用户界面语言
  - `getSupportedLanguages()`: 获取支持的语言列表

### 三、配置更新
- 更新 `config/admin.php`：
  - `auth.providers.admin.model` 改为 `App\Models\Administrator::class`
  - `database.users_model` 改为 `App\Models\Administrator::class`
  - `route.middleware` 添加 `\App\Http\Middleware\SetAdminLocale::class`

### 四、菜单多语言实现

#### 方案说明
采用**直接翻译方式**：
1. 数据库中保持中文标题
2. 在英文语言文件中使用中文标题作为键映射到英文翻译
3. Laravel 的翻译系统自动处理：中文环境直接显示，英文环境自动翻译

#### 实现步骤
1. 在 `lang/en/admin.php` 中添加中文标题到英文的映射，例如：
   ```php
   '仪表盘' => 'Dashboard',
   '渠道管理' => 'Channels',
   // ...
   ```

2. 创建语言切换中间件 `app/Http/Middleware/SetAdminLocale.php`
3. 中间件根据用户的 `language` 字段设置应用语言

### 五、用户设置页面

#### 创建自定义 AuthController
- 文件：`app/Admin/Controllers/AuthController.php`
- 继承 `Dcat\Admin\Http\Controllers\AuthController`
- 覆盖 `getSetting()` 和 `putSetting()` 方法
- 在设置表单中添加语言选择字段

#### 表单字段
- 用户名（只读）
- 姓名
- 头像上传
- **语言选择**（新增）
- 修改密码相关字段

#### 路由配置
- 在 `app/Admin/routes.php` 中覆盖默认的设置路由
- 指向自定义 AuthController

## 支持的语言
- `zh_CN` - 简体中文（默认）
- `en` - English

## 使用说明

### 如何切换语言
1. 登录后台管理系统
2. 点击右上角用户名，选择"用户设置"
3. 在"界面语言"下拉框中选择语言
4. 点击"提交"保存
5. 刷新页面，菜单和界面将切换为对应语言

### 菜单多语言工作原理
1. 数据库中菜单标题使用中文（如"仪表盘"）
2. 中文环境：直接显示中文标题
3. 英文环境：通过语言文件自动翻译为英文（"Dashboard"）
4. 用户登录后，中间件根据 `language` 字段设置语言环境

## 技术要点

### 菜单翻译方式选择
最初尝试使用翻译键格式（如 `admin.menu_titles.dashboard`），但发现：
- Dcat Admin 直接从数据库读取标题并显示，不自动翻译翻译键
- 需要额外的处理逻辑

最终采用**直接翻译方式**：
- 数据库保持中文标题
- 语言文件中用中文作为键
- 利用 Laravel 的 `trans()` 函数自动翻译
- 简单、直观、易于维护

### 中间件执行流程
```
用户登录 → SetAdminLocale 中间件
         ↓
    读取用户 language 字段
         ↓
    设置 app()->setLocale($language)
         ↓
    渲染页面时自动翻译文本
```

## 后续工作
- 可以扩展支持更多语言（如繁体中文、日文等）
- 可以添加更多界面文本的多语言支持
- 可以添加自动检测浏览器语言功能

## 文件清单

### 新增文件
- `app/Models/Administrator.php` - 自定义管理员模型
- `app/Http/Middleware/SetAdminLocale.php` - 语言切换中间件
- `app/Admin/Controllers/AuthController.php` - 自定义设置控制器
- `database/migrations/2026_03_17_234002_add_language_to_admin_users_table.php` - 语言字段迁移
- `docs/design/菜单多语言实现方案.md` - 设计文档

### 修改文件
- `config/admin.php` - 更新模型和中间件配置
- `lang/zh_CN/admin.php` - 添加菜单翻译定义
- `lang/en/admin.php` - 添加中文到英文的菜单映射
- `app/Admin/routes.php` - 添加自定义设置路由

## 测试验证
- ✅ 数据库字段添加成功
- ✅ 用户设置页面显示语言选择
- ✅ 保存语言设置成功
- ✅ 中文界面正常显示
- ✅ 英文界面菜单翻译正确