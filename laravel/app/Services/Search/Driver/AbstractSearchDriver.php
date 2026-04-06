<?php

namespace App\Services\Search\Driver;

use App\Services\Search\Contracts\SearchItem;
use App\Services\Search\Contracts\SearchRequest;
use App\Services\Search\Contracts\SearchResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 抽象搜索驱动基类
 */
abstract class AbstractSearchDriver implements SearchDriverInterface
{
    /**
     * 驱动配置
     */
    protected array $config = [];

    /**
     * HTTP 请求超时时间（秒）
     */
    protected int $timeout = 30;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->timeout = $config['timeout'] ?? 30;
    }

    /**
     * 获取配置项
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * 设置配置
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * 发送 HTTP 请求
     */
    protected function httpRequest(string $url, array $params = [], array $headers = [], string $method = 'GET'): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($headers)
                ->{$method}($url, $params);

            if (! $response->successful()) {
                Log::warning('SearchDriver HTTP request failed', [
                    'driver' => $this->getName(),
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('SearchDriver HTTP request exception', [
                'driver' => $this->getName(),
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 截取摘要文本
     */
    protected function truncateSnippet(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength).'...';
    }

    /**
     * 解析域名
     */
    protected function parseDomain(string $url): ?string
    {
        $parsed = parse_url($url);
        if ($parsed && isset($parsed['host'])) {
            return $parsed['host'];
        }

        return null;
    }

    /**
     * 应用时间过滤
     */
    protected function applyRecencyFilter(array $items, ?int $days): array
    {
        if ($days === null) {
            return $items;
        }

        $now = now();

        return array_filter($items, function ($item) use ($days, $now) {
            $publishedAt = $item['publishedAt'] ?? $item['date'] ?? null;
            if (! $publishedAt) {
                return true; // 无时间信息时保留
            }

            try {
                $date = \Carbon\Carbon::parse($publishedAt);

                return $date->diffInDays($now) <= $days;
            } catch (\Exception $e) {
                return true;
            }
        });
    }

    /**
     * 应用域名过滤
     */
    protected function applyDomainFilter(array $items, ?string $domain): array
    {
        if (! $domain) {
            return $items;
        }

        return array_filter($items, function ($item) use ($domain) {
            $itemDomain = $item['domain'] ?? $this->parseDomain($item['url'] ?? '');

            return $itemDomain === $domain;
        });
    }

    /**
     * 构建搜索结果
     */
    protected function buildResult(SearchRequest $request, array $rawItems, int $total): SearchResult
    {
        // 应用过滤
        $filteredItems = $this->applyDomainFilter($rawItems, $request->domainFilter);
        $filteredItems = $this->applyRecencyFilter($filteredItems, $request->getRecencyDays());

        // 截取摘要
        $summaryLength = $request->getSummaryLength();
        $items = [];
        $position = 1;

        foreach ($filteredItems as $rawItem) {
            if ($position > $request->count) {
                break;
            }

            $items[] = new SearchItem(
                $rawItem['title'] ?? '',
                $rawItem['url'] ?? '',
                $this->truncateSnippet($rawItem['snippet'] ?? $rawItem['content'] ?? '', $summaryLength),
                $rawItem['domain'] ?? $this->parseDomain($rawItem['url'] ?? ''),
                $rawItem['publishedAt'] ?? $rawItem['date'] ?? null,
                $position,
                $rawItem['extra'] ?? []
            );
            $position++;
        }

        return new SearchResult(
            $request->query,
            count($items),
            $total,
            $items,
            $this->getName(),
            [
                'domain' => $request->domainFilter,
                'recency' => $request->recencyFilter,
                'content_size' => $request->contentSize,
            ]
        );
    }

    /**
     * 默认配置要求
     */
    public function getConfigRequirements(): array
    {
        return [
            'api_key' => 'API密钥（可选，部分驱动需要）',
            'timeout' => '请求超时时间（秒，默认30）',
        ];
    }
}
