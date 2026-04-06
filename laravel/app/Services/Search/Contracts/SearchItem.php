<?php

namespace App\Services\Search\Contracts;

/**
 * 搜索结果项
 */
class SearchItem
{
    /**
     * 结果标题
     */
    public string $title;

    /**
     * 结果链接
     */
    public string $url;

    /**
     * 结果摘要
     */
    public string $snippet;

    /**
     * 来源域名
     */
    public ?string $domain = null;

    /**
     * 发布时间
     */
    public ?string $publishedAt = null;

    /**
     * 结果位置
     */
    public int $position = 0;

    /**
     * 额外数据
     */
    public array $extra = [];

    public function __construct(
        string $title,
        string $url,
        string $snippet,
        ?string $domain = null,
        ?string $publishedAt = null,
        int $position = 0,
        array $extra = []
    ) {
        $this->title = $title;
        $this->url = $url;
        $this->snippet = $snippet;
        $this->domain = $domain;
        $this->publishedAt = $publishedAt;
        $this->position = $position;
        $this->extra = $extra;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'snippet' => $this->snippet,
            'domain' => $this->domain,
            'published_at' => $this->publishedAt,
            'position' => $this->position,
            ...$this->extra,
        ];
    }
}
