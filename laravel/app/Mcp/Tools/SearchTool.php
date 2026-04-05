<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * 搜索工具
 *
 * 提供搜索功能，返回匹配结果
 */
#[Description('搜索功能，根据查询字符串返回匹配结果')]
class SearchTool extends Tool
{
    /**
     * 模拟搜索数据
     */
    protected array $mockData = [
        [
            'id' => 1,
            'title' => 'API 代理服务配置指南',
            'content' => 'CdApi 是一个 AI 大模型 API 代理工具，支持 OpenAI、Anthropic 等多种协议转换。',
            'category' => '文档',
            'url' => '/docs/api-proxy-guide',
        ],
        [
            'id' => 2,
            'title' => '渠道管理说明',
            'content' => '渠道是上游 API 提供商的配置，包括 API Key、Base URL、模型映射等信息。',
            'category' => '文档',
            'url' => '/docs/channel-management',
        ],
        [
            'id' => 3,
            'title' => '模型别名功能',
            'content' => '支持为模型设置别名，客户端可使用统一的模型名称访问不同的上游模型。',
            'category' => '功能',
            'url' => '/docs/model-alias',
        ],
        [
            'id' => 4,
            'title' => '速率限制配置',
            'content' => '支持配置 API Key 级别的速率限制，防止滥用和超支。',
            'category' => '功能',
            'url' => '/docs/rate-limit',
        ],
        [
            'id' => 5,
            'title' => '认证授权设计',
            'content' => '系统支持 API Key 认证，可通过 Authorization Bearer 或 X-API-Key 头传递。',
            'category' => '文档',
            'url' => '/docs/authentication',
        ],
        [
            'id' => 6,
            'title' => 'MCP 服务介绍',
            'content' => 'CdApi 提供 MCP Server 功能，外部客户端可通过 MCP 协议调用工具获取服务器信息。',
            'category' => '功能',
            'url' => '/docs/mcp',
        ],
    ];

    /**
     * 处理工具请求
     */
    public function handle(Request $request): Response
    {
        $query = $request->get('query', '');
        $count = (int) $request->get('count', 5);

        if (empty($query)) {
            return Response::error('查询字符串不能为空');
        }

        // 模拟搜索：根据查询字符串过滤
        $results = collect($this->mockData)
            ->filter(function ($item) use ($query) {
                $queryLower = mb_strtolower($query);

                return str_contains(mb_strtolower($item['title']), $queryLower)
                    || str_contains(mb_strtolower($item['content']), $queryLower)
                    || str_contains(mb_strtolower($item['category']), $queryLower);
            })
            ->take($count)
            ->values()
            ->toArray();

        return Response::json([
            'query' => $query,
            'count' => count($results),
            'total' => count($this->mockData),
            'results' => $results,
        ]);
    }

    /**
     * 定义工具输入参数 Schema
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('查询字符串，用于搜索标题、内容或分类')
                ->required(),
            'count' => $schema->integer()
                ->description('返回结果数量，默认 5')
                ->default(5)
                ->min(1)
                ->max(20),
        ];
    }
}
