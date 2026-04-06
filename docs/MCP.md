# MCP 功能

> 本项目提供的 MCP (Model Context Protocol) 服务

## 目录

- [MCP Server](#mcp-server) - 本项目作为 MCP Server 提供服务
- [MCP Client](#mcp-client) - 本项目连接外部 MCP Server

---

## MCP Server

本项目作为 MCP Server，供外部 AI 客户端调用。

### 服务地址

**内网访问**:
```
http://127.0.0.1:32126/mcp/cdapi
```

**公网访问**（需配置域名）:
```
https://your-domain.com/mcp/cdapi
```

> 替换 `your-domain.com` 为实际部署域名

## 认证方式

支持三种方式传递 API Key：

1. **Authorization Bearer Header**（推荐）
   ```
   Authorization: Bearer sk-xxx
   ```

2. **X-API-Key Header**
   ```
   X-API-Key: sk-xxx
   ```

3. **Query Parameter**
   ```
   ?api_key=sk-xxx
   ```

示例 Token: `sk-your-api-key-here`

## 可用工具

### 1. search

搜索功能，可用于查询百科知识、时事新闻、天气等信息。支持多种搜索引擎驱动。

**参数**:
| 名称 | 类型 | 必填 | 描述 |
|------|------|------|------|
| search_query | string | 是 | 搜索内容，建议不超过 70 个字符 |
| count | integer | 否 | 返回结果条数，默认 10，范围 1-50 |
| search_domain_filter | string | 否 | 白名单域名过滤，如: www.example.com |
| search_recency_filter | string | 否 | 时间范围过滤，默认 noLimit。可选: oneDay, oneWeek, oneMonth, oneYear, noLimit |
| content_size | string | 否 | 摘要字数控制，默认 medium。可选: medium(400-600字), high(2500字) |
| driver | string | 否 | 指定搜索驱动。可选: mock, serper, duckduckgo。默认使用系统配置 |

**搜索驱动**:
| 驱动名称 | 描述 | 配置要求 |
|----------|------|----------|
| mock | 模拟数据驱动，用于测试 | 无需配置 |
| serper | Google 搜索 API (serper.dev) | 需要 API Key |
| duckduckgo | DuckDuckGo 搜索 | 无需配置 |

**返回示例**:
```json
{
  "search_query": "API",
  "count": 2,
  "total": 10,
  "driver": "mock",
  "filters": {
    "domain": null,
    "recency": "noLimit",
    "content_size": "medium"
  },
  "results": [
    {
      "title": "API 代理服务配置指南",
      "url": "https://docs.cdapi.local/docs/api-proxy-guide",
      "snippet": "CdApi 是一个 AI 大模型 API 代理工具...",
      "domain": "docs.cdapi.local",
      "published_at": "2026-03-01",
      "position": 1
    }
  ]
}
```

### 2. web_parser

网页解析工具：通过无头浏览器获取动态网页内容，使用 AI 提取高质量 Markdown 文本。

**参数**:
| 名称 | 类型 | 必填 | 描述 |
|------|------|------|------|
| url | string | 是 | 网页地址，必须为有效的 HTTP/HTTPS URL |
| prompt | string | 否 | 处理提示词，指导 AI 如何处理网页内容 |

**返回示例**:
```json
{
  "format": "markdown",
  "content": "# 页面标题\n\n**来源**: https://example.com\n\n**解析时间**: 2026-04-06T12:50:59+08:00\n\n---\n\n提取的内容..."
}
```

**依赖配置**（需在系统设置中配置）:
| 配置项 | 描述 | 默认值 |
|--------|------|--------|
| mcp.webparser_base_url | AI API 地址 | `http://127.0.0.1/api/openai/v1` |
| mcp.webparser_api_key | AI API Key | 无（必填） |
| mcp.webparser_model | 使用的模型 | `gpt-4o` |
| mcp.webparser_temperature | 温度参数 | `0.3` |

## 代码结构

```
laravel/app/Mcp/
├── Servers/
│   └── CdApiServer.php      # MCP Server 定义
└── Tools/
    ├── SearchTool.php        # 搜索工具
    └── WebParserTool.php     # 网页解析工具

laravel/app/Services/Search/
├── Contracts/
│   ├── SearchRequest.php    # 搜索请求结构体
│   ├── SearchResult.php     # 搜索结果结构体
│   └── SearchItem.php       # 搜索结果项
├── Driver/
│   ├── SearchDriverInterface.php     # 驱动接口
│   ├── AbstractSearchDriver.php      # 抽象驱动基类
│   ├── MockSearchDriver.php          # Mock 驱动
│   ├── SerperSearchDriver.php        # Serper 驱动
│   └── DuckDuckGoSearchDriver.php    # DuckDuckGo 驱动
├── Exceptions/
│   └── SearchDriverException.php     # 驱动异常
└── SearchDriverManager.php           # 驱动管理器
```

**路由配置**: [laravel/routes/ai.php](laravel/routes/ai.php)
**搜索配置**: [laravel/config/search.php](laravel/config/search.php)

## 技术实现

- **框架**: `laravel/mcp` v0.6.4
- **认证**: [AuthenticateApiKey](laravel/app/Http/Middleware/AuthenticateApiKey.php) 中间件
- **无头浏览器**: Symfony Panther + Chrome
- **AI 处理**: OpenAI API 兼容接口
- **搜索驱动**: 可扩展驱动模式，支持 Mock、Serper、DuckDuckGo

## 搜索驱动配置

### 数据库配置（推荐）

驱动配置存储在 `search_drivers` 表中，支持在后台管理。

**数据库表结构**:
| 表名 | 说明 |
|------|------|
| search_drivers | 搜索驱动配置表 |
| search_logs | 搜索记录日志表 |

**字段说明**:

`search_drivers` 表：
| 字段 | 类型 | 说明 |
|------|------|------|
| name | string | 驱动名称 |
| slug | string | 驱动标识（唯一） |
| driver_class | string | 驱动类名 |
| config | json | 驱动配置 |
| timeout | int | 请求超时秒数 |
| priority | int | 优先级（数字越大优先级越高） |
| is_default | bool | 是否为默认驱动 |
| status | enum | 状态：active/inactive/error |

`search_logs` 表：
| 字段 | 类型 | 说明 |
|------|------|------|
| query | string | 搜索查询内容 |
| driver | string | 使用的驱动 |
| driver_id | int | 驱动ID |
| result_count | int | 返回结果数量 |
| total_count | int | 总匹配数量 |
| success | bool | 是否成功 |
| response_time_ms | int | 响应时间(毫秒) |
| filters | json | 过滤条件 |
| results | json | 搜索结果摘要(前3条) |

### 环境变量配置

在 `.env` 中配置（作为备用）：

```env
# 默认搜索驱动
SEARCH_DRIVER=mock

# Serper API 配置（可选）
SERPER_API_KEY=your-serper-api-key
```

或发布配置文件自定义：

```bash
php artisan vendor:publish --tag=search-config
```

## 使用示例

### Claude Code 配置

在 `.mcp.json` 中添加：

**内网配置**:
```json
{
  "servers": {
    "cdapi": {
      "url": "http://127.0.0.1:32126/mcp/cdapi",
      "headers": {
        "Authorization": "Bearer sk-your-api-key-here"
      }
    }
  }
}
```

**公网配置**:
```json
{
  "servers": {
    "cdapi": {
      "url": "https://your-domain.com/mcp/cdapi",
      "headers": {
        "Authorization": "Bearer sk-your-api-key-here"
      }
    }
  }
}
```

### 调用示例

```bash
# 内网调用
curl -X POST http://127.0.0.1:32126/mcp/cdapi \
  -H "Authorization: Bearer sk-your-api-key-here" \
  -H "Content-Type: application/json" \
  -d '{"tool": "search", "arguments": {"query": "API"}}'

# 公网调用
curl -X POST https://your-domain.com/mcp/cdapi \
  -H "Authorization: Bearer sk-your-api-key-here" \
  -H "Content-Type: application/json" \
  -d '{"tool": "search", "arguments": {"query": "API"}}'
```

---
**更新日期**: 2026-04-06

---

## MCP Client

本项目支持作为 MCP Client 连接外部 MCP Server，实现代理调用外部工具。

### 管理入口

Admin 后台：`/admin/mcp-clients`

### 支持的传输协议

| 协议 | 说明 | 适用场景 |
|------|------|----------|
| HTTP | HTTP+SSE 远程连接 | 连接远程 MCP Server |
| Stdio | 标准输入输出 | 连接本地 MCP Server |

### 创建客户端

通过 Admin 后台创建，配置以下信息：

**HTTP 协议配置：**
- 名称：客户端名称
- 标识符：唯一标识（用于 API 调用）
- URL：MCP Server 地址，如 `http://127.0.0.1:32126/mcp/cdapi`
- 请求头：如 `Authorization: Bearer sk-xxx`
- 超时时间：默认 30 秒

**Stdio 协议配置：**
- 名称：客户端名称
- 标识符：唯一标识
- 命令：执行的命令，如 `npx`、`php artisan`
- 参数：命令参数

### 代码调用示例

```php
use App\Models\McpClient;
use App\Services\McpClientService;

// 获取 MCP 客户端
$client = McpClient::where('slug', 'external-server')->first();

// 获取服务实例
$service = app(McpClientService::class);

// 测试连接
$result = $service->testConnection($client);

// 获取工具列表
$tools = $service->listTools($client);

// 调用工具
$result = $service->callTool($client, 'search', [
    'query' => 'API',
    'count' => 10,
]);
```

### API 接口

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | `/admin/mcp-clients/{id}/test` | 测试连接 |
| GET | `/admin/mcp-clients/{id}/tools` | 获取工具列表 |
| POST | `/admin/mcp-clients/{id}/call` | 调用工具 |

**调用工具示例：**

```bash
curl -X POST http://127.0.0.1:32126/admin/mcp-clients/1/call \
  -H "Content-Type: application/json" \
  -d '{
    "tool": "search",
    "arguments": {"query": "API", "count": 10}
  }'
```

### 技术实现

- **库**：`php-mcp/client` v1.0.1
- **模型**：`App\Models\McpClient`
- **服务**：`App\Services\McpClientService`
- **控制器**：`App\Admin\Controllers\McpClientController`

### 已知兼容性问题

> ⚠️ **HTTP 协议兼容性限制**

| 组件 | HTTP 传输模式 | 说明 |
|------|--------------|------|
| laravel/mcp (Server) | Streamable HTTP | POST 请求，符合 MCP 规范 2025-06-18 |
| php-mcp/client (Client) | HTTP with SSE | GET + SSE 流 |

**问题：** 两种传输模式不兼容，HTTP 方式无法连接 laravel/mcp Server。

**解决方案：**
1. 使用 **Stdio** 传输连接本地 MCP Server
2. 等待 php-mcp/client 支持 Streamable HTTP

**Stdio 连接配置：**
- 命令：`php`
- 参数：`["artisan", "mcp:start", "cdapi"]`