---
name: dev-filament
description: 进行 Filament Admin v4.x 的后台开发，专注于基于 Laravel 12 + Filament 的模块化管理系统开发
---

# Filament Admin Development

## 🚀 快速开始

此技能专用于基于 Laravel 12 + Filament Admin v4.2.0 的后台管理系统开发。项目采用模块化架构，集成 nwidart/laravel-modules 模块系统，专注于构建高效、可维护的管理后台。

### 📋 开发检查清单
在开始开发前，请确保以下环境已就绪：
- [ ] PHP 8.3+ 已安装
- [ ] Laravel 12 项目已创建
- [ ] Filament v4.2.0 已安装
- [ ] nwidart/laravel-modules 已配置
- [ ] 数据库连接已建立
- [ ] 模块系统已启用

### 技术栈
- **PHP**: 8.3
- **Laravel**: 12.x
- **Filament**: v4.2.0 (最新版本)
- **模块系统**: nwidart/laravel-modules
- **数据库**: MySQL 8.0

### 项目状态
- ✅ Filament 已安装并可用
- ✅ 核心包完整：forms, notifications, support, tables, actions, infolists, schemas, widgets
- ✅ 配置文件已发布（`config/filament.php` 可用）
- ✅ 视图文件已发布（支持自定义主题和视图）
- ✅ 开发环境完全就绪

## 核心概念



### 2. Filament 核心组件

#### Resources (资源类)
- **用途**: 自动生成 CRUD 界面
- **核心方法**: `form()`, `table()`, `pages()`
- **详细文档**: [Resources 完整指南](.claude/skills/filament/03-resources/01-overview.md)
- **功能模块**:
  - [记录列表](.claude/skills/filament/03-resources/02-listing-records.md)
  - [创建记录](.claude/skills/filament/03-resources/03-creating-records.md)
  - [编辑记录](.claude/skills/filament/03-resources/04-editing-records.md)
  - [查看记录](.claude/skills/filament/03-resources/05-viewing-records.md)
  - [删除记录](.claude/skills/filament/03-resources/06-deleting-records.md)
  - [管理关联关系](.claude/skills/filament/03-resources/07-managing-relationships.md)
  - [嵌套资源](.claude/skills/filament/03-resources/08-nesting.md)
  - [单数资源](.claude/skills/filament/03-resources/09-singular.md)
  - [全局搜索](.claude/skills/filament/03-resources/10-global-search.md)
  - [小组件集成](.claude/skills/filament/03-resources/11-widgets.md)
  - [自定义页面](.claude/skills/filament/03-resources/12-custom-pages.md)

- **示例**:
```php
class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required(),
            Select::make('role_id')
                ->relationship('role', 'name')
                ->searchable(),
        ]);
    }
}
```

#### Relation Managers (关系管理器)
- **用途**: 管理模型关联关系
- **详细文档**: [管理关联关系](.claude/skills/filament/03-resources/07-managing-relationships.md)
- **示例**: 用户角色关系管理

#### Pages (页面)
- **用途**: 自定义页面，如仪表盘、设置页等
- **详细文档**: [自定义页面](.claude/skills/filament/03-resources/12-custom-pages.md) | [导航页面](.claude/skills/filament/06-navigation/02-custom-pages.md)
- **类型**: Settings Pages, Custom Pages

#### Widgets (小组件)
- **用途**: 显示统计信息、图表等
- **详细文档**: [小组件集成](.claude/skills/filament/03-resources/11-widgets.md) | [小组件组件](.claude/skills/filament/12-components/02-widget.md)
- **类型**: Chart Widgets, Stats Widgets

#### Clusters (集群)
- **用途**: 将相关资源分组显示
- **详细文档**: [集群导航](.claude/skills/filament/06-navigation/04-clusters.md)
- **示例**: 用户管理集群包含用户、角色、权限资源

### 3. 表单和表格组件

#### 表单组件
- **详细文档**: [表单组件完整指南](.claude/skills/filament/12-components/02-form.md)
- **基础组件**:
  - **TextInput**: [文本输入](.claude/skills/filament/12-components/03-input.md)
  - **Textarea**: 多行文本
  - **Select**: [下拉选择](.claude/skills/filament/12-components/03-select.md)
  - **Checkbox**: [复选框](.claude/skills/filament/12-components/03-checkbox.md)
  - **Radio**: 单选按钮
  - **DatePicker**: 日期选择器
  - **FileUpload**: 文件上传

#### 表格组件
- **详细文档**: [表格组件完整指南](.claude/skills/filament/12-components/02-table.md)
- **基础组件**:
  - **TextColumn**: 文本列
  - **IconColumn**: 图标列
  - **ImageColumn**: 图片列
  - **ToggleColumn**: 开关列
  - **SelectColumn**: 选择列

### 4. Actions 和 Filters

#### Actions (操作)
- **详细文档**: [操作组件完整指南](.claude/skills/filament/12-components/02-action.md) | [测试操作](.claude/skills/filament/10-testing/05-testing-actions.md)
- **基础操作**:
  - **CreateAction**: 创建操作
  - **EditAction**: 编辑操作
  - **DeleteAction**: 删除操作
  - **CustomAction**: 自定义操作

#### Filters (过滤器)
- **详细文档**: [表格过滤器](.claude/skills/filament/10-testing/03-testing-tables.md)
- **基础过滤器**:
  - **SelectFilter**: 选择过滤器
  - **DateFilter**: 日期过滤器
  - **TernaryFilter**: 三态过滤器
  - **CustomFilter**: 自定义过滤器

### 5. 💡 开发最佳实践

#### 🏗️ 模块化开发原则
1. **单一职责**: 每个模块专注特定业务领域
2. **松耦合**: 模块间通过接口和事件通信
3. **高内聚**: 相关功能集中在同一模块内
4. **可复用**: 通用组件可跨模块使用

#### 🔐 权限控制最佳实践
```php
// Resource 级别权限控制
public static function canViewAny(): bool
{
    return auth()->user()->can('view-users');
}

// 表单字段级别权限控制
TextInput::make('email')
    ->visible(fn () => auth()->user()->can('view-email'))
    ->disabled(fn () => !auth()->user()->can('edit-email'));

// 操作级别权限控制
use Filament\Actions\EditAction;

EditAction::make()
    ->visible(fn ($record) => auth()->user()->can('edit', $record));
```

#### ✅ 数据验证最佳实践
```php
use Closure;

// 使用 Form Request 进行验证
public static function form(Form $form): Form
{
    return $form->schema([
        TextInput::make('name')
            ->required()
            ->rules(['string', 'max:255'])
            ->validationMessages([
                'required' => '姓名不能为空',
                'max' => '姓名不能超过255个字符',
            ]),

        TextInput::make('email')
            ->required()
            ->email()
            ->unique(ignoreRecord: true)
            ->validationAttribute('邮箱地址'),
    ]);
}

// 自定义验证规则
protected function getFormSchema(): array
{
    return [
        TextInput::make('password')
            ->password()
            ->required()
            ->rules([
                'min:8',
                'confirmed',
                function (string $attribute, string $value, Closure $fail) {
                    if (!preg_match('/[A-Z]/', $value)) {
                        $fail('密码必须包含至少一个大写字母');
                    }
                },
            ]),
    ];
}
```

#### ⚡ 性能优化技巧
```php
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;

// 表格性能优化
public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('name')
                ->searchable() // 启用搜索
                ->sortable(),   // 启用排序

            TextColumn::make('created_at')
                ->dateTime('Y-m-d H:i')
                ->sortable(),
        ])
        ->defaultSort('created_at', 'desc')
        ->poll('60s') // 自动刷新
        ->actions([
            EditAction::make(),
            DeleteAction::make(),
        ])
        ->bulkActions([
            DeleteBulkAction::make(),
        ]);
}

// 延迟加载大数据集
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->with(['roles', 'permissions']) // 预加载关联关系
        ->select(['id', 'name', 'email', 'created_at']); // 选择需要的字段
}

// 缓存静态数据
Select::make('country_id')
    ->options(cache()->remember('countries', 3600, function () {
        return Country::pluck('name', 'id')->toArray();
    }))
    ->searchable();
```

#### 🎨 用户体验增强
```php
// 自定义表格样式
TextColumn::make('status')
    ->badge()
    ->color(fn (string $state): string => match ($state) {
        'active' => 'success',
        'inactive' => 'danger',
        default => 'gray',
    })
    ->formatStateUsing(fn (string $state): string => __($state)),

// 添加图标和提示
TextInput::make('email')
    ->prefixIcon('heroicon-o-envelope')
    ->hint('请输入有效的邮箱地址')
    ->hintColor('danger'),

// 响应式表单布局
Section::make('用户信息')
    ->description('填写基本用户信息')
    ->schema([
        Grid::make(2)
            ->schema([
                TextInput::make('first_name'),
                TextInput::make('last_name'),
            ]),
        TextInput::make('email')
            ->columnSpanFull(),
    ]),
```

## 📚 快速参考

### ⚡ 常用代码片段

#### 创建完整的 Resource
```php
<?php

namespace Modules\UserManagement\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Modules\UserManagement\Models\User;
use BackedEnum;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static string|UnitEnum|null $navigationGroup = '用户管理';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('基本信息')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('email')
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true),
                        ]),
                    Forms\Components\Select::make('role_id')
                        ->relationship('role', 'name')
                        ->searchable()
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->relationship('role', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
```

#### 创建自定义 Widget
```php
<?php

namespace Modules\UserManagement\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\UserManagement\Models\User;

class UserStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('总用户数', User::count())
                ->description('相比上月增长 5%')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('活跃用户', User::where('last_login_at', '>=', now()->subDays(7))->count())
                ->description('最近7天活跃')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),

            Stat::make('新注册', User::where('created_at', '>=', now()->subDays(30))->count())
                ->description('本月新注册')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('warning'),
        ];
    }
}
```

### 🛠️ 开发指南

### 1. 创建新模块

- **详细文档**: [安装指南](.claude/skills/filament/01-introduction/02-installation.md) | [入门指南](.claude/skills/filament/02-getting-started.md)

```bash
# 创建基础模块
php artisan module:make ModuleName

# 创建带 Filament 支持的模块
php artisan module:make ModuleName --fillable=Resources,Pages,Widgets
```

### 2. 创建 Resource

- **详细文档**: [Resources 概览](.claude/skills/filament/03-resources/01-overview.md) | [代码质量提示](.claude/skills/filament/03-resources/13-code-quality-tips.md)

```bash
# 在模块中创建 Resource
php artisan filament:make-resource UserResource --module=ModuleName

# 创建带 Relation Manager 的 Resource
php artisan filament:make-resource UserResource --generate --module=ModuleName
```

### 3. 创建页面

- **详细文档**: [自定义页面](.claude/skills/filament/03-resources/12-custom-pages.md) | [导航页面](.claude/skills/filament/06-navigation/02-custom-pages.md)

```bash
# 创建自定义页面
php artisan filament:make-page Settings --module=ModuleName

# 创建仪表盘页面
php artisan filament:make-page Dashboard --type=dashboard --module=ModuleName
```

### 4. 创建组件

- **详细文档**: [小组件](.claude/skills/filament/12-components/02-widget.md) | [组件概览](.claude/skills/filament/12-components/01-overview.md)

```bash
# 创建小组件
php artisan filament:make-widget StatsOverview --module=ModuleName

# 创建关系管理器
php artisan filament:make-relation-manager RoleRelationManager --module=ModuleName
```



## 常用命令

### Filament 命令
- **详细文档**: [安装指南](.claude/skills/filament/01-introduction/02-installation.md) | [入门指南](.claude/skills/filament/02-getting-started.md)

```bash
# 查看Filament信息
php artisan filament:about


# 清理缓存
php artisan filament:cache-components
php artisan filament:optimize-clear

# 检查翻译
php artisan filament:check-translations
```

### 开发命令
- **详细文档**: [优化本地开发](.claude/skills/filament/01-introduction/03-optimizing-local-development.md)

```bash
# 发布配置文件
php artisan vendor:publish --tag=filament-config

# 发布视图文件
php artisan vendor:publish --tag=filament-views

# 发布语言文件
php artisan vendor:publish --tag=filament-translations

# 清理缓存
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 格式化代码
vendor/bin/pint --dirty
```

## 🔧 故障排除和常见问题

### ❓ 常见问题解答

#### Q: 如何创建自定义页面？
```bash
php artisan filament:make-page CustomPage --module=ModuleName
```

#### Q: 如何在表格中添加自定义操作？
```php
Tables\Actions\Action::make('approve')
    ->label('批准')
    ->icon('heroicon-o-check')
    ->color('success')
    ->action(function (Model $record) {
        $record->update(['status' => 'approved']);
    }),
```

#### Q: 如何实现多语言支持？
```php
// 在 Resource 中
use Illuminate\Support\Facades\Lang;

protected static string|\BackedEnum|null $navigationLabel = 'Users';

// 在表单中
TextInput::make('name')
    ->label(__('fields.name'))
    ->placeholder(__('placeholders.name')),

// 发布语言文件
php artisan vendor:publish --tag=filament-translations
```

#### Q: 如何自定义主题颜色？
```php
use Filament\Panel;
use Filament\Support\Colors\Color;

// 在 app/Providers/Filament/AdminPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->colors([
            'primary' => Color::Amber,
            'secondary' => Color::Gray,
            'success' => Color::Green,
            'danger' => Color::Red,
        ]);
}
```

#### Q: 如何处理大数据集的性能问题？
```php
use Illuminate\Database\Eloquent\Builder;

// 使用分页和延迟加载
public static function table(Table $table): Table
{
    return $table
        ->defaultPaginationPageOption(25)
        ->extremePaginationLinks()
        ->poll('60s');
}

// 优化查询
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->select(['id', 'name', 'email'])
        ->withCount(['posts', 'comments']);
}
```

### 🐛 调试和故障排除

#### 资源不显示
- 检查 `static::$model` 是否正确
- 确认模块已启用
- 验证路由是否正确注册

#### 表单提交失败
- 检查验证规则
- 确认模型字段可填充
- 查看浏览器控制台错误

#### 权限问题
- 确认用户有对应权限
- 检查 Gate/Policy 定义
- 验证中间件配置

### 2. 调试技巧

#### 启用调试模式
```php
// 在 config/app.php 中
'debug' => env('APP_DEBUG', false),

// 查看详细错误信息
php artisan tinker
```

#### 查询调试
```php
// 在模型中启用查询日志
DB::enableQueryLog();
// 执行查询
dd(DB::getQueryLog());
```

#### 组件调试
```php
// 在 Resource 中添加调试信息
public static function table(Table $table): Table
{
    return $table->columns([
        TextColumn::make('name')
            ->formatStateUsing(fn ($state) => {
                dump($state); // 调试输出
                return $state;
            }),
    ]);
}
```


---

## 高级主题

### 用户管理与认证
- **详细文档**: [用户管理概览](.claude/skills/filament/07-users/01-overview.md)
- **功能特性**:
  - [多因子认证](.claude/skills/filament/07-users/02-multi-factor-authentication.md)
  - [多租户支持](.claude/skills/filament/07-users/03-tenancy.md)

### 导航系统
- **详细文档**: [导航概览](.claude/skills/filament/06-navigation/01-overview.md)
- **功能特性**:
  - [自定义页面导航](.claude/skills/filament/06-navigation/02-custom-pages.md)
  - [用户菜单](.claude/skills/filament/06-navigation/03-user-menu.md)
  - [集群导航](.claude/skills/filament/06-navigation/04-clusters.md)

### 样式定制
- **详细文档**: [样式概览](.claude/skills/filament/08-styling/01-overview.md)
- **自定义功能**:
  - [CSS 钩子](.claude/skills/filament/08-styling/02-css-hooks.md)
  - [颜色系统](.claude/skills/filament/08-styling/03-colors.md)
  - [图标系统](.claude/skills/filament/08-styling/04-icons.md)

### 高级功能
- **详细文档**: [高级功能概览](.claude/skills/filament/09-advanced/01-overview.md)
- **高级特性**:
  - [渲染钩子](.claude/skills/filament/09-advanced/01-render-hooks.md)
  - [资源管理](.claude/skills/filament/09-advanced/02-assets.md)
  - [枚举支持](.claude/skills/filament/09-advanced/03-enums.md)
  - [文件生成](.claude/skills/filament/09-advanced/04-file-generation.md)

### 测试
- **详细文档**: [测试概览](.claude/skills/filament/10-testing/01-overview.md)
- **测试类型**:
  - [测试 Resources](.claude/skills/filament/10-testing/02-testing-resources.md)
  - [测试表格](.claude/skills/filament/10-testing/03-testing-tables.md)
  - [测试表单](.claude/skills/filament/10-testing/04-testing-schemas.md)
  - [测试操作](.claude/skills/filament/10-testing/05-testing-actions.md)
  - [测试通知](.claude/skills/filament/10-testing/06-testing-notifications.md)

### 插件开发
- **详细文档**: [插件入门](.claude/skills/filament/11-plugins/01-getting-started.md)
- **插件类型**:
  - [面板插件](.claude/skills/filament/11-plugins/02-panel-plugins.md)
  - [构建面板插件](.claude/skills/filament/11-plugins/03-building-a-panel-plugin.md)
  - [构建独立插件](.claude/skills/filament/11-plugins/04-building-a-standalone-plugin.md)

### 组件库
- **详细文档**: [组件概览](.claude/skills/filament/12-components/01-overview.md)
- **可用组件**:
  - [Avatar](.claude/skills/filament/12-components/03-avatar.md)
  - [Badge](.claude/skills/filament/12-components/03-badge.md)
  - [Button](.claude/skills/filament/12-components/03-button.md)
  - [Modal](.claude/skills/filament/12-components/03-modal.md)
  - [Section](.claude/skills/filament/12-components/03-section.md)
  - [Tabs](.claude/skills/filament/12-components/03-tabs.md)
  - 及更多...

## 学习路径

### 初学者路径
1. **入门**: [入门指南](.claude/skills/filament/02-getting-started.md) → [安装指南](.claude/skills/filament/01-introduction/02-installation.md)
2. **基础**: [Resources 概览](.claude/skills/filament/03-resources/01-overview.md) → [组件概览](.claude/skills/filament/12-components/01-overview.md)
3. **实践**: 创建第一个 Resource 和 Page

### 进阶路径
1. **深入**: [高级功能](.claude/skills/filament/09-advanced/01-overview.md) → [样式定制](.claude/skills/filament/08-styling/01-overview.md)
2. **扩展**: [插件开发](.claude/skills/filament/11-plugins/01-getting-started.md) → [用户管理](.claude/skills/filament/07-users/01-overview.md)
3. **优化**: [性能优化](.claude/skills/filament/01-introduction/03-optimizing-local-development.md) → [测试](.claude/skills/filament/10-testing/01-overview.md)

### 专业路径
1. **架构**: [多租户](.claude/skills/filament/07-users/03-tenancy.md) → [导航系统](.claude/skills/filament/06-navigation/01-overview.md)
2. **部署**: [部署指南](.claude/skills/filament/13-deployment.md) → [升级指南](.claude/skills/filament/14-upgrade-guide.md)

---

## 🎯 总结与建议

Filament v4.2.0 为 Laravel 12 提供了强大的后台管理系统开发能力。通过模块化架构，我们可以构建可维护、可扩展的管理系统。

### ⭐ 核心优势
- **快速开发**: 自动生成 CRUD 界面，减少重复代码
- **模块化架构**: 基于 nwidart/laravel-modules 的清晰分层
- **丰富的组件**: 表单、表格、图表等开箱即用
- **权限控制**: 细粒度的权限管理系统
- **响应式设计**: 完美适配桌面和移动设备

### 🚀 开发建议
1. **从简单开始**: 先实现基础功能，再逐步完善
2. **遵循约定**: 充分利用 Filament 和 Laravel 的约定
3. **模块化思维**: 将功能拆分到独立的模块中
4. **性能优先**: 关注查询性能和用户体验
5. **充分测试**: 为关键功能编写测试用例

### 📖 学习路径
- **初学者**: 从 Resources 和 Pages 开始，理解基础概念
- **进阶开发者**: 学习自定义组件、权限控制和性能优化
- **专家级**: 掌握插件开发、多租户和高级功能

### 🛠️ 常用命令速查
```bash
# 创建模块


# 创建 Resource
php artisan filament:make-resource ResourceName --module=ModuleName

# 创建 Widget
php artisan filament:make-widget WidgetName --module=ModuleName

# 创建自定义页面
php artisan filament:make-page PageName --module=ModuleName

# 清理缓存
php artisan optimize:clear

# （可选）重新发布配置文件
php artisan vendor:publish --tag=filament-config --force
```

### 🔗 重要链接
- **Filament 官方文档**: https://filamentphp.com/docs
- **Laravel 模块**: https://nwidart.com/laravel-modules
- **Heroicons**: https://heroicons.com/
- **Tailwind CSS**: https://tailwindcss.com/

记住：始终保持代码简洁、遵循 Laravel 约定、充分利用 Filament 的强大功能！

**文档导航**: 使用上述链接深入探索各个主题，按照学习路径逐步掌握 Filament 开发技能。

