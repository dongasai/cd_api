<?php

namespace App\Services\Search\Contracts;

/**
 * 搜索请求结构体
 */
class SearchRequest
{
    /**
     * 搜索查询内容
     */
    public string $query;

    /**
     * 返回结果数量
     */
    public int $count = 10;

    /**
     * 域名白名单过滤
     */
    public ?string $domainFilter = null;

    /**
     * 时间范围过滤
     * oneDay, oneWeek, oneMonth, oneYear, noLimit
     */
    public string $recencyFilter = 'noLimit';

    /**
     * 摘要长度
     * medium: 400-600字, high: 2500字
     */
    public string $contentSize = 'medium';

    public function __construct(
        string $query,
        int $count = 10,
        ?string $domainFilter = null,
        string $recencyFilter = 'noLimit',
        string $contentSize = 'medium'
    ) {
        $this->query = $query;
        $this->count = $count;
        $this->domainFilter = $domainFilter;
        $this->recencyFilter = $recencyFilter;
        $this->contentSize = $contentSize;
    }

    /**
     * 从数组创建请求
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['search_query'] ?? $data['query'] ?? '',
            (int) ($data['count'] ?? 10),
            $data['search_domain_filter'] ?? $data['domain_filter'] ?? null,
            $data['search_recency_filter'] ?? $data['recency_filter'] ?? 'noLimit',
            $data['content_size'] ?? 'medium'
        );
    }

    /**
     * 获取时间范围天数
     */
    public function getRecencyDays(): ?int
    {
        $filters = [
            'oneDay' => 1,
            'oneWeek' => 7,
            'oneMonth' => 30,
            'oneYear' => 365,
            'noLimit' => null,
        ];

        return $filters[$this->recencyFilter] ?? null;
    }

    /**
     * 获取摘要长度
     */
    public function getSummaryLength(): int
    {
        $sizes = [
            'medium' => 400,
            'high' => 2500,
        ];

        return $sizes[$this->contentSize] ?? 400;
    }
}
