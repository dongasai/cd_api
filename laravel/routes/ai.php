<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Mcp\Servers\CdApiServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Routes
|--------------------------------------------------------------------------
|
| CdApi MCP Server 路由配置
| 提供 HTTP 协议的 MCP 服务，供外部客户端调用
|
*/

// CdApi MCP Server - Web 方式暴露，使用 API Key 认证
Mcp::web('/mcp/cdapi', CdApiServer::class)
    ->middleware([AuthenticateApiKey::class]);
