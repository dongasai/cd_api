<?php

namespace App\Services\Search\Driver;

use App\Services\Search\Contracts\SearchRequest;
use App\Services\Search\Contracts\SearchResult;

/**
 * Serper 搜索驱动
 *
 * 使用 Serper.dev Google 搜索 API
 * 文档: https://serper.dev/
 */
class SerperSearchDriver extends AbstractSearchDriver
{
    /**
     * API 端点
     */
    protected string $apiEndpoint = 'https://google.serper.dev/search';

    /**
     * 获取驱动名称
     */
    public function getName(): string
    {
        return 'serper';
    }

    /**
     * 执行搜索
     */
    public function search(SearchRequest $request): SearchResult
    {
        $apiKey = $this->getConfig('api_key');

        if (! $apiKey) {
            return SearchResult::empty($request->query, $this->getName(), [
                'error' => 'API key not configured',
            ]);
        }

        // 构建请求参数
        $params = $this->buildSearchParams($request);

        // 发送请求
        $response = $this->httpRequest(
            $this->apiEndpoint,
            $params,
            [
                'X-API-KEY' => $apiKey,
                'Content-Type' => 'application/json',
            ],
            'POST'
        );

        if (! $response) {
            return SearchResult::empty($request->query, $this->getName());
        }

        // 解析响应
        $items = $this->parseResponse($response);
        $total = count($items);

        return $this->buildResult($request, $items, $total);
    }

    /**
     * 构建搜索参数
     */
    protected function buildSearchParams(SearchRequest $request): array
    {
        $params = [
            'q' => $request->query,
            'num' => min($request->count, 100), // Serper 最多返回100条
        ];

        // 域名过滤
        if ($request->domainFilter) {
            $params['q'] .= ' site:'.$request->domainFilter;
        }

        // 时间过滤
        $recencyMap = [
            'oneDay' => 'd1',
            'oneWeek' => 'w1',
            'oneMonth' => 'm1',
            'oneYear' => 'y1',
        ];

        if (isset($recencyMap[$request->recencyFilter])) {
            $params['tbs'] = 'qdr:'.$recencyMap[$request->recencyFilter];
        }

        return $params;
    }

    /**
     * 解析 Serper 响应
     */
    protected function parseResponse(array $response): array
    {
        $items = [];

        // 解析 organic 结果
        $organic = $response['organic'] ?? [];
        foreach ($organic as $index => $result) {
            $items[] = [
                'title' => $result['title'] ?? '',
                'url' => $result['link'] ?? '',
                'snippet' => $result['snippet'] ?? '',
                'date' => $result['date'] ?? null,
                'position' => $index + 1,
            ];
        }

        // 解析 news 结果（如果有）
        $news = $response['news'] ?? [];
        foreach ($news as $index => $result) {
            $items[] = [
                'title' => $result['title'] ?? '',
                'url' => $result['link'] ?? '',
                'snippet' => $result['snippet'] ?? '',
                'date' => $result['date'] ?? null,
                'position' => count($items) + $index + 1,
            ];
        }

        return $items;
    }

    /**
     * 验证配置
     */
    public function validateConfig(): bool
    {
        $apiKey = $this->getConfig('api_key');

        return ! empty($apiKey);
    }

    /**
     * 获取驱动配置要求
     */
    public function getConfigRequirements(): array
    {
        return [
            'api_key' => 'Serper API 密钥（必填，从 https://serper.dev 获取）',
            'timeout' => '请求超时时间（秒，默认30）',
        ];
    }
}
