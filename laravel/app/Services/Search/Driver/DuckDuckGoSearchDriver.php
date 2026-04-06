<?php

namespace App\Services\Search\Driver;

use App\Services\Search\Contracts\SearchRequest;
use App\Services\Search\Contracts\SearchResult;

/**
 * DuckDuckGo 搜索驱动
 *
 * 使用 DuckDuckGo Instant Answer API
 */
class DuckDuckGoSearchDriver extends AbstractSearchDriver
{
    /**
     * API 端点
     */
    protected string $apiEndpoint = 'https://api.duckduckgo.com/';

    /**
     * 获取驱动名称
     */
    public function getName(): string
    {
        return 'duckduckgo';
    }

    /**
     * 执行搜索
     */
    public function search(SearchRequest $request): SearchResult
    {
        // DuckDuckGo Instant Answer API 参数
        $params = [
            'q' => $request->query,
            'format' => 'json',
            'no_html' => 1,
            'skip_disambig' => 1,
        ];

        // 发送请求
        $response = $this->httpRequest(
            $this->apiEndpoint,
            $params,
            [],
            'GET'
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
     * 解析 DuckDuckGo 响应
     */
    protected function parseResponse(array $response): array
    {
        $items = [];

        // 解析 Abstract（摘要答案）
        if (! empty($response['Abstract']) && ! empty($response['AbstractURL'])) {
            $items[] = [
                'title' => $response['Heading'] ?? 'Abstract',
                'url' => $response['AbstractURL'],
                'snippet' => $response['Abstract'],
                'date' => null,
                'position' => 1,
            ];
        }

        // 解析 RelatedTopics（相关主题）
        $relatedTopics = $response['RelatedTopics'] ?? [];
        $position = count($items) + 1;

        foreach ($relatedTopics as $topic) {
            // 有些主题是嵌套的 Topics 数组
            if (isset($topic['Topics'])) {
                foreach ($topic['Topics'] as $subTopic) {
                    $items[] = $this->parseTopic($subTopic, $position++);
                }
            } else {
                $items[] = $this->parseTopic($topic, $position++);
            }
        }

        // 解析 Results（外部结果）
        $results = $response['Results'] ?? [];
        foreach ($results as $result) {
            if (! empty($result['FirstURL'])) {
                $items[] = [
                    'title' => $result['Text'] ?? '',
                    'url' => $result['FirstURL'],
                    'snippet' => '',
                    'date' => null,
                    'position' => $position++,
                ];
            }
        }

        return $items;
    }

    /**
     * 解析单个主题
     */
    protected function parseTopic(array $topic, int $position): array
    {
        $url = $topic['FirstURL'] ?? '';
        $text = $topic['Text'] ?? '';

        // 从文本中提取标题（通常在第一个链接标签之前）
        $title = $text;
        if (preg_match('/<a[^>]*>([^<]+)</a>/', $text, $matches)) {
            $title = $matches[1];
            // 去除 HTML 标签获取摘要
            $snippet = strip_tags($text);
        } else {
            $snippet = strip_tags($text);
        }

        return [
            'title' => $title,
            'url' => $url,
            'snippet' => $snippet,
            'date' => null,
            'position' => $position,
        ];
    }

    /**
     * 验证配置
     */
    public function validateConfig(): bool
    {
        return true; // DuckDuckGo API 无需密钥
    }

    /**
     * 获取驱动配置要求
     */
    public function getConfigRequirements(): array
    {
        return [
            'timeout' => '请求超时时间（秒，默认30）',
        ];
    }
}
