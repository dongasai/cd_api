<?php

namespace App\Services\Search\Contracts;

/**
 * 搜索结果结构体
 */
class SearchResult
{
    /**
     * 搜索查询
     */
    public string $query;

    /**
     * 结果数量
     */
    public int $count;

    /**
     * 总数量
     */
    public int $total;

    /**
     * 搜索结果列表
     *
     * @var array<SearchItem>
     */
    public array $items = [];

    /**
     * 使用的驱动名称
     */
    public string $driver;

    /**
     * 过滤条件
     */
    public array $filters = [];

    public function __construct(
        string $query,
        int $count,
        int $total,
        array $items,
        string $driver,
        array $filters = []
    ) {
        $this->query = $query;
        $this->count = $count;
        $this->total = $total;
        $this->items = $items;
        $this->driver = $driver;
        $this->filters = $filters;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'search_query' => $this->query,
            'count' => $this->count,
            'total' => $this->total,
            'driver' => $this->driver,
            'filters' => $this->filters,
            'results' => array_map(fn ($item) => $item->toArray(), $this->items),
        ];
    }

    /**
     * 空结果
     */
    public static function empty(string $query, string $driver, array $filters = []): self
    {
        return new self($query, 0, 0, [], $driver, $filters);
    }
}
