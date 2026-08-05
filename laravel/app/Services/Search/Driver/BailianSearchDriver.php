<?php

namespace App\Services\Search\Driver;

use App\Services\Search\Contracts\SearchRequest;
use App\Services\Search\Contracts\SearchResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 百炼搜索驱动
 *
 * 通过阿里云百炼 DashScope API 执行网络搜索
 * API 文档: https://help.aliyun.com/document_detail/2774953.html
 */
class BailianSearchDriver extends AbstractSearchDriver
{
    /**
     * DashScope API 端点
     */
    protected const API_URL = 'https://dashscope.aliyuncs.com/compatible-mode/v1/services/aigc/text-generation/generation';

    public function __construct(array $config = [])
    {
        parent::__construct($config);
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
        $apiKey = $this->getConfig('api_key');
        $baseUrl = $this->getConfig('base_url', 'https://dashscope.aliyuncs.com');

        if (empty($apiKey)) {
            Log::warning('BailianSearchDriver: API Key 未配置');

            return SearchResult::empty($request->query, $this->getName(), [
                'error' => 'Bailian API key not configured',
            ]);
        }

        try {
            $arguments = $this->buildToolArguments($request);

            // 调用百炼 Web Search API
            $url = rtrim($baseUrl, '/').'/compatible-mode/v1/services/aigc/text-generation/generation';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->getConfig('timeout', 30))
                ->post($url, $this->buildRequestBody($arguments));

            if (! $response->successful()) {
                Log::error('BailianSearchDriver: API 请求失败', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return SearchResult::empty($request->query, $this->getName(), [
                    'error' => "API request failed: {$response->status()}",
                ]);
            }

            $result = $response->json();
            $items = $this->parseApiResponse($result);
            $total = count($items);

            Log::info('BailianSearchDriver: 搜索完成', [
                'query' => $request->query,
                'total' => $total,
            ]);

            return $this->buildResult($request, $items, $total);
        } catch (ConnectionException $e) {
            Log::error('BailianSearchDriver: 连接超时', [
                'query' => $request->query,
                'error' => $e->getMessage(),
            ]);

            return SearchResult::empty($request->query, $this->getName(), [
                'error' => 'Connection timeout',
            ]);
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
     * 构建搜索参数
     */
    protected function buildToolArguments(SearchRequest $request): array
    {
        $arguments = [
            'query' => $request->query,
            'count' => min($request->count, 50),
        ];

        if ($request->domainFilter) {
            $arguments['search_domain_filter'] = $request->domainFilter;
        }

        if ($request->recencyFilter !== 'noLimit') {
            $arguments['search_recency_filter'] = $request->recencyFilter;
        }

        if ($request->contentSize) {
            $arguments['content_size'] = $request->contentSize;
        }

        return $arguments;
    }

    /**
     * 构建 DashScope API 请求体
     */
    protected function buildRequestBody(array $searchArgs): array
    {
        return [
            'model' => $this->getConfig('model', 'qwen-plus'),
            'input' => [
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $searchArgs['query'],
                    ],
                ],
            ],
            'parameters' => [
                'result_format' => 'message',
                'enable_search' => true,
                'search_info' => [
                    'search_args' => $searchArgs,
                ],
            ],
        ];
    }

    /**
     * 解析 DashScope API 响应
     */
    protected function parseApiResponse(array $result): array
    {
        $items = [];

        // DashScope 响应格式
        $output = $result['output'] ?? [];
        $choices = $output['choices'] ?? [];

        foreach ($choices as $choice) {
            $message = $choice['message'] ?? [];

            // 尝试从搜索信息中提取结果
            $searchInfo = $choice['search_info'] ?? $result['search_info'] ?? [];
            $pages = $searchInfo['search_results'] ?? [];

            foreach ($pages as $index => $data) {
                $items[] = $this->parseSearchItem($data, $index + 1);
            }
        }

        // 如果没有结构化搜索结果，尝试从文本内容解析
        if (empty($items)) {
            $text = $output['text'] ?? $output['choices'][0]['message']['content'][0]['text'] ?? '';
            if ($text) {
                $parsed = json_decode($text, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                    $pages = $parsed['pages'] ?? $parsed['results'] ?? $parsed;
                    if (is_array($pages)) {
                        foreach ($pages as $index => $data) {
                            $items[] = $this->parseSearchItem($data, $index + 1);
                        }
                    }
                } else {
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
     * 验证配置
     */
    public function validateConfig(): bool
    {
        return ! empty($this->getConfig('api_key'));
    }

    /**
     * 获取驱动配置要求
     */
    public function getConfigRequirements(): array
    {
        return [
            'api_key' => '百炼 API Key（必填）',
            'base_url' => 'API 基础 URL（可选，默认 https://dashscope.aliyuncs.com）',
            'model' => '搜索模型（可选，默认 qwen-plus）',
            'timeout' => '请求超时时间（秒，默认30）',
        ];
    }
}
