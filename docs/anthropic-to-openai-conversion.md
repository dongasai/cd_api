# Anthropic (Claude) 请求转换为 OpenAI 格式

本文档详细说明 new-api 项目中 Anthropic (Claude) 请求转换为 OpenAI 格式的实现逻辑。

## 一、核心文件位置

| 文件 | 说明 |
|------|------|
| `service/convert.go` | Claude → OpenAI 请求转换核心函数 |
| `dto/claude.go` | Claude 请求结构体定义 |
| `dto/openai_request.go` | OpenAI 请求结构体定义 |
| `relay/channel/claude/relay-claude.go` | OpenAI → Claude 反向转换函数 |
| `relay/reasonmap/reasonmap.go` | 停止原因映射 |

## 二、请求结构体对比

### 2.1 Claude 请求结构体

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
    Type         string               `json:"type,omitempty"`
    Text         *string              `json:"text,omitempty"`
    Source       *ClaudeMessageSource `json:"source,omitempty"`
    Thinking     *string              `json:"thinking,omitempty"`
    CacheControl json.RawMessage      `json:"cache_control,omitempty"`
    // tool_calls 相关
    Id        string `json:"id,omitempty"`
    Name      string `json:"name,omitempty"`
    Input     any    `json:"input,omitempty"`
    Content   any    `json:"content,omitempty"`
    ToolUseId string `json:"tool_use_id,omitempty"`
}
```

### 2.2 OpenAI 请求结构体

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

type MediaContent struct {
    Type       string `json:"type"`
    Text       string `json:"text,omitempty"`
    ImageUrl   any    `json:"image_url,omitempty"`
    InputAudio any    `json:"input_audio,omitempty"`
    File       any    `json:"file,omitempty"`
    VideoUrl   any    `json:"video_url,omitempty"`
    CacheControl json.RawMessage `json:"cache_control,omitempty"`
}
```

## 三、核心转换函数

### 3.1 入口函数

**文件**: `service/convert.go`

```go
func ClaudeToOpenAIRequest(claudeRequest dto.ClaudeRequest, info *relaycommon.RelayInfo) (*dto.GeneralOpenAIRequest, error)
```

### 3.2 转换流程

```
┌─────────────────────────────────────────────────────────────────────┐
│                     客户端发送 Claude 格式请求                        │
│                  POST /v1/messages (Claude 格式)                     │
└─────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│  router/relay-router.go                                             │
│  路由到 controller.Relay(c, types.RelayFormatClaude)                │
└─────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│  controller/relay.go                                                │
│  根据 RelayFormat 调用 relay.ClaudeHelper(c, relayInfo)             │
└─────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│  service/convert.go                                                 │
│  ClaudeToOpenAIRequest() 核心转换函数                                 │
└─────────────────────────────────────────────────────────────────────┘
```

## 四、转换逻辑详解

### 4.1 基本参数映射

| Claude 参数 | OpenAI 参数 | 说明 |
|-------------|-------------|------|
| `model` | `model` | 直接映射 |
| `max_tokens` | `max_tokens` | 指针类型，直接复制 |
| `temperature` | `temperature` | 指针类型，直接复制 |
| `top_p` | `top_p` | 指针类型，直接复制 |
| `top_k` | `top_k` | 指针类型，直接复制 |
| `stream` | `stream` | 指针类型，直接复制 |

**转换代码**:
```go
openAIRequest := dto.GeneralOpenAIRequest{
    Model:       claudeRequest.Model,
    Temperature: claudeRequest.Temperature,
}
if claudeRequest.MaxTokens != nil {
    openAIRequest.MaxTokens = lo.ToPtr(lo.FromPtr(claudeRequest.MaxTokens))
}
if claudeRequest.TopP != nil {
    openAIRequest.TopP = lo.ToPtr(lo.FromPtr(claudeRequest.TopP))
}
if claudeRequest.TopK != nil {
    openAIRequest.TopK = lo.ToPtr(lo.FromPtr(claudeRequest.TopK))
}
if claudeRequest.Stream != nil {
    openAIRequest.Stream = lo.ToPtr(lo.FromPtr(claudeRequest.Stream))
}
```

### 4.2 Stop Sequences 转换

**Claude 格式**: `stop_sequences` (数组)
**OpenAI 格式**: `stop` (字符串或数组)

```go
if len(claudeRequest.StopSequences) == 1 {
    openAIRequest.Stop = claudeRequest.StopSequences[0]  // 单个转为字符串
} else if len(claudeRequest.StopSequences) > 1 {
    openAIRequest.Stop = claudeRequest.StopSequences     // 多个保持数组
}
```

### 4.3 工具 (Tools) 转换

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

**转换代码**:
```go
tools, _ := common.Any2Type[[]dto.Tool](claudeRequest.Tools)
openAITools := make([]dto.ToolCallRequest, 0)
for _, claudeTool := range tools {
    openAITool := dto.ToolCallRequest{
        Type: "function",
        Function: dto.FunctionRequest{
            Name:        claudeTool.Name,
            Description: claudeTool.Description,
            Parameters:  claudeTool.InputSchema,
        },
    }
    openAITools = append(openAITools, openAITool)
}
openAIRequest.Tools = openAITools
```

### 4.4 System 消息转换

**Claude 格式**: `system` 是顶级字段
```json
{
  "system": "You are a helpful assistant.",
  "messages": [...]
}
```

**OpenAI 格式**: `system` 是 messages 数组中的一条消息
```json
{
  "messages": [
    {"role": "system", "content": "You are a helpful assistant."},
    ...
  ]
}
```

**转换代码**:
```go
if claudeRequest.System != nil {
    if claudeRequest.IsStringSystem() && claudeRequest.GetStringSystem() != "" {
        openAIMessage := dto.Message{
            Role: "system",
        }
        openAIMessage.SetStringContent(claudeRequest.GetStringSystem())
        openAIMessages = append(openAIMessages, openAIMessage)
    } else {
        // 处理数组格式的 system
        systems := claudeRequest.ParseSystem()
        if len(systems) > 0 {
            openAIMessage := dto.Message{
                Role: "system",
            }
            // ... 转换逻辑
            openAIMessages = append(openAIMessages, openAIMessage)
        }
    }
}
```

### 4.5 消息内容转换

#### 4.5.1 文本内容

| Claude | OpenAI |
|--------|--------|
| `{"type": "text", "text": "Hello"}` | `{"type": "text", "text": "Hello"}` |

**转换代码**:
```go
case "text", "input_text":
    message := dto.MediaContent{
        Type:         "text",
        Text:         mediaMsg.GetText(),
        CacheControl: mediaMsg.CacheControl,
    }
    mediaMessages = append(mediaMessages, message)
```

#### 4.5.2 图片内容

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

**OpenAI 格式**:
```json
{
  "type": "image_url",
  "image_url": {
    "url": "data:image/png;base64,iVBORw0KGgo..."
  }
}
```

**转换代码**:
```go
case "image":
    imageData := fmt.Sprintf("data:%s;base64,%s", mediaMsg.Source.MediaType, mediaMsg.Source.Data)
    mediaMessage := dto.MediaContent{
        Type:     "image_url",
        ImageUrl: &dto.MessageImageUrl{Url: imageData},
    }
    mediaMessages = append(mediaMessages, mediaMessage)
```

#### 4.5.3 工具调用 (tool_use)

**Claude 格式**:
```json
{
  "type": "tool_use",
  "id": "toolu_01A...",
  "name": "get_weather",
  "input": {"location": "SF"}
}
```

**OpenAI 格式**:
```json
{
  "tool_calls": [{
    "id": "toolu_01A...",
    "type": "function",
    "function": {
      "name": "get_weather",
      "arguments": "{\"location\": \"SF\"}"
    }
  }]
}
```

**转换代码**:
```go
case "tool_use":
    toolCall := dto.ToolCallRequest{
        ID:   mediaMsg.Id,
        Type: "function",
        Function: dto.FunctionRequest{
            Name:      mediaMsg.Name,
            Arguments: toJSONString(mediaMsg.Input),
        },
    }
    toolCalls = append(toolCalls, toolCall)
```

#### 4.5.4 工具结果 (tool_result)

**Claude 格式**:
```json
{
  "type": "tool_result",
  "tool_use_id": "toolu_01A...",
  "content": "The weather is sunny"
}
```

**OpenAI 格式**:
```json
{
  "role": "tool",
  "tool_call_id": "toolu_01A...",
  "content": "The weather is sunny"
}
```

**转换代码**:
```go
case "tool_result":
    toolName := mediaMsg.Name
    if toolName == "" {
        toolName = claudeRequest.SearchToolNameByToolCallId(mediaMsg.ToolUseId)
    }
    oaiToolMessage := dto.Message{
        Role:       "tool",
        Name:       &toolName,
        ToolCallId: mediaMsg.ToolUseId,
    }
    if mediaMsg.IsStringContent() {
        oaiToolMessage.SetStringContent(mediaMsg.GetStringContent())
    } else {
        mediaContents := mediaMsg.ParseMediaContent()
        encodeJson, _ := common.Marshal(mediaContents)
        oaiToolMessage.SetStringContent(string(encodeJson))
    }
    openAIMessages = append(openAIMessages, oaiToolMessage)
```

### 4.6 Thinking/Reasoning 模式转换

**Claude Thinking 配置**:
```json
{
  "thinking": {
    "type":