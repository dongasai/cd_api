# Anthropic Driver 结构优化

**日期**: 2026-03-22

## 背景

对比官方 Anthropic SDK (`laravel/vendor/anthropic-ai/sdk/src`) 与项目实现 (`laravel/app/Services/Protocol/Driver/Anthropic`)，发现并修复了若干结构问题。

## 发现的问题

### 高优先级问题

1. **ContentBlock 缺少 citations 字段**
   - 官方 SDK TextBlock 包含 `citations` 字段，用于文本引用支持
   - 项目实现缺少此字段，导致 PDF、纯文本、内容块引用功能不完整

2. **Message 类命名混淆**
   - 项目的 `Message` 类对应官方 SDK 的 `MessageParam`（请求参数）
   - 官方 SDK 的 `Message` 类对应项目的 `MessagesResponse`（响应）
   - 缺少明确说明，容易造成混淆

### 中优先级问题

3. **Container 类型定义不完整**
   - `MessagesResponse` 中 `container` 字段定义为 `?array`
   - 官方 SDK 有专门的 `Container` 类
   - 缺少类型安全保证

4. **caller 字段注释不够详细**
   - ContentBlock 的 `caller` 字段类型为 `?array`
   - 缺少对其可能值（direct、server_tool 等）的说明

## 解决方案

### 1. 为 ContentBlock 添加 citations 字段

**修改文件**: `laravel/app/Services/Protocol/Driver/Anthropic/ContentBlock.php`

**变更内容**:
```php
// 构造函数添加参数
public ?array $citations = null,

// fromArray 添加解析
citations: $data['citations'] ?? null,

// toArray 添加输出
if ($this->citations !== null) {
    $result['citations'] = $this->citations;
}

// 验证规则添加
'citations' => 'nullable|array',
```

**影响范围**:
- 支持文本引用功能（PDF、纯文本、内容块）
- 向后兼容，不影响现有功能

### 2. 明确 Message 类用途

**修改文件**: `laravel/app/Services/Protocol/Driver/Anthropic/Message.php`

**变更内容**:
```php
/**
 * Anthropic 消息参数结构体
 *
 * 注意：此类对应官方 SDK 的 MessageParam（请求消息参数）
 * 官方 SDK 的 Message 类对应本项目中的 MessagesResponse（响应消息）
 *
 * @see https://docs.anthropic.com/en/api/messages#request-body-messages
 * @see \Anthropic\Messages\MessageParam 官方 SDK 对应类
 */
class Message
```

**效果**:
- 明确类的用途和对应关系
- 避免开发者混淆

### 3. 创建 Container 类

**新增文件**: `laravel/app/Services/Protocol/Driver/Anthropic/Container.php`

**类结构**:
```php
class Container
{
    public string $id = '';              // 容器标识符
    public ?string $expires_at = null;   // 过期时间
    public array $additionalData = [];   // 额外字段透传
}
```

**集成到 MessagesResponse**:
```php
// 构造函数参数类型变更
public ?Container $container = null,

// fromArray 解析
$container = Container::fromArray($data['container']);

// toArray 输出
$result['container'] = $this->container->toArray();
```

**效果**:
- 提供类型安全
- 支持代码执行工具的容器管理
- 便于 IDE 自动补全和静态分析

### 4. 增强 caller 字段注释

**修改文件**: `laravel/app/Services/Protocol/Driver/Anthropic/ContentBlock.php`

**变更内容**:
```php
/**
 * @param  array|null  $caller  调用者信息 (tool_use 类型，包含 type: direct|server_tool 等信息)
 */
public ?array $caller = null,
```

**说明**:
- `caller` 字段在 `tool_use` 类型的内容块中使用
- 可能的值包括：
  - `{type: 'direct'}` - 直接工具调用
  - `{type: 'server_tool', name: '...'}` - 服务器端工具调用
  - 其他类型根据 Anthropic API 更新

## 测试验证

### 验证方法

1. **结构对比**
   ```bash
   # 对比官方 SDK 字段
   diff -u <(grep -E 'public \$' vendor/anthropic-ai/sdk/src/Messages/ContentBlock.php) \
           <(grep -E 'public \$' app/Services/Protocol/Driver/Anthropic/ContentBlock.php)
   ```

2. **单元测试**
   - 测试 ContentBlock 的 fromArray/toArray 转换
   - 测试 Container 类的序列化/反序列化
   - 测试 MessagesResponse 的完整流程

3. **集成测试**
   ```bash
   cd laravel && php artisan cdapi:proxy:test --stream
   ```

### 验证结果

- ✅ ContentBlock 支持 citations 字段
- ✅ Message 类注释明确说明用途
- ✅ Container 类正常工作
- ✅ 向后兼容，现有功能不受影响

## 命名风格说明

### 字段命名差异

项目使用 snake_case，官方 SDK 使用 camelCase：

| 官方 SDK | 项目实现 | API 传输 |
|---------|---------|---------|
| `inputTokens` | `input_tokens` | `input_tokens` |
| `outputTokens` | `output_tokens` | `output_tokens` |
| `stopReason` | `stop_reason` | `stop_reason` |

**说明**:
- Anthropic API 使用 snake_case 传输
- 项目保持与 API 一致的命名
- 官方 SDK 内部转换为 camelCase
- 两种方式均正确，项目方案更贴近 API

## 残留问题

### 低优先级

1. **ToolUseBlock 专门类**
   - 当前 ContentBlock 统一处理所有类型
   - 可考虑为每种类型创建专门类（如 TextBlock、ToolUseBlock）
   - 优点：类型更安全
   - 缺点：增加复杂度

2. **ServerToolUseBlock 支持**
   - 已在 ContentBlock 类型常量中定义
   - 字段处理已支持
   - 可根据需要进一步细化

## 影响评估

### 代码变更

- **新增**: 1 个文件 (Container.php)
- **修改**: 3 个文件 (ContentBlock.php, Message.php, MessagesResponse.php)
- **删除**: 0 个文件

### 兼容性

- ✅ 向后兼容
- ✅ 不影响现有功能
- ✅ 仅添加新字段和类型

### 性能影响

- 可忽略（仅增加一个轻量级类）

## 参考资料

- [Anthropic Messages API 文档](https://docs.anthropic.com/en/api/messages)
- 官方 SDK 源码: `laravel/vendor/anthropic-ai/sdk/src/Messages/`
- 项目实现: `laravel/app/Services/Protocol/Driver/Anthropic/`

## 总结

通过对比官方 SDK，发现并修复了 4 个结构问题：
- 2 个高优先级问题已修复
- 2 个中优先级问题已修复

项目实现现已与官方 SDK 保持一致，具有更好的类型安全性和可维护性。