<?php

namespace App\Mcp\Tools;

use App\Models\SearchLog;
use App\Services\Search\Contracts\SearchRequest;
use App\Services\Search\SearchDriverManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Request as HttpRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

/**
 * 搜索工具
 *
 * 提供搜索功能，支持多种搜索引擎驱动
 */
#[Description('搜索可用于查询百科知识、时事新闻、天气等信息')]
class SearchTool extends Tool
{
    /**
     * 搜索驱动管理器
     */
    protected SearchDriverManager $driverManager;

    public function __construct()
    {
        $this->driverManager = app(SearchDriverManager::class);
    }

    /**
     * 处理工具请求
     */
    public function handle(Request $request): Response
    {
        $startTime = microtime(true);

        // 解析参数
        $searchRequest = $this->parseRequest($request);

        // 参数校验
        if (empty($searchRequest->query)) {
            return Response::error('search_query 不能为空');
        }

        if (mb_strlen($searchRequest->query) > 70) {
            return Response::error('search_query 建议不超过 70 个字符');
        }

        if ($searchRequest->count < 1 || $searchRequest->count > 50) {
            return Response::error('count 范围必须在 1-50 之间');
        }

        // 获取驱动（支持指定驱动）
        $driverName = $request->get('driver', $this->driverManager->getDefaultDriver());
        if (! $this->driverManager->hasDriver($driverName)) {
            return Response::error('不支持的驱动: '.$driverName);
        }

        // 获取驱动模型ID
        $driverModel = $this->driverManager->getDriverModel($driverName);
        $driverId = $driverModel?->id;

        try {
            // 执行搜索
            $driver = $this->driverManager->driver($driverName);
            $result = $driver->search($searchRequest);

            // 计算响应时间
            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            // 标记驱动使用
            if ($driverModel) {
                $driverModel->markUsed();
            }

            // 记录搜索日志
            $this->logSearch(
                $searchRequest,
                $driverName,
                $driverId,
                $result,
                $responseTimeMs,
                true
            );

            return Response::json($result->toArray());
        } catch (\Exception $e) {
            // 计算响应时间
            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            // 标记驱动错误
            if ($driverModel) {
                $driverModel->markError($e->getMessage());
            }

            // 记录失败日志
            $this->logSearch(
                $searchRequest,
                $driverName,
                $driverId,
                null,
                $responseTimeMs,
                false,
                $e->getMessage()
            );

            return Response::error('搜索失败: '.$e->getMessage());
        }
    }

    /**
     * 解析请求参数
     */
    protected function parseRequest(Request $request): SearchRequest
    {
        return SearchRequest::fromArray([
            'search_query' => $request->get('search_query', $request->get('query', '')),
            'count' => $request->get('count', 10),
            'search_domain_filter' => $request->get('search_domain_filter', null),
            'search_recency_filter' => $request->get('search_recency_filter', 'noLimit'),
            'content_size' => $request->get('content_size', 'medium'),
        ]);
    }

    /**
     * 记录搜索日志
     */
    protected function logSearch(
        SearchRequest $request,
        string $driver,
        ?int $driverId,
        $result,
        int $responseTimeMs,
        bool $success,
        ?string $errorMessage = null
    ): void {
        try {
            if ($success && $result) {
                // 提取前3条结果摘要
                $resultsSummary = null;
                if (! empty($result->items)) {
                    $resultsSummary = array_map(
                        fn ($item) => [
                            'title' => $item->title,
                            'url' => $item->url,
                        ],
                        array_slice($result->items, 0, 3)
                    );
                }

                SearchLog::recordSuccess(
                    $request->query,
                    $driver,
                    $driverId,
                    $result->count,
                    $result->total,
                    $responseTimeMs,
                    $result->filters,
                    $resultsSummary,
                    HttpRequest::ip(),
                    $this->getApiKey(),
                    $this->getMcpClient()
                );
            } else {
                SearchLog::recordFailure(
                    $request->query,
                    $driver,
                    $driverId,
                    $errorMessage ?? 'Unknown error',
                    $responseTimeMs,
                    [
                        'domain' => $request->domainFilter,
                        'recency' => $request->recencyFilter,
                        'content_size' => $request->contentSize,
                    ],
                    HttpRequest::ip(),
                    $this->getApiKey(),
                    $this->getMcpClient()
                );
            }
        } catch (\Exception $e) {
            // 日志记录失败不影响搜索结果
        }
    }

    /**
     * 获取 API Key ID
     */
    protected function getApiKey(): ?string
    {
        // 从请求中获取 API Key
        $apiKey = HttpRequest::bearerToken() ?? HttpRequest->header('X-API-Key');

        return $apiKey;
    }

    /**
     * 获取 MCP 客户端 ID
     */
    protected function getMcpClient(): ?string
    {
        // 从请求属性获取 MCP 客户端标识
        return HttpRequest::attribute('mcp_client_slug');
    }

    /**
     * 定义工具输入参数 Schema
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search_query' => $schema->string()
                ->description('需要进行搜索的内容，建议搜索 query 不超过 70 个字符')
                ->required(),
            'count' => $schema->integer()
                ->description('返回结果的条数，可填范围：1-50，默认为 10')
                ->default(10)
                ->min(1)
                ->max(50),
            'search_domain_filter' => $schema->string()
                ->description('用于限定搜索结果的范围，仅返回指定白名单域名的内容，如: www.example.com'),
            'search_recency_filter' => $schema->string()
                ->description('搜索指定时间范围内的网页。默认为 noLimit，可选值：oneDay（一天内）、oneWeek（一周内）、oneMonth（一个月内）、oneYear（一年内）、noLimit（不限）')
                ->default('noLimit')
                ->enum(['oneDay', 'oneWeek', 'oneMonth', 'oneYear', 'noLimit']),
            'content_size' => $schema->string()
                ->description('控制网页摘要的字数；默认值为 medium。medium：平衡模式，适用于大多数查询，400-600 字。high（高）：最大化上下文以提供更全面的回答，2500 字')
                ->default('medium')
                ->enum(['medium', 'high']),
            'driver' => $schema->string()
                ->description('指定搜索驱动。可选值：mock（模拟数据）、serper（Google搜索）、duckduckgo（DuckDuckGo）。默认使用系统配置的驱动')
                ->enum(['mock', 'serper', 'duckduckgo']),
        ];
    }
}