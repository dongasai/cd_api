<?php

namespace App\Admin\Repositories;

use Dcat\Admin\Grid;
use Dcat\Admin\Repositories\Repository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 数据库迁移仓库
 *
 * 合并文件系统和 migrations 表数据，提供迁移列表
 */
class MigrationRepository extends Repository
{
    /**
     * 主键字段名
     *
     * @var string
     */
    protected $keyName = 'name';

    /**
     * 获取迁移列表数据
     */
    public function get(Grid\Model $model)
    {
        // 获取所有迁移文件
        $migrationPath = database_path('migrations');
        $allFiles = glob($migrationPath.'/*.php') ?: [];
        $allFiles = array_map('basename', $allFiles);

        // 获取已执行迁移
        try {
            $ranMigrations = DB::table('migrations')
                ->select(['migration', 'batch'])
                ->get()
                ->keyBy('migration')
                ->toArray();
        } catch (\Exception) {
            $ranMigrations = [];
        }

        // 合并数据
        $data = [];
        foreach ($allFiles as $file) {
            $name = str_replace('.php', '', $file);
            $isRan = isset($ranMigrations[$name]);
            $batch = $isRan ? $ranMigrations[$name]->batch : null;

            $data[] = [
                'name' => $name,
                'status' => $isRan ? 'ran' : 'pending',
                'batch' => $batch,
                'file' => $file,
            ];
        }

        // 处理筛选
        $data = $this->applyFilter($data, $model);

        // 排序处理
        $sort = $model->getSort();
        // getSort 返回 [column, type, cast] 或 [null, null, null]
        $sortColumn = $sort[0] ?? null;
        $sortDirection = $sort[1] ?? 'asc';

        if ($sortColumn) {
            usort($data, function ($a, $b) use ($sortColumn, $sortDirection) {
                $valA = (string) ($a[$sortColumn] ?? '');
                $valB = (string) ($b[$sortColumn] ?? '');
                $cmp = strcmp($valA, $valB);

                return $sortDirection === 'asc' ? $cmp : -$cmp;
            });
        } else {
            // 默认排序：按文件名倒序（最新的在前）
            usort($data, function ($a, $b) {
                return strcmp($b['name'], $a['name']);
            });
        }

        // 分页
        $pageSize = $model->getPerPage();
        $currentPage = $model->getCurrentPage();
        $total = count($data);
        $items = array_slice($data, ($currentPage - 1) * $pageSize, $pageSize);

        return new LengthAwarePaginator($items, $total, $pageSize, $currentPage);
    }

    /**
     * 应用筛选条件
     */
    protected function applyFilter(array $data, Grid\Model $model): array
    {
        $filters = $model->filter()->getConditions();

        if (empty($filters)) {
            return $data;
        }

        foreach ($filters as $filter) {
            $column = $filter['column'] ?? null;
            $value = $filter['value'] ?? null;

            if ($column === 'status' && $value) {
                $data = array_filter($data, fn ($item) => $item['status'] === $value);
            }
        }

        return array_values($data);
    }
}
