<?php

namespace App\Services\Search\Driver;

use App\Services\Search\Contracts\SearchRequest;
use App\Services\Search\Contracts\SearchResult;

/**
 * Mock 搜索驱动
 *
 * 用于测试和演示，返回模拟数据
 */
class MockSearchDriver extends AbstractSearchDriver
{
    /**
     * 模拟搜索数据
     */
    protected array $mockData = [
        [
            'title' => 'API 代理服务配置指南',
            'url' => 'https://docs.cdapi.local/docs/api-proxy-guide',
            'snippet' => 'CdApi 是一个 AI 大模型 API 代理工具，支持 OpenAI、Anthropic 等多种协议转换。可以配置多个上游渠道，实现负载均衡和故障转移。',
            'date' => '2026-03-01',
        ],
        [
            'title' => '渠道管理说明',
            'url' => 'https://docs.cdapi.local/docs/channel-management',
            'snippet' => '渠道是上游 API 提供商的配置，包括 API Key、Base URL、模型映射等信息。支持优先级配置和健康检查。',
            'date' => '2026-03-15',
        ],
        [
            'title' => '模型别名功能',
            'url' => 'https://docs.cdapi.local/docs/model-alias',
            'snippet' => '支持为模型设置别名，客户端可使用统一的模型名称访问不同的上游模型。例如将 gpt-4 映射到 claude-3-opus。',
            'date' => '2026-04-01',
        ],
        [
            'title' => '速率限制配置',
            'url' => 'https://docs.cdapi.local/docs/rate-limit',
            'snippet' => '支持配置 API Key 级别的速率限制，防止滥用和超支。可设置每分钟、每小时、每天的请求限制。',
            'date' => '2026-04-03',
        ],
        [
            'title' => '认证授权设计',
            'url' => 'https://docs.cdapi.local/docs/authentication',
            'snippet' => '系统支持 API Key 认证，可通过 Authorization Bearer 或 X-API-Key 头传递。支持 Key 级别的权限控制。',
            'date' => '2026-02-20',
        ],
        [
            'title' => 'MCP 服务介绍',
            'url' => 'https://docs.cdapi.local/docs/mcp',
            'snippet' => 'CdApi 提供 MCP Server 功能，外部客户端可通过 MCP 协议调用工具获取服务器信息。支持搜索和网页解析等工具。',
            'date' => '2026-04-05',
        ],
        [
            'title' => '最新天气预报',
            'url' => 'https://news.example.com/news/weather',
            'snippet' => '今日天气晴朗，气温 18-25℃，适合户外活动。未来一周预计维持晴好天气。',
            'date' => '2026-04-06',
        ],
        [
            'title' => 'AI 行业动态',
            'url' => 'https://news.example.com/news/ai-industry',
            'snippet' => 'OpenAI 发布最新模型，性能大幅提升。Anthropic 推出新版本 Claude，支持更长上下文。',
            'date' => '2026-04-04',
        ],
        [
            'title' => '百科知识：人工智能',
            'url' => 'https://wiki.example.com/wiki/ai',
            'snippet' => '人工智能是计算机科学的一个分支，致力于创建能够模拟人类智能行为的系统。包括机器学习、深度学习等子领域。',
            'date' => '2025-12-01',
        ],
        [
            'title' => '百科知识：API 接口',
            'url' => 'https://wiki.example.com/wiki/api',
            'snippet' => 'API（应用程序编程接口）是软件系统之间通信的桥梁。RESTful API 是最常见的 Web API 设计风格。',
            'date' => '2025-11-15',
        ],
    ];

    /**
     * 获取驱动名称
     */
    public function getName(): string
    {
        return 'mock';
    }

    /**
     * 执行搜索
     */
    public function search(SearchRequest $request): SearchResult
    {
        $queryLower = mb_strtolower($request->query);

        // 文本匹配
        $matchedItems = array_filter($this->mockData, function ($item) use ($queryLower) {
            return str_contains(mb_strtolower($item['title']), $queryLower)
                || str_contains(mb_strtolower($item['snippet']), $queryLower);
        });

        return $this->buildResult($request, $matchedItems, count($matchedItems));
    }

    /**
     * 验证配置
     */
    public function validateConfig(): bool
    {
        return true; // Mock 驱动无需配置
    }

    /**
     * 获取驱动配置要求
     */
    public function getConfigRequirements(): array
    {
        return []; // Mock 驱动无需配置
    }
}
