<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 数据库备份命令
 *
 * 在执行危险操作前自动备份关键表
 */
class DatabaseBackup extends Command
{
    protected $signature = 'cdapi:backup:table
        {--group=core : 备份组（core/all）}
        {--path= : 备份路径}
        {--format=json : 输出格式（json/sql）}';

    protected $description = '备份关键数据表';

    // 核心数据表（会清空的表）
    protected array $coreTables = [
        'channels',
        'api_keys',
        'channel_models',
        'model_lists',
        'coding_accounts',
        'admin_menu',
    ];

    // 全部数据表（包括日志）
    protected array $allTables = [
        'channels',
        'api_keys',
        'channel_models',
        'model_lists',
        'coding_accounts',
        'admin_menu',
        'request_logs',
        'audit_logs',
        'response_logs',
        'channel_request_logs',
    ];

    public function handle(): int
    {
        $tables = $this->option('group') === 'all' ? $this->allTables : $this->coreTables;
        $format = $this->option('format');
        $path = $this->option('path') ?? storage_path('backups/tables');

        // 确保目录存在
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $timestamp = date('Y-m-d_His');
        $backupDir = "{$path}/{$timestamp}";

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $this->info("开始备份到：{$backupDir}");

        foreach ($tables as $table) {
            $this->backupTable($table, $backupDir, $format);
        }

        $this->info("✅ 备份完成：{$backupDir}");

        // 生成备份索引
        $this->generateIndex($backupDir, $tables);

        return self::SUCCESS;
    }

    private function backupTable(string $table, string $path, string $format): void
    {
        $this->line("备份表：{$table}");

        $data = DB::table($table)->get();

        if ($format === 'json') {
            $file = "{$path}/{$table}.json";
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            // SQL 格式
            $file = "{$path}/{$table}.sql";
            $sql = $this->generateSql($table, $data);
            file_put_contents($file, $sql);
        }

        $count = $data->count();
        $this->line("  - {$count} 行数据");
    }

    private function generateSql(string $table, $data): string
    {
        $sql = '-- 备份时间: '.date('Y-m-d H:i:s')."\n";
        $sql .= "-- 表名: {$table}\n\n";

        foreach ($data as $row) {
            $values = collect($row)->map(function ($value) {
                return $value === null ? 'NULL' : DB::getPdo()->quote($value);
            })->implode(', ');

            $sql .= "INSERT INTO `{$table}` VALUES ({$values});\n";
        }

        return $sql;
    }

    private function generateIndex(string $path, array $tables): void
    {
        $index = [
            'timestamp' => date('Y-m-d H:i:s'),
            'tables' => [],
        ];

        foreach ($tables as $table) {
            $file = "{$path}/{$table}.json";
            if (file_exists($file)) {
                $data = json_decode(file_get_contents($file), true);
                $index['tables'][$table] = [
                    'count' => count($data),
                    'file' => basename($file),
                ];
            }
        }

        file_put_contents("{$path}/index.json", json_encode($index, JSON_PRETTY_PRINT));
    }
}
