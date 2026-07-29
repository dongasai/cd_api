# A2O协议转换,在多工具调用时出错

## 问题现象

- request-logs/583: A2O 转换，应该是两个工具调用但只输出了一个，提前结束
- request-logs/584: A2O 转换，返回 400 错误 `"The reasoning_content in the thinking mode must be passed back to the API."`
- 正常对比: request-logs/516/578（A→A 不转换，无问题）

## 根因分析

### BUG 1（核心）: AnthropicMessagesDriver 多工具调用未关闭前一个块

**位置**: `app/Services/Protocol/Driver/AnthropicMessagesDriver.php:376-405`

**问题**: `buildToolUseEvent()` 方法在多工具调用时，当从第一个 tool_use（index=0）切换到第二个 tool_use（index=1）时：

1. `$hasIdAndName && $isFirstTime` = true
2. 检查 `$this->contentBlockStarted` → **false**（第 404 行被前一个 tool_use 设为 false）
3. **跳过了关闭前一个 tool_use 块** — 只检查了 `contentBlockStarted`，没检查 `toolCallBlockStarted`
4. `currentBlockIndex` 没有递增，两个 tool_use 块使用**相同的 index**
5. 客户端收到两个 tool_use 块使用同一个 index，第一个块没有 `content_block_stop` → 解析混乱

**修复**: 在第 386 行后增加对 `toolCallBlockStarted` 的检查和关闭逻辑

### BUG 2: StreamHandler::buildCompleteResponse 未收集 tool_calls

**位置**: `app/Services/Router/Handler/StreamHandler.php:466-533`

**问题**: `buildCompleteResponse` 方法只提取了文本和推理内容，完全未从 streamChunks 中收集 tool_calls

### BUG 3: A2O 请求转换丢失 reasoning_content

**位置**: `app/Services/Protocol/Driver/OpenAI/Message.php:288-293`

**现象**: 583 原始请求中 assistant 消息有 thinking 块，但转换后发给 DeepSeek 的请求中 `reasoning_content=MISSING`

**影响**: DeepSeek thinking mode 要求多轮对话传回 `reasoning_content`，丢失后返回 400

**状态**: 需确认 `Message::fromSharedDTO` 中 thinking 块转换是否被其他逻辑干扰

## 数据证据

| 请求 | 协议 | 上游模型 | 转换 | 结果 |
|------|------|----------|------|------|
| 516 | A→A | MiniMax-M3 | 不转换 | 正常 |
| 578 | A→A | deepseek-v4-flash | 不转换 | 正常 |
| 583 | A→O | deepseek-v4-flash | A2O | tool_use 只输出1个 |
| 584 | A→O | deepseek-v4-flash | A2O | 400 reasoning_content 缺失 |

### 583 上游响应（正确，2个tool_calls）
```
index=0 id=call_00_2B6YxpSnFQTjWi15NMgK2203 name=Read
index=1 id=call_01_jmdbmmfPt1yTeXQfO37U6453 name=Read
finish_reason: tool_use
```

### 583 原始请求 assistant 消息（有 thinking）
```
[2] thinking=111 text=66 tool_use[call_00_YTfkSXi7OXdt0moKgx8f8283 => Read]
[4] thinking=464 text=64 tool_use[call_00_c9kBQhtCdC5mFkPArlH28287 => Bash]
[6] thinking=93 text=153
```

### 584 转换后请求（reasoning_content 全部 MISSING）
```
[3] reasoning_content=MISSING tool_calls=1 [call_00_YTfkSXi7OXdt0moKgx8f8283 => Read]
[5] reasoning_content=MISSING tool_calls=1 [call_00_c9kBQhtCdC5mFkPArlH28287 => Bash]
[7] reasoning_content=MISSING content=array(1)
```

## 修复方案

### BUG 1 修复 — AnthropicMessagesDriver::buildToolUseEvent

**位置**: `app/Services/Protocol/Driver/AnthropicMessagesDriver.php:386`

在关闭 `contentBlockStarted` 后，增加对 `toolCallBlockStarted` 的检查：

```php
// 如果之前的工具调用块没关闭，先关闭（多工具调用场景）
if ($this->toolCallBlockStarted) {
    $blockStop = [
        'type' => self::EVENT_CONTENT_BLOCK_STOP,
        'index' => $this->currentBlockIndex,
    ];
    $output .= $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_STOP, $this->safeJsonEncode($blockStop));
    $this->toolCallBlockStarted = false;
    $this->currentBlockIndex++;
}
```

### BUG 2 修复 — StreamHandler::buildCompleteResponse

**位置**: `app/Services/Router/Handler/StreamHandler.php:491`

从 streamChunks 中收集 tool_calls 并添加到响应：

```php
// 提取 tool_calls（从流式块中收集）
$toolCalls = [];
foreach ($streamChunks as $chunk) {
    if ($chunk->toolCalls !== null) {
        foreach ($chunk->toolCalls as $tc) {
            $tcIndex = $tc['index'] ?? 0;
            if (isset($tc['id']) && ! empty($tc['id'])) {
                // 新工具调用开始
                $toolCalls[$tcIndex] = $tc;
            } elseif (isset($toolCalls[$tcIndex])) {
                // 参数增量追加
                if (isset($tc['function']['arguments'])) {
                    $toolCalls[$tcIndex]['function']['arguments'] .= $tc['function']['arguments'];
                }
            }
        }
    }
}
$toolCalls = array_values($toolCalls);

// 添加到响应
if (! empty($toolCalls)) {
    $response['choices'][0]['message']['tool_calls'] = $toolCalls;
}
```

### BUG 3 修复 — Message::fromSharedDTO thinking 转换

**位置**: `app/Services/Protocol/Driver/OpenAI/Message.php:288-293`

thinking 内容存在 `$block->thinking` 中，不是 `$block->text`：

```php
} elseif ($block->type === 'thinking') {
    // thinking 内容转换为 reasoningContent
    $thinkingText = $block->thinking ?? $block->text;
    if ($thinkingText !== null) {
        $reasoningContent = $reasoningContent ?? '';
        $reasoningContent .= $thinkingText;
    }
}
```

## 验证方法

重放 request-logs/583 验证修复：
```bash
php artisan cdapi:request:replay --request-id=583
```
