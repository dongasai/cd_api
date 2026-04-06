<?php

namespace App\Services\Search\Driver;

use App\Models\McpClient;
use App\Services\McpClientService;
use App\Services\Search\Contracts\SearchRequest;
use App\Services\Search\Contracts\SearchResult;
use Illuminate\Support\Facades\Log;

/**
 * 百炼搜索驱动
 *
 * 通过阿里云百炼 MCP 服务执行网络搜索
 * 工具：bailian_web_search
 */
class BailianSearchDriver extends AbstractSearchDriver
{
    /**
     * MCP 客户端服务
     */
    protected McpClientService $mcpService;

    /**
     * MCP 客户端实例
     */
    protected ?McpClient $mcpClient = null;

    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->mcpService = app(McpClientService::class);
    }

    /**
     * 获取驱动名称
     */
    public function getName(): string
    {
        return 'bailian';
    }

    /**
     * 执行搜索
     */
    public function search(SearchRequest $request): SearchResult
    {
        $client = $this->getMcpClient();

        if (! $client) {
            Log::warning('BailianSearchDriver: MCP 客户端未配置');

            return SearchResult::empty($request->query, $this->getName(), [
                'error' => 'MCP client not configured',
            ]);
        }

        try {
            // 构建 MCP 工具参数
            $arguments = $this->buildToolArguments($request);

            // 调用 bailian_web_search 工具
            $result = $this->mcpService->callTool($client, 'bailian_web_search', $arguments);

            // 解析结果
            $items = $this->parseToolResult($result);
            $total = count($items);

            Log::info('BailianSearchDriver: 搜索完成', [
                'query' => $request->query,
                'total' => $total,
            ]);

            return $this->buildResult($request, $items, $total);
        } catch (\Exception $e) {
            Log::error('BailianSearchDriver: 搜索失败', [
                'query' => $request->query,
                'error' => $e->getMessage(),
            ]);

            return SearchResult::empty($request->query, $this->getName(), [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 构建 MCP 工具参数
     */
    protected function buildToolArguments(SearchRequest $request): array
    {
        $arguments = [
            'query' => $request->query,
            'count' => min($request->count, 50), // 百炼限制最多50条
        ];

        // 域名过滤
        if ($request->domainFilter) {
            $arguments['search_domain_filter'] = $request->domainFilter;
        }

        // 时间过滤
        if ($request->recencyFilter !== 'noLimit') {
            $arguments['search_recency_filter'] = $request->recencyFilter;
        }

        // 内容长度
        if ($request->contentSize) {
            $arguments['content_size'] = $request->contentSize;
        }

        return $arguments;
    }

    /**
     * 解析 MCP 工具返回结果
     */
    protected function parseToolResult(array $result): array
    {
        $items = [];

        // 结果格式: { content: [{ type: 'text', text: '...' }], is_error: false }
        $content = $result['content'] ?? [];

        foreach ($content as $item) {
            if ($item['type'] === 'text') {
                $text = $item['text'] ?? '';

                // 解析 JSON 格式的搜索结果
                $parsed = json_decode($text, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                    // 百炼返回格式: { pages: [...], request_id: "...", status: 0 }
                    $pages = $parsed['pages'] ?? $parsed['results'] ?? $parsed;

                    foreach ($pages as $index => $data) {
                        $items[] = $this->parseSearchItem($data, $index + 1);
                    }
                } else {
                    // 非 JSON 格式，作为文本结果返回
                    $items[] = [
                        'title' => '搜索结果',
                        'url' => '',
                        'snippet' => $this->truncateSnippet($text, 400),
                        'domain' => null,
                        'date' => null,
                        'position' => 1,
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * 解析单个搜索结果项
     */
    protected function parseSearchItem(array $data, int $position): array
    {
        return [
            'title' => $data['title'] ?? $data['name'] ?? '',
            'url' => $data['url'] ?? $data['link'] ?? '',
            'snippet' => $data['snippet'] ?? $data['content'] ?? $data['summary'] ?? '',
            'domain' => $data['hostname'] ?? $data['domain'] ?? $this->parseDomain($data['url'] ?? ''),
            'date' => $data['date'] ?? $data['publishedAt'] ?? $data['published_at'] ?? null,
            'position' => $position,
            'extra' => [
                'hostlogo' => $data['hostlogo'] ?? null,
            ],
        ];
    }

    /**
     * 获取 MCP 客户端
     */
    protected function getMcpClient(): ?McpClient
    {
        if ($this->mcpClient) {
            return $this->mcpClient;
        }

        // 从配置获取客户端 ID 或 slug
        $clientId = $this->getConfig('mcp_client_id');
        $clientSlug = $this->getConfig('mcp_client_slug', 'BailianSearch');

        if ($clientId) {
            $this->mcpClient = McpClient::find($clientId);
        } else {
            $this->mcpClient = McpClient::where('slug', $clientSlug)
                ->where('status', McpClient::STATUS_ACTIVE)
                ->first();
        }

        return $this->mcpClient;
    }

    /**
     * 验证配置
     */
    public function validateConfig(): bool
    {
        $client = $this->getMcpClient();

        return $client !== null && $client->isActive();
    }

    /**
     * 获取驱动配置要求
     */
    public function getConfigRequirements(): array
    {
        return [
            'mcp_client_id' => 'MCP 客户端 ID（可选，优先使用）',
            'mcp_client_slug' => 'MCP 客户端标识（可选，默认 BailianSearch）',
            'timeout' => '请求超时时间（秒，默认30）',
        ];
    }
}
