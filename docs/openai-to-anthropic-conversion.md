# OpenAI 请求转换为 Anthropic (Claude) 格式

本文档详细说明 new-api 项目中 OpenAI 请求转换为 Anthropic (Claude) 格式的实现逻辑。

## 一、核心文件位置

| 文件 | 说明 |
|------|------|
| `relay/channel/claude/relay-claude.go` | OpenAI → Claude 请求转换核心函数 |
| `dto/openai_request.go` | OpenAI 请求结构体定义 |
| `dto/claude.go` | Claude 请求结构体定义 |
| `service/convert.go` | Claude → OpenAI 反向转换函数 |
| `relay/reasonmap/reasonmap.go` | 停止原因映射 |

## 二、请求结构体对比

### 2.1 OpenAI 请求结构体

**文件**: `dto/openai_request.go`

```go
type GeneralOpenAIRequest struct {
    Model               string            `json:"model,omitempty"`
    Messages            []Message         `json:"messages,omitempty"`
    Prompt              any               `json:"prompt,omitempty"`
    Stream              *bool             `json:"stream,omitempty"`
    StreamOptions       *StreamOptions    `json:"stream_options,omitempty"`
    MaxTokens           *uint             `json:"max_tokens,omitempty"`
    MaxCompletionTokens *uint             `json:"max_completion_tokens,omitempty"`
    ReasoningEffort     string            `json:"reasoning_effort,omitempty"`
    Temperature         *float64          `json:"temperature,omitempty"`
    TopP                *float64          `json:"top_p,omitempty"`
    TopK                *int              `json:"top_k,omitempty"`
    Stop                any               `json:"stop,omitempty"`
    Tools               []ToolCallRequest `json:"tools,omitempty"`
    ToolChoice          any               `json:"tool_choice,omitempty"`
    ParallelTooCalls    *bool             `json:"parallel_tool_calls,omitempty"`
    WebSearchOptions    *WebSearchOptions `json:"web_search_options,omitempty"`
    // ... 其他字段
}

type Message struct {
    Role             string          `json:"role"`
    Content          any             `json:"content"`
    Name             *string         `json:"name,omitempty"`
    ReasoningContent string          `json:"reasoning_content,omitempty"`
    ToolCalls        json.RawMessage `json:"tool_calls,omitempty"`
    ToolCallId       string          `json:"tool_call_id,omitempty"`
}
```

### 2.2 Claude 请求结构体

**文件**: `dto/claude.go`

```go
type ClaudeRequest struct {
    Model            string          `json:"model"`
    Prompt           string          `json:"prompt,omitempty"`
    System           any             `json:"system,omitempty"`        // 独立字段
    Messages         []ClaudeMessage `json:"messages,omitempty"`
    MaxTokens        *uint           `json:"max_tokens,omitempty"`
    StopSequences    []string        `json:"stop_sequences,omitempty"`
    Temperature      *float64        `json:"temperature,omitempty"`
    TopP             *float64        `json:"top_p,omitempty"`
    TopK             *int            `json:"top_k,omitempty"`
    Stream           *bool           `json:"stream,omitempty"`
    Tools            any             `json:"tools,omitempty"`
    ToolChoice       any             `json:"tool_choice,omitempty"`
    Thinking         *Thinking       `json:"thinking,omitempty"`
    // ... 其他字段
}

type ClaudeMessage struct {
    Role    string `json:"role"`
    Content any    `json:"content"`
}

type ClaudeMediaMessage struct {
    Type        string               `json:"type,omitempty"`
    Text        *string              `json:"text,omitempty"`
    Source      *ClaudeMessageSource `json:"source,omitempty"`
    Thinking    *string              `json:"thinking,omitempty"`
    // tool_calls 相关
    Id        string `json:"id,omitempty"`
    Name      string `json:"name,omitempty"`
    Input     any    `json:"input,omitempty"`
    Content   any    `json:"content,omitempty"`
    ToolUseId string `json:"tool_use_id,omitempty"`
}
```

## 三、核心转换函数

### 3.1 入口函数

**文件**: `relay/channel/claude/relay-claude.go`

```go
func RequestOpenAI2ClaudeMessage(c *gin.Context, textRequest dto.GeneralOpenAIRequest) (*dto.ClaudeRequest, error)
```

### 3.2 Adaptor 入口

**文件**: `relay/channel/claude/adaptor.go`

```go
func (a *Adaptor) ConvertOpenAIRequest(c *gin.Context, info *relaycommon.RelayInfo, request *dto.GeneralOpenAIRequest) (any, error) {
    if request == nil {
        return nil, errors.New("request is nil")
    }
    return RequestOpenAI2ClaudeMessage(c, *request)
}
```

## 四、转换逻辑详解

### 4.1 工具 (Tools) 转换

**OpenAI 格式**:
```json
{
  "type": "function",
  "function": {
    "name": "get_weather",
    "description": "Get weather info",
    "parameters": {
      "type": "object",
      "properties": {
        "location": {"type": "string"}
      },
      "required": ["location"]
    }
  }
}
```

**Claude 格式**:
```json
{
  "name": "get_weather",
  "description": "Get weather info",
  "input_schema": {
    "type": "object",
    "properties": {
      "location": {"type": "string"}
    },
    "required": ["location"]
  }
}
```

**转换代码**:
```go
claudeTools := make([]any, 0, len(textRequest.Tools))
for _, tool := range textRequest.Tools {
    if params, ok := tool.Function.Parameters.(map[string]any); ok {
        claudeTool := dto.Tool{
            Name:        tool.Function.Name,
            Description: tool.Function.Description,
        }
        claudeTool.InputSchema = make(map[string]interface{})
        claudeTool.InputSchema["type"] = params["type"].(string)
        claudeTool.InputSchema["properties"] = params["properties"]
        claudeTool.InputSchema["required"] = params["required"]
        claudeTools = append(claudeTools, &claudeTool)
    }
}
```

### 4.2 Web Search 工具转换

将 OpenAI 的 `WebSearchOptions` 转换为 Claude 的 `ClaudeWebSearchTool`:

- 处理 `user_location` 参数
- 处理 `search_context_size` 参数

### 4.3 基本参数映射

| OpenAI 参数 | Claude 参数 | 说明 |
|-------------|-------------|------|
| `model` | `model` | 直接映射 |
| `max_tokens` / `max_completion_tokens` | `max_tokens` | 优先使用 `max_completion_tokens` |
| `temperature` | `temperature` | 直接映射 |
| `top_p` | `top_p` | 直接映射 |
| `top_k` | `top_k` | 直接映射 |
| `stop` | `stop_sequences` | 格式转换 |

**转换代码**:
```go
claudeRequest := dto.ClaudeRequest{
    Model:         textRequest.Model,
    StopSequences: nil,
    Temperature:   textRequest.Temperature,
    Tools:         claudeTools,
}
if maxTokens := textRequest.GetMaxTokens(); maxTokens > 0 {
    claudeRequest.MaxTokens = common.GetPointer(maxTokens)
}
if textRequest.TopP != nil {
    claudeRequest.TopP = common.GetPointer(*textRequest.TopP)
}
if textRequest.TopK != nil {
    claudeRequest.TopK = common.GetPointer(*textRequest.TopK)
}
```

### 4.4 Tool Choice 映射

| OpenAI | Claude |
|--------|--------|
| `"auto"` | `{"type": "auto"}` |
| `"required"` | `{"type": "any"}` |
| `"none"` | `{"type": "none"}` |
| `{"type": "function", "function": {"name": "xxx"}}` | `{"type": "tool", "name": "xxx"}` |

**转换代码**:
```go
func mapToolChoice(toolChoice any, parallelToolCalls *bool) *dto.ClaudeToolChoice {
    switch choice := toolChoice.(type) {
    case string:
        switch choice {
        case "auto":
            return &dto.ClaudeToolChoice{Type: "auto"}
        case "required":
            return &dto.ClaudeToolChoice{Type: "any"}
        case "none":
            return &dto.ClaudeToolChoice{Type: "none"}
        }
    case map[string]any:
        if choice["type"] == "function" {
            if fn, ok := choice["function"].(map[string]any); ok {
                return &dto.ClaudeToolChoice{
                    Type: "tool",
                    Name: fn["name"].(string),
                }
            }
        }
    }
    return nil
}
```

### 4.5 Thinking/Reasoning 模式处理

- 支持 `-thinking` 后缀模型
- 支持 `reasoning_effort` 参数
- 处理 `BudgetTokens` 计算

**转换逻辑**:
1. 检测模型名称是否包含 `-thinking` 后缀
2. 根据 `reasoning_effort` 值计算 `budget_tokens`
3. 设置 `ClaudeRequest.Thinking` 字段

### 4.6 消息格式转换

#### 4.6.1 System 消息

**OpenAI 格式**:
```json
{
  "messages": [
    {"role": "system", "content": "You are a helpful assistant."}
  ]
}
```

**Claude 格式**:
```json
{
  "system": "You are a helpful assistant.",
  "messages": []
}
```

**转换逻辑**: 提取 messages 数组中 `role: "system"` 的消息，放入独立的 `system` 字段。

#### 4.6.2 User 消息

**OpenAI 格式**:
```json
{"role": "user", "content": "Hello"}
```

**Claude 格式**:
```json
{"role": "user", "content": [{"type": "text", "text": "Hello"}]}
```

**转换逻辑**: 将字符串 content 转换为 `ClaudeMediaMessage` 数组。

#### 4.6.3 图片处理

**OpenAI 格式**:
```json
{
  "type": "image_url",
  "image_url": {
    "url": "data:image/png;base64,iVBORw0KGgo..."
  }
}
```

**Claude 格式**:
```json
{
  "type": "image",
  "source": {
    "type": "base64",
    "media_type": "image/png",
    "data": "iVBORw0KGgo..."
  }
}
```

**转换逻辑**:
1. 解析 `image_url.url` 中的 data URL
2. 提取 media_type 和 base64 数据
3. 构造 Claude 的 `source` 对象

#### 4.6.4 Assistant 消息 (含 Tool Calls)

**OpenAI 格式**:
```json
{
  "role": "assistant",
  "content": "Let me check that.",
  "tool_calls": [
    {
      "id": "call_123",
      "type": "function",
      "function": {
        "name": "get_weather",
        "arguments": "{\"location\": \"Beijing\"}"
      }
    }
  ]
}
```

**Claude 格式**:
```json
{
  "role": "assistant",
  "content": [
    {"type": "text", "text": "Let me check that."},
    {
      "type": "tool_use",
      "id": "call_123",
      "name": "get_weather",
      "input": {"location": "Beijing"}
    }
  ]
}
```

**转换逻辑**:
1. 将 `tool_calls` 转换为 `tool_use` 类型的 content block
2. 解析 `arguments` JSON 字符串为对象

#### 4.6.5 Tool 消息 (Tool Result)

**OpenAI 格式**:
```json
{
  "role": "tool",
  "tool_call_id": "call_123",
  "content": "The weather in Beijing is sunny."
}
```

**Claude 格式**:
```json
{
  "role": "user",
  "content": [
    {
      "type": "tool_result",
      "tool_use_id": "call_123",
      "content": "The weather in Beijing is sunny."
    }
  ]
}
```

**转换逻辑**:
1. 将 `role: "tool"` 消息转换为 `role: "user"` 消息
2. 构造 `tool_result` 类型的 content block
3. `tool_call_id` 映射为 `tool_use_id`

## 五、停止原因映射

**文件**: `relay/reasonmap/reasonmap.go`

### 5.1 Claude → OpenAI

| Claude `stop_reason` | OpenAI `finish_reason` |
|---------------------|------------------------|
| `stop_sequence` | `stop` |
| `end_turn` | `stop` |
| `max_tokens` | `length` |
| `tool_use` | `tool_calls` |
| `refusal` | `content_filter` |

### 5.2 OpenAI → Claude

| OpenAI `finish_reason` | Claude `stop_reason` |
|------------------------|---------------------|
| `stop` | `end_turn` |
| `length` / `max_tokens` | `max_tokens` |
| `tool_calls` | `tool_use` |
| `content_filter` | `refusal` |

## 六、反向转换 (Claude → OpenAI)

**文件**: `service/convert.go`

```go
func ClaudeToOpenAIRequest(claudeRequest dto.ClaudeRequest, info *relaycommon.RelayInfo) (*dto.GeneralOpenAIRequest, error)
```

主要转换逻辑:
1. 基本参数映射
2. 工具转换 (Claude `Tool` → OpenAI `ToolCallRequest`)
3. 消息转换 (Claude `ClaudeMessage` → OpenAI `Message`)
4. System 消息处理 (独立字段 → messages 数组)
5. Tool result 转换

## 七、关键差异总结

| 特性 | OpenAI 格式 | Claude 格式 |
|------|-------------|-------------|
| System 消息 | `messages` 数组中的 `role: "system"` | 独立的 `system` 字段 |
| 图片格式 | `image_url` 对象 | `source` 对象 (base64) |
| 工具调用 | `tool_calls` 数组 | `tool_use` 类型的 content block |
| 工具结果 | `role: "tool"` 的消息 | `tool_result` 类型的 content block |
| 停止序列 | `stop` 字段 | `stop_sequences` 数组 |
| Tool Choice | 字符串或对象 | 对象，类型映射不同 |
| Thinking | `reasoning_effort` 字符串 | `thinking` 对象 |
| 参数 schema | `function.parameters` | `input_schema` |

## 八、响应转换

### 8.1 非流式响应

**文件**: `relay/channel/claude/relay-claude.go`

```go
func ResponseClaude2OpenAI(response *dto.ClaudeResponse) *dto.OpenAITextResponse
```

### 8.2 流式响应

```go
func StreamResponseClaude2OpenAI(claudeResponse *dto.ClaudeStreamResponse) (*dto.ChatCompletionsStreamResponse, string)
```

## 九、相关文件汇总

| 文件路径 | 功能说明 |
|----------|----------|
| `relay/channel/claude/relay-claude.go` | 请求/响应转换核心实现 |
| `relay/channel/claude/adaptor.go` | Adaptor 入口 |
| `dto/openai_request.go` | OpenAI 请求结构体 |
| `dto/claude.go` | Claude 请求结构体 |
| `service/convert.go` | 反向转换函数 |
| `relay/reasonmap/reasonmap.go` | 停止原因映射 |
