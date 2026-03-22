# Anthropic Driver 与 Shared DTO 层结构优化

**日期**: 2026-03-22

## 背景

对比官方 Anthropic SDK (`laravel/vendor/anthropic-ai/sdk/src`) 与项目实现 (`laravel/app/Services/Protocol/Driver/Anthropic`)，发现并修复了若干结构问题，并同步更新了中间层 DTO (`laravel/app/Services/Shared`)。

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

### Shared DTO 层同步问题

5. **Shared\DTO\ContentBlock 缺少字段**
   - 缺少 `citations` 字段支持
   - 缺少 `caller` 字段支持
   - 导致数据在 Driver 和 DTO 层之间转换时丢失

6. **Shared\DTO\Usage 缺少新字段**
   - 缺少 `cache_creation` 对象
   - 缺少 `inference_geo` 推理地理位置
   - 缺少 `server_tool_use` 服务端工具使用
   - 缺少 `service_tier` 服务层级

7. **Shared\DTO\Response 缺少 Container 支持**
   - 缺少 `container` 字段
   - 无法在中间层传递容器信息

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

### 5. 同步更新 Shared DTO 层

#### ContentBlock DTO 更新

**修改文件**: `laravel/app/Services/Shared/DTO/ContentBlock.php`

**变更内容**:
```php
// 构造函数添加参数
public ?array $citations = null, // 文本引用列表
public ?array $caller = null,    // 工具调用者信息

// fromAnthropic 添加解析
'text' => new self(
    type: 'text',
    text: $block['text'] ?? '',
    citations: $block['citations'] ?? null, // 新增
    cacheControl: $cacheControl,
),
'tool_use' => new self(
    type: 'tool_use',
    toolId: $block['id'] ?? null,
    toolName: $block['name'] ?? null,
    toolInput: $block['input'] ?? null,
    caller: $block['caller'] ?? null, // 新增
    cacheControl: $cacheControl,
),

// toAnthropic 添加输出
'text' => [
    'type' => 'text',
    'text' => $this->text ?? '',
    ...($this->citations !== null ? ['citations' => $this->citations] : []),
],
'tool_use' => [
    'type' => 'tool_use',
    'id' => $this->toolId,
    'name' => $this->toolName,
    'input' => $this->toolInput ?? [],
    ...($this->caller !== null ? ['caller' => $this->caller] : []),
],

// toArray 添加字段
'citations' => $this->citations,
'caller' => $this->caller,
```

**效果**:
- Shared DTO 支持 citations 和 caller 字段
- 数据在 Driver 和 DTO 层之间完整传递
- 向后兼容，不影响现有功能

#### Usage DTO 更新

**修改文件**: `laravel/app/Services/Shared/DTO/Usage.php`

**变更内容**:
```php
// 构造函数添加参数
public ?array $cacheCreation = null,     // 缓存创建详情（Anthropic）
public ?string $inferenceGeo = null,     // 推理地理位置（Anthropic）
public ?array $serverToolUse = null,     // 服务端工具使用（Anthropic）
public ?string $serviceTier = null,      // 服务层级（Anthropic）

// fromAnthropic 添加解析
cacheCreation: $usage['cache_creation'] ?? null,
inferenceGeo: $usage['inference_geo'] ?? null,
serverToolUse: $usage['server_tool_use'] ?? null,
serviceTier: $usage['service_tier'] ?? null,

// merge 添加合并
cacheCreation: $this->cacheCreation ?? $other->cacheCreation,
inferenceGeo: $this->inferenceGeo ?? $other->inferenceGeo,
serverToolUse: $this->serverToolUse ?? $other->serverToolUse,
serviceTier: $this->serviceTier ?? $other->serviceTier,

// toAnthropic 添加输出
if ($this->cacheCreation !== null) {
    $result['cache_creation'] = $this->cacheCreation;
}
if ($this->inferenceGeo !== null) {
    $result['inference_geo'] = $this->inferenceGeo;
}
// ... 其他字段

// toArray 添加字段
'cache_creation' => $this->cacheCreation,
'inference_geo' => $this->inferenceGeo,
'server_tool_use' => $this->serverToolUse,
'service_tier' => $this->serviceTier,
```

**效果**:
- 支持官方 SDK 新增的所有 Usage 字段
- 完整的数据传递和转换
- 支持流式累加场景

#### Response DTO 更新

**修改文件**: `laravel/app/Services/Shared/DTO/Response.php`

**变更内容**:
```php
// 构造函数添加参数
public ?array $container = null, // Container info (Anthropic)

// toAnthropic 添加输出
if ($this->container !== null) {
    $result['container'] = $this->container;
}

// toArray 添加字段
'container' => $this->container,
```

**效果**:
- 支持 Container 信息在中间层传递
- 保持数据完整性

### 6. 更新 Driver 层转换方法

#### Anthropic\Usage 转换方法更新

**修改文件**: `laravel/app/Services/Protocol/Driver/Anthropic/Usage.php`

**变更内容**:
```php
// toSharedDTO 添加新字段
public function toSharedDTO(): SharedUsage
{
    return new SharedUsage(
        // ... 原有字段
        cacheCreation: $this->cache_creation?->toArray(),
        inferenceGeo: $this->inference_geo,
        serverToolUse: $this->server_tool_use,
        serviceTier: $this->service_tier,
    );
}

// fromSharedDTO 添加新字段
public static function fromSharedDTO(object $dto): static
{
    $cacheCreation = null;
    if (isset($dto->cacheCreation) && is_array($dto->cacheCreation)) {
        $cacheCreation = CacheCreation::fromArray($dto->cacheCreation);
    }

    return new self(
        // ... 原有字段
        cache_creation: $cacheCreation,
        inference_geo: $dto->inferenceGeo ?? null,
        server_tool_use: $dto->serverToolUse ?? null,
        service_tier: $dto->serviceTier ?? null,
    );
}
```

#### Anthropic\MessagesResponse 转换方法更新

**修改文件**: `laravel/app/Services/Protocol/Driver/Anthropic/MessagesResponse.php`

**变更内容**:
```php
// toSharedDTO 添加 container
public function toSharedDTO(): SharedResponse
{
    return new SharedResponse(
        // ... 原有字段
        container: $this->container?->toArray(),
    );
}

// fromSharedDTO 添加 container 解析
$container = null;
if (isset($dto->container) && is_array($dto->container)) {
    $container = Container::fromArray($dto->container);
}

return new self(
    // ... 原有字段
    container: $container,
);
```

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
   - 测试 Shared DTO 与 Driver 层的转换

3. **集成测试**
   ```bash
   cd laravel && php artisan cdapi:proxy:test --stream
   ```

### 验证结果

- ✅ ContentBlock 支持 citations 和 caller 字段
- ✅ Message 类注释明确说明用途
- ✅ Container 类正常工作
- ✅ Shared DTO 支持所有新字段
- ✅ Driver 与 DTO 层转换正确
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

**Driver 层**:
- **新增**: 1 个文件 (Container.php)
- **修改**: 4 个文件 (ContentBlock.php, Message.php, Usage.php, MessagesResponse.php)
- **删除**: 0 个文件

**Shared DTO 层**:
- **新增**: 0 个文件
- **修改**: 3 个文件 (ContentBlock.php, Usage.php, Response.php)
- **删除**: 0 个文件

### 兼容性

- ✅ 向后兼容
- ✅ 不影响现有功能
- ✅ 仅添加新字段和类型
- ✅ Driver 与 DTO 层转换正确

### 性能影响

- 可忽略（仅增加轻量级类和字段）

## 架构说明

### 三层架构

```
Protocol Driver 层 (Anthropic/OpenAI)
        ↕
   Shared DTO 层 (中间层)
        ↕
    业务逻辑层
```

**职责划分**:

1. **Protocol Driver 层** (`app/Services/Protocol/Driver/`)
   - 处理特定协议（Anthropic/OpenAI）的数据结构
   - 提供序列化/反序列化能力
   - 与上游 API 保持一致

2. **Shared DTO 层** (`app/Services/Shared/DTO/`)
   - 提供协议无关的统一数据结构
   - 作为中间层转换和传递数据
   - 支持多协议互转

3. **业务逻辑层**
   - 使用 Shared DTO 进行业务处理
   - 不关心具体协议细节

### 数据流转示例

```
Anthropic API Response
    ↓
Anthropic\MessagesResponse (Driver 层)
    ↓ toSharedDTO()
Shared\DTO\Response (DTO 层)
    ↓ toOpenAI()
OpenAI 格式输出
```

**关键点**:
- Driver 层与官方 SDK 保持一致
- DTO 层提供协议无关的结构
- 转换方法确保数据不丢失

## 参考资料

- [Anthropic Messages API 文档](https://docs.anthropic.com/en/api/messages)
- 官方 SDK 源码: `laravel/vendor/anthropic-ai/sdk/src/Messages/`
- 项目实现: `laravel/app/Services/Protocol/Driver/Anthropic/`

## 总结

通过对比官方 SDK，发现并修复了 7 个结构问题：

**Driver 层（已完成）**:
- 2 个高优先级问题已修复
- 2 个中优先级问题已修复

**Shared DTO 层（已完成）**:
- 3 个同步问题已修复

项目实现现已与官方 SDK 保持一致，具有更好的类型安全性和可维护性。Driver 层与 DTO 层之间的数据流转完整，确保了多协议互转的正确性。

### 改进效果

1. **功能完整**
   - 支持文本引用（citations）功能
   - 支持工具调用者（caller）信息
   - 支持容器（Container）管理
   - 支持 Usage 所有新字段

2. **类型安全**
   - Container 从 `?array` 提升为专门类
   - 所有字段都有明确类型定义
   - IDE 自动补全和静态分析更友好

3. **代码清晰**
   - Message 类用途明确
   - Driver 和 DTO 职责分明
   - 数据流转路径清晰

4. **可维护性**
   - 与官方 SDK 保持一致
   - 易于跟进 API 更新
   - 代码注释完整