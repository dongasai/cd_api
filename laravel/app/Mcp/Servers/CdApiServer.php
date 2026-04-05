<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetServerTimeTool;
use App\Mcp\Tools\SearchTool;
use App\Mcp\Tools\WebParserTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * CdApi MCP Server
 *
 * 提供 CdApi 系统相关的 MCP 工具服务
 */
#[Name('CdApi MCP Server')]
#[Version('1.0.0')]
#[Instructions('CdApi MCP 服务，提供服务器信息查询等工具。可通过 API Key 认证访问。')]
class CdApiServer extends Server
{
    /**
     * 注册的工具列表
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        GetServerTimeTool::class,
        SearchTool::class,
        WebParserTool::class,
    ];

    /**
     * 注册的资源列表
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        // 后续可扩展
    ];

    /**
     * 注册的提示模板列表
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        // 后续可扩展
    ];
}
