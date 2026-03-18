<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 表名解析服务
 *
 * 负责解析配置中的表组，支持通配符和正则表达式匹配表名。
 */
class TableResolver
{
    /**
     * 解析表组配置
     *
     * @param  string  $group  表组名称
     * @return array 解析后的表名数组
     *
     * @throws \InvalidArgumentException 当表组不存在时抛出异常
     */
    public function resolveGroup(string $group): array
    {
        $groups = config('backup.groups', []);

        if (! isset($groups[$group])) {
            throw new \InvalidArgumentException("表组 [{$group}] 不存在");
        }

        $config = $groups[$group];
        $tables = $config['tables'] ?? [];

        return $this->resolveTables($tables);
    }

    /**
     * 解析表名列表
     *
     * @param  array  $patterns  表名模式数组
     * @return array 解析后的表名数组
     */
    public function resolveTables(array $patterns): array
    {
        $resolved = [];
        $allTables = $this->getAllTables();

        foreach ($patterns as $pattern) {
            $matched = $this->resolvePattern($pattern, $allTables);
            $resolved = array_merge($resolved, $matched);
        }

        // 去重并过滤排除表
        $resolved = array_unique($resolved);
        $resolved = $this->filterExcludedTables($resolved);

        return array_values($resolved);
    }

    /**
     * 解析单个表名模式
     *
     * @param  string  $pattern  表名模式
     * @param  array|null  $allTables  所有表名数组（可选，用于性能优化）
     * @return array 匹配的表名数组
     */
    public function resolvePattern(string $pattern, ?array $allTables = null): array
    {
        $allTables = $allTables ?? $this->getAllTables();

        // 精确匹配
        if (in_array($pattern, $allTables)) {
            return [$pattern];
        }

        // 正则表达式匹配（以 / 开头）
        if (Str::startsWith($pattern, '/')) {
            return $this->matchByRegex($pattern, $allTables);
        }

        // 通配符匹配（包含 * 或 ?）
        if (Str::contains($pattern, ['*', '?'])) {
            return $this->matchByWildcard($pattern, $allTables);
        }

        // 未找到匹配
        return [];
    }

    /**
     * 验证表是否存在
     *
     * @param  array  $tables  表名数组
     * @return array 有效的表名数组
     */
    public function validateTables(array $tables): array
    {
        $allTables = $this->getAllTables();

        return array_filter($tables, function ($table) use ($allTables) {
            return in_array($table, $allTables);
        });
    }

    /**
     * 获取所有表名
     *
     * @return array 表名数组
     */
    public function getAllTables(): array
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite 查询
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

            return array_map(function ($table) {
                return $table->name;
            }, $tables);
        } else {
            // MySQL 查询
            $tables = DB::select('SHOW TABLES');
            $key = 'Tables_in_'.DB::getDatabaseName();

            return array_map(function ($table) use ($key) {
                return $table->$key;
            }, $tables);
        }
    }

    /**
     * 通配符匹配
     *
     * @param  string  $pattern  通配符模式（如 'admin_*'）
     * @param  array  $allTables  所有表名数组
     * @return array 匹配的表名数组
     */
    private function matchByWildcard(string $pattern, array $allTables): array
    {
        // 将通配符替换为占位符，避免被 preg_quote 转义
        $placeholder1 = uniqid('WILDCARD_STAR_');
        $placeholder2 = uniqid('WILDCARD_QUESTION_');

        $temp = str_replace(['*', '?'], [$placeholder1, $placeholder2], $pattern);

        // 对其他特殊字符进行转义
        $regex = preg_quote($temp, '/');

        // 将占位符替换为正则通配符
        $regex = str_replace([$placeholder1, $placeholder2], ['.*', '.'], $regex);

        return preg_grep('/^'.$regex.'$/i', $allTables);
    }

    /**
     * 正则表达式匹配
     *
     * @param  string  $pattern  正则表达式模式
     * @param  array  $allTables  所有表名数组
     * @return array 匹配的表名数组
     */
    private function matchByRegex(string $pattern, array $allTables): array
    {
        // 移除前后的 /
        $regex = trim($pattern, '/');

        // 验证正则表达式有效性
        if (@preg_match('/'.$regex.'/', '') === false) {
            throw new \InvalidArgumentException("无效的正则表达式: {$pattern}");
        }

        return preg_grep('/'.$regex.'/', $allTables);
    }

    /**
     * 过滤排除的表
     *
     * @param  array  $tables  表名数组
     * @return array 过滤后的表名数组
     */
    private function filterExcludedTables(array $tables): array
    {
        $excluded = config('backup.exclude_tables', []);

        return array_diff($tables, $excluded);
    }
}
