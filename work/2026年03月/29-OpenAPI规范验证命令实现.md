# OpenAPI 规范验证命令实现

## 完成内容

### 1. 安装依赖包

安装了opis/json-schema和symfony/yaml依赖包：

```bash
composer require opis/json-schema symfony/yaml
```

- **opis/json-schema 2.6.0**: JSON Schema验证库（支持draft-2020-12，兼容OpenAPI 3.1.0）
- **symfony/yaml 7.4**: YAML解析库（用于解析OpenAPI规范文件）

### 2. 下载OpenAPI规范文件

使用已有的更新命令下载了官方OpenAPI规范文件：

```bash
php artisan cdapi:update-openapi-spec --force     # 2.44 MB
php artisan cdapi:update-anthropic-spec --force   # 0.78 MB
```

文件位置：
- OpenAI: `storage/openai-openapi.yml`
- Anthropic: `storage/anthropic-openapi.yml`

### 3. 创建验证命令

**文件**: `app/Console/Commands/ValidateApiRequest.php`

**命令**: `cdapi:validate-request {id}`

**功能**:
- 从`channel_request_logs`表查询指定ID的请求日志
- 自动识别协议类型（openai/anthropic）
- 根据API路径匹配对应的Schema名称
- 使用Laravel Validator验证请求体的关键字段
- 支持显示完整请求体内容（`--show-body`）

**使用示例**:

```bash
# 验证OpenAI请求
php artisan cdapi:validate-request 2528

# 验证Anthropic请求
php artisan cdapi:validate-request 2495

# 显示完整请求体
php artisan cdapi:validate-request 2528 --show-body

# 手动指定协议类型
php artisan cdapi:validate-request 2528 --protocol=openai
```

### 4. 实现细节

#### 协议类型判断

从`provider`字段自动推断协议：
- **OpenAI协议**: openai, azure, deepseek, moonshot, zhipu, qwen
- **Anthropic协议**: anthropic, claude
- 默认使用OpenAI协议

#### API路径映射

支持的API路径：
- **OpenAI**:
  - `/v1/chat/completions` → `CreateChatCompletionRequest`
  - `/chat/completions` → `CreateChatCompletionRequest`
- **Anthropic**:
  - `/v1/messages` → `CreateMessageParams`
  - `/messages` → `CreateMessageParams`

#### 验证规则

使用Laravel Validator进行基本验证（OpenAPI Schema的简化版本）：

**OpenAI ChatCompletionRequest关键规则**:
```php
'model' => 'required|string',
'messages' => 'required|array|min:1',
'messages.*.role' => 'required|string|in:system,user,assistant,tool,function',
'messages.*.content' => 'required_without:messages.*.tool_calls',
'temperature' => 'nullable|numeric|between:0,2',
'max_tokens' => 'nullable|integer|min:1',
'tools' => 'nullable|array',
'tool_choice' => 'nullable',
```

**Anthropic CreateMessageParams关键规则**:
```php
'model' => 'required|string',
'messages' => 'required|array|min:1',
'messages.*.role' => 'required|string|in:user,assistant',
'messages.*.content' => 'required',
'max_tokens' => 'required|integer|min:1',
'system' => 'nullable', // 可以是string或array
'temperature' => 'nullable|numeric|between:0,1',
'tools' => 'nullable|array',
```

## 测试结果

### 测试1：OpenAI请求验证（ID: 2528）

```
✅ 请求体基本验证通过！
注意：仅验证了关键字段，完整OpenAPI Schema验证暂未实现
```

请求体大小：134074字节（包含完整消息历史）

### 测试2：Anthropic请求验证（ID: 2495）

```
✅ 请求体基本验证通过！
注意：仅验证了关键字段，完整OpenAPI Schema验证暂未实现
```

请求体大小：166667字节

## 遇到的挑战

### OpenAPI Schema引用解析问题

OpenAPI规范使用了复杂的`$ref`引用结构：

```yaml
CreateChatCompletionRequest:
  allOf:
    - $ref: '#/components/schemas/CreateModelResponseProperties'
    - type: object
      properties:
        messages: ...
```

opis/json-schema库在解析内部引用时遇到困难，无法正确解析：
- `#/components/schemas/CreateModelResponseProperties`
- 内部URI引用机制复杂

**解决方案**: 改用Laravel Validator进行关键字段验证，提供实用的基本验证功能。

## 功能特点

### 优势

1. **实用性强**: 验证关键字段，快速发现明显错误
2. **易于使用**: 只需提供日志ID即可验证
3. **自动识别**: 自动判断协议类型和Schema名称
4. **详细输出**: 显示完整的请求日志信息
5. **可选显示**: 可查看完整请求体内容

### 当前限制

仅验证关键字段，未实现完整的OpenAPI Schema验证（包括所有字段、嵌套结构、枚举值等）。

### 未来改进方向

1. 实现完整的OpenAPI Schema验证（解决$ref引用解析问题）
2. 支持更多API路径的验证
3. 提供更详细的错误定位（字段路径）
4. 支持批量验证多个请求日志

## 相关文件

- [ValidateApiRequest.php](laravel/app/Console/Commands/ValidateApiRequest.php)
- [OpenAPI规范验证工具方案](work/2026年03月/29-OpenAPI规范验证工具方案.md)
- [UpdateOpenApiSpec.php](laravel/app/Console/Commands/UpdateOpenApiSpec.php)
- [UpdateAnthropicApiSpec.php](laravel/app/Console/Commands/UpdateAnthropicApiSpec.php)