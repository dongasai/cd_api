# JSON Schema 空 properties 字段修复

## 时间
2026-05-12 22:30

## 问题

DeepSeek 提供商报错：
```
Invalid schema for function 'CronList': [] is not of type "object"
```

### 根本原因

MCP Server 返回的工具定义中，`inputSchema.properties` 字段为空数组 `[]`，但 JSON Schema 规范要求 `properties` 必须是 object 类型。

实际发送的参数：
```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "properties": [],  // ❌ 这是数组，应该是 {} 或 object
  "additionalProperties": false
}
```

## 修复方案

### 修复位置

**1. McpClientService.php:186-197** - 从源头修复

在获取 MCP 工具定义后，立即修复空的 `properties` 字段：

```php
foreach ($result->tools as $tool) {
    // 修复 JSON Schema 中的空 properties 字段
    // MCP Server 可能返回 properties: []，但 JSON Schema 要求 properties 必须是 object 类型
    $inputSchema = $tool->inputSchema;
    if (isset($inputSchema['properties']) && is_array($inputSchema['properties']) && empty($inputSchema['properties'])) {
        $inputSchema['properties'] = new \stdClass;
    }

    $tools[] = [
        'name' => $tool->name,
        'description' => $tool->description,
        'input_schema' => $inputSchema,
    ];
}
```

**2. FunctionDef::toArray()** - 序列化时兜底修复

在 OpenAI 格式工具定义序列化时，再次检查并修复：

```php
if ($this->parameters !== null) {
    // 修复 JSON Schema 中的空 properties 字段
    // 某些上游可能返回 properties: []，但 JSON Schema 要求必须是 object 类型
    $parameters = $this->parameters;
    if (isset($parameters['properties']) && is_array($parameters['properties']) && empty($parameters['properties'])) {
        $parameters['properties'] = new \stdClass;
    }
    $result['parameters'] = $parameters;
}
```

### 修复原理

使用 PHP 的 `\stdClass` 对象，在 JSON 编码时会输出为 `{}` 而不是 `[]`：

```php
json_encode([])              // 输出: []
json_encode(new \stdClass)   // 输出: {}
```

## 影响范围

- 所有使用 MCP 工具的请求
- DeepSeek、OpenAI 等严格验证 JSON Schema 的提供商
- 61 个 MCP 工具定义中的空参数工具（如 CronList）

## 测试验证

修复后，工具定义变为：
```json
{
  "type": "function",
  "function": {
    "name": "CronList",
    "parameters": {
      "type": "object",
      "properties": {},  // ✅ 现在是 object
      "additionalProperties": false
    }
  }
}
```

## 相关文件

- `laravel/app/Services/McpClientService.php`
- `laravel/app/Services/Protocol/Driver/OpenAI/FunctionDef.php`