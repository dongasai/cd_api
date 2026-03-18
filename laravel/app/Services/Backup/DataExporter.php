<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;

/**
 * 数据导出器
 *
 * 负责导出表结构和数据，支持分批处理大表。
 */
class DataExporter
{
    /**
     * 导出表结构
     *
     * @param  string  $table  表名
     * @return string CREATE TABLE 语句
     */
    public function exportStructure(string $table): string
    {
        $sql = "-- Table: {$table}\n";
        $sql .= '-- Backup at: '.now()->format('Y-m-d H:i:s')."\n";
        $sql .= '-- Database: '.DB::getDatabaseName()."\n\n";

        // 添加 DROP TABLE 语句
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n\n";

        // 获取 CREATE TABLE 语句
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite 查询
            $result = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$table]);
            if (! empty($result)) {
                $createStatement = $result[0]->sql;
                $sql .= $createStatement.";\n\n";
            }
        } else {
            // MySQL 查询
            $createTable = DB::select("SHOW CREATE TABLE `{$table}`");
            $createStatement = $createTable[0]->{'Create Table'};
            $sql .= $createStatement.";\n\n";
        }

        return $sql;
    }

    /**
     * 导出表数据
     *
     * @param  string  $table  表名
     * @param  int  $chunkSize  分批大小
     * @param  callable|null  $progressCallback  进度回调函数
     * @return string INSERT 语句
     */
    public function exportData(string $table, int $chunkSize = 5000, ?callable $progressCallback = null): string
    {
        $sql = "-- Data: {$table}\n\n";

        // 获取总行数
        $totalRows = DB::table($table)->count();
        $sql .= "-- Total rows: {$totalRows}\n\n";

        if ($totalRows === 0) {
            $sql .= "-- No data in this table\n\n";

            return $sql;
        }

        // 获取列名
        $columns = $this->getTableColumns($table);
        $columnList = '`'.implode('`, `', $columns).'`';

        // 分批导出数据
        $processed = 0;
        $batchNumber = 0;

        DB::table($table)
            ->orderBy('id')
            ->chunk($chunkSize, function ($rows) use ($table, $columns, $columnList, &$sql, &$processed, $totalRows, &$batchNumber, $progressCallback) {
                $batchNumber++;
                $sql .= "-- Batch {$batchNumber}\n";

                foreach ($rows as $row) {
                    $values = $this->formatRowValues($row, $columns);
                    $sql .= "INSERT INTO `{$table}` ({$columnList}) VALUES ({$values});\n";
                    $processed++;
                }

                $sql .= "\n";

                // 调用进度回调
                if ($progressCallback) {
                    $progressCallback($processed, $totalRows);
                }
            });

        $sql .= "-- End of data for {$table}\n\n";

        return $sql;
    }

    /**
     * 获取表列名
     *
     * @param  string  $table  表名
     * @return array 列名数组
     */
    public function getTableColumns(string $table): array
    {
        $columns = DB::select("SHOW COLUMNS FROM `{$table}`");

        return array_map(function ($column) {
            return $column->Field;
        }, $columns);
    }

    /**
     * 格式化行值为 SQL 字符串
     *
     * @param  object  $row  数据行
     * @param  array  $columns  列名数组
     * @return string 格式化后的值字符串
     */
    private function formatRowValues(object $row, array $columns): string
    {
        $values = [];

        foreach ($columns as $column) {
            $value = $row->$column ?? null;
            $values[] = $this->escapeValue($value);
        }

        return implode(', ', $values);
    }

    /**
     * 转义值为 SQL 安全字符串
     *
     * @param  mixed  $value  值
     * @return string 转义后的值
     */
    private function escapeValue($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        // 字符串类型，进行转义
        return "'".addslashes($value)."'";
    }

    /**
     * 导出完整的表备份（结构 + 数据）
     *
     * @param  string  $table  表名
     * @param  bool  $withStructure  是否包含结构
     * @param  int  $chunkSize  分批大小
     * @param  callable|null  $progressCallback  进度回调函数
     * @return string SQL 备份内容
     */
    public function exportTable(string $table, bool $withStructure = true, int $chunkSize = 5000, ?callable $progressCallback = null): string
    {
        $sql = "-- ============================================\n";
        $sql .= "-- Backup for table: {$table}\n";
        $sql .= '-- Generated at: '.now()->format('Y-m-d H:i:s')."\n";
        $sql .= "-- ============================================\n\n";

        // 导出表结构
        if ($withStructure) {
            $sql .= $this->exportStructure($table);
        }

        // 导出数据
        $sql .= $this->exportData($table, $chunkSize, $progressCallback);

        return $sql;
    }

    /**
     * 获取表统计信息
     *
     * @param  string  $table  表名
     * @return array 统计信息
     */
    public function getTableStats(string $table): array
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite 查询
            $count = DB::table($table)->count();

            return [
                'row_count' => $count,
                'data_length' => 0,
                'index_length' => 0,
                'data_free' => 0,
            ];
        } else {
            // MySQL 查询
            $tableInfo = DB::select('
                SELECT
                    TABLE_ROWS,
                    DATA_LENGTH,
                    INDEX_LENGTH,
                    DATA_FREE
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
            ', [DB::getDatabaseName(), $table]);

            $info = $tableInfo[0] ?? null;

            return [
                'row_count' => $info->TABLE_ROWS ?? 0,
                'data_length' => $info->DATA_LENGTH ?? 0,
                'index_length' => $info->INDEX_LENGTH ?? 0,
                'data_free' => $info->DATA_FREE ?? 0,
            ];
        }
    }
}
