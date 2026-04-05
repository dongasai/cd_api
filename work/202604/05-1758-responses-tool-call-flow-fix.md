# Responses API 工具调用流程修复

## 问题描述

使用 OpenAI 官方 codex CLI 客户端测试 Responses API 时遇到两个问题：

1. **`developer` 角色不被支持**：上游 API（硅基流动）不支持 `developer` 角色，只支持 `system`, `user`, `assistant`, `tool`
2. **`function_call_output` 转换不完整**：转换后的消息缺少必要的上下文，导致 "No user query found in messages" 错误

## 根因分析

### 问题 1：developer 角色转换

错误日志：
```
Input tag 'developer' found using 'role' does not match any of the expected tags: 'system', 'user', 'assistant', 'tool'
```

虽然 `OpenAIResponsesRequest.php` 中已有角色转换逻辑，但 `Message::fromSharedDTO()` 直接使用 `$dto->role->value` 而没有转换。

### 问题 2：function_call_output 转换

Responses API 的工具调用流程：
1. 模型返回 `function_call` 输出项
2. 客户端执行工具
3. 客户端提交 `function_call_output`（包含 `call_id` 和 `output`）

之前的转换逻辑只是简单地将 `function_call_output` 转为 `tool` 消息，但没有：
- 关联对应的 `function_call`
- 添加后续用户提示

## 修复内容

### 1. `Message.php` - developer 角色转换

**toArray() 方法**：
```php
public function toArray(): array
{
    // 转换角色：developer -> user（上游 API 不支持 developer 角色）
    $role = $this->role;
    if ($role === 'developer') {
        $role = 'user';
    }

    $result = [
        'role' => $role,
    ];
    // ...
}
```

**fromSharedDTO() 方法**：
```php
// 转换角色：developer -> user（上游 API 不支持 developer 角色）
$role = $dto->role->value;
if ($role === 'developer') {
    $role = 'user';
}

return new self(
    role: $role,
    // ...
);
```

### 2. `OpenAIResponsesRequest.php` - function_call_output 转换

重构 `convertInputItemsToMessages()` 方法：

```php
private function convertInputItemsToMessages(array $items): array
{
    $messages = [];

    // 收集所有 function_call 和 function_call_output
    $functionCalls = [];
    $functionCallOutputs = [];

    foreach ($items as $item) {
        if (! is_array($item)) {
            continue;
        }

        $type = $item['type'] ?? '';

        if ($type === 'function_call') {
            $callId = $item['call_id'] ?? $item['id'] ?? '';
            $functionCalls[$callId] = $item;
        } elseif ($type === 'function_call_output') {
            $callId = $item['call_id'] ?? '';
            $functionCallOutputs[$callId] = $item;
        }
    }

    // 处理每个 item
    foreach ($items as $item) {
        $type = $item['type'] ?? '';

        if ($type === 'function_call_output') {
            $callId = $item['call_id'] ?? '';

            // 如果有对应的 function_call，先添加 assistant 消息
            if (isset($functionCalls[$callId])) {
                $fc = $functionCalls[$callId];
                $messages[] = [
                    'role' => 'assistant',
                    'tool_calls' => [[
                        'id' => $fc['call_id'] ?? $fc['id'] ?? '',
                        'type' => 'function',
                        'function' => [
                            'name' => $fc['name'] ?? '',
                            'arguments' => $fc['arguments'] ?? '',
                        ],
                    ]],
                ];
                unset($functionCalls[$callId]);
            }

            // 然后添加 tool 消息
            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => $callId,
                'content' => $item['output'] ?? '',
            ];
        }
        // ... 其他类型处理
    }

    // 如果最后一条消息是 tool，添加用户提示
    if ($lastMessage !== null && $lastMessage['role'] === 'tool') {
        $messages[] = ['role' => 'user', 'content' => '请继续处理工具执行结果。'];
    }

    return $messages;
}
```

## 验证结果

### API 测试

```bash
curl -X POST http://192.168.4.107:32126/api/openai/v1/responses \
  -H "Authorization: Bearer sk-xxx" \
  -H "Content-Type: application/json" \
  -d '{
    "model":"Qwen/Qwen3.5-397B-A17B",
    "input":[
      {"type":"function_call","call_id":"call_123","name":"read_file","arguments":"{\"path\":\"demo.md\"}"},
      {"type":"function_call_output","call_id":"call_123","output":"# Demo 文件\n\n这是一个测试文件。"}
    ],
    "stream":false
  }'
```

返回正常响应，模型正确处理了工具执行结果。

### Codex 客户端测试

```bash
codex exec --skip-git-repo-check "读取 demo.md 文件内容，然后告诉我里面有什么"
```

输出：
```
exec
/bin/bash -lc 'cat demo.md' in /data/project/ai_proxy/coding_api
 succeeded in 0ms:
# Demo 文件
...
```

工具调用成功执行，模型正确返回了文件内容。

## 文件变更

- `laravel/app/Services/Protocol/Driver/OpenAI/Message.php` - 添加 developer 角色转换
- `laravel/app/Services/Protocol/Driver/OpenAIResponses/OpenAIResponsesRequest.php` - 重构 function_call_output 转换

## 相关工作日志

- [05-1740-responses-call-id-fix.md](./05-1740-responses-call-id-fix.md) - call_id 字段修复
- [05-1425-responses-streaming-fix.md](./05-1425-responses-streaming-fix.md) - 流式事件格式修复