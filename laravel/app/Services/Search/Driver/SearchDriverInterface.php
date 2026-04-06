<?php

namespace App\Services\Search\Driver;

use App\Services\Search\Contracts\SearchRequest;
use App\Services\Search\Contracts\SearchResult;

/**
 * 搜索驱动接口
 */
interface SearchDriverInterface
{
    /**
     * 获取驱动名称
     */
    public function getName(): string;

    /**
     * 执行搜索
     *
     * @param  SearchRequest  $request  搜索请求
     * @return SearchResult 搜索结果
     */
    public function search(SearchRequest $request): SearchResult;

    /**
     * 验证配置
     */
    public function validateConfig(): bool;

    /**
     * 获取驱动配置要求
     *
     * @return array 配置项说明
     */
    public function getConfigRequirements(): array;
}
