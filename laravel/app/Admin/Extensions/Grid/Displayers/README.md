# CopyableValue 扩展使用说明

## 功能说明

`copyableValue` 是一个 Dcat Admin Grid 列扩展，允许你**显示一个值，但复制另一个值**。

## 安装

该扩展已在 `app/Admin/bootstrap.php` 中自动注册，无需额外配置。

## 使用方法

### 1. 基本用法 - 复制当前列的原始值

当显示值经过处理后，复制原始数据库值：

```php
// 显示：部分密钥，复制：完整密钥
$grid->column('key', '密钥')
    ->display(function ($value) {
        return substr($value, 0, 10) . '...';  // 显示：sk-GVvku4fq...
    })
    ->copyableValue();  // 复制完整密钥
```

### 2. 复制其他字段值

显示一个字段，复制另一个字段的值：

```php
// 显示：名称，复制：ID
$grid->column('name', '名称')->copyableValue('id');

// 显示：名称，复制：密钥
$grid->column('name', '名称')->copyableValue('key');
```

### 3. 使用闭包动态生成要复制的值

根据当前行数据动态生成要复制的内容：

```php
$grid->column('name', '名称')->copyableValue(function () {
    // $this 指向当前行的模型实例
    return "ID: {$this->id}, Key: {$this->key}";
});

// 或者组合多个字段
$grid->column('user_info', '用户信息')
    ->display('查看详情')
    ->copyableValue(function () {
        return "{$this->name} ({$this->email})";
    });
```

## 实际应用示例

### 示例 1：API 密钥管理

```php
protected function grid()
{
    return Grid::make(ApiKey::query(), function (Grid $grid) {
        // 显示名称，点击复制图标可复制完整的密钥
        $grid->column('name', '名称')->copyableValue('key');

        // 显示部分密钥，复制完整密钥
        $grid->column('key', '密钥')
            ->display(function ($value) {
                return substr($value, 0, 15) . '...';
            })
            ->copyableValue();

        // 显示ID，复制带前缀的完整ID
        $grid->column('id', 'ID')
            ->copyableValue(function () {
                return "API-KEY-{$this->id}";
            });
    });
}
```

### 示例 2：用户管理

```php
// 显示用户名，复制邮箱
$grid->column('username', '用户名')->copyableValue('email');

// 显示角色，复制角色ID
$grid->column('role_name', '角色')->copyableValue('role_id');
```

### 示例 3：订单管理

```php
// 显示订单号，复制完整的订单链接
$grid->column('order_no', '订单号')
    ->copyableValue(function () {
        return route('orders.show', $this->id);
    });
```

## 与 copyable 的区别

| 特性 | copyable | copyableValue |
|------|----------|---------------|
| 复制内容 | 当前显示的值 | 可以指定任意值 |
| 灵活性 | 固定复制显示值 | 高度灵活 |
| 使用场景 | 简单的复制需求 | 需要复制不同值的场景 |

```php
// copyable - 复制显示的值
$grid->column('key')->copyable();  // 复制：显示的完整密钥

// copyableValue - 可以复制其他值
$grid->column('name')->copyableValue('key');  // 显示：名称，复制：密钥
```

## 注意事项

1. **安全性**：要复制的值会自动进行 HTML 实体编码，防止 XSS 攻击
2. **空值处理**：如果要复制的值为空，将不会显示复制图标
3. **闭包上下文**：在闭包中 `$this` 指向当前行的模型实例，可以访问所有字段

## 核心代码位置

- 扩展类：`app/Admin/Extensions/Grid/Displayers/CopyableValue.php`
- 注册文件：`app/Admin/bootstrap.php`