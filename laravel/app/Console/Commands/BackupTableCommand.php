<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupFileManager;
use App\Services\Backup\DataExporter;
use App\Services\Backup\TableResolver;
use Illuminate\Console\Command;

/**
 * 数据库表备份命令
 *
 * 使用方法：
 * php artisan backup:table --group=core
 * php artisan backup:table --tables=users,channels
 * php artisan backup:table --tables=admin_*
 */
class BackupTableCommand extends Command
{
    /**
     * 命令签名
     *
     * @var string
     */
    protected $signature = 'backup:table
        {--group= : 指定配置文件中的表组}
        {--tables= : 直接指定表名，逗号分隔或通配符}
        {--path= : 自定义备份路径}
        {--no-structure : 不包含表结构}
        {--no-compress : 不压缩备份文件}
        {--chunk= : 自定义分批大小}
        {--keep= : 保留最近N个备份}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '备份指定数据表的数据和结构';

    protected TableResolver $resolver;

    protected DataExporter $exporter;

    protected BackupFileManager $fileManager;

    /**
     * 执行命令
     */
    public function handle(
        TableResolver $resolver,
        DataExporter $exporter,
        BackupFileManager $fileManager
    ): int {
        $this->resolver = $resolver;
        $this->exporter = $exporter;
        $this->fileManager = $fileManager;

        $this->info('开始数据库表备份...');

        try {
            // 解析要备份的表
            $tables = $this->resolveTables();

            if (empty($tables)) {
                $this->error('没有找到要备份的表');

                return self::FAILURE;
            }

            $this->info('找到 '.count($tables).' 个表需要备份');

            // 获取配置
            $config = $this->getConfig();

            // 确保备份目录存在
            $this->fileManager->ensureDirectoryExists($config['path']);

            // 备份每个表
            $success = 0;
            $failed = 0;

            foreach ($tables as $table) {
                $this->newLine();
                $this->info("[{$table}] 开始备份...");

                try {
                    $this->backupTable($table, $config);
                    $this->info("[{$table}] 备份完成 ✓");
                    $success++;
                } catch (\Exception $e) {
                    $this->error("[{$table}] 备份失败: ".$e->getMessage());
                    $failed++;
                }
            }

            // 显示统计信息
            $this->newLine();
            $this->info('备份完成！');
            $this->info("成功: {$success}, 失败: {$failed}");

            // 清理旧备份
            if ($config['cleanup_enabled'] && $config['retention_count'] > 0) {
                $this->newLine();
                $this->info('清理旧备份文件...');
                $this->cleanupBackups($tables, $config);
            }

            return $failed > 0 ? self::FAILURE : self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('备份失败: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * 解析要备份的表
     */
    protected function resolveTables(): array
    {
        $group = $this->option('group');
        $tablesOption = $this->option('tables');

        if ($group && $tablesOption) {
            $this->warn('警告: 同时指定了 --group 和 --tables，将使用 --tables');
        }

        // 优先使用 --tables 参数
        if ($tablesOption) {
            $patterns = array_map('trim', explode(',', $tablesOption));

            return $this->resolver->resolveTables($patterns);
        }

        // 使用 --group 参数
        if ($group) {
            return $this->resolver->resolveGroup($group);
        }

        // 未指定参数，提示用户
        $this->error('请使用 --group 或 --tables 参数指定要备份的表');

        return [];
    }

    /**
     * 获取配置
     */
    protected function getConfig(): array
    {
        $group = $this->option('group');
        $groupConfig = $group ? config("backup.groups.{$group}", []) : [];

        return [
            'path' => $this->option('path') ?? $groupConfig['path'] ?? config('backup.defaults.path'),
            'with_structure' => ! $this->option('no-structure') && ($groupConfig['with_structure'] ?? config('backup.defaults.with_structure', true)),
            'compress' => ! $this->option('no-compress') && ($groupConfig['compress'] ?? config('backup.defaults.compress', true)),
            'chunk_size' => (int) ($this->option('chunk') ?? $groupConfig['chunk_size'] ?? config('backup.defaults.chunk_size', 5000)),
            'retention_count' => (int) ($this->option('keep') ?? config('backup.cleanup.retention_count', 10)),
            'cleanup_enabled' => config('backup.cleanup.enabled', true),
        ];
    }

    /**
     * 备份单个表
     */
    protected function backupTable(string $table, array $config): void
    {
        // 获取表统计信息
        $stats = $this->exporter->getTableStats($table);
        $this->info('  行数: '.number_format($stats['row_count']));
        $this->info('  大小: '.$this->fileManager->formatBytes($stats['data_length']));

        // 生成文件路径
        $filepath = $this->fileManager->generateFilePath($table, $config['path'], false);

        // 导出表
        $sql = $this->exporter->exportTable(
            $table,
            $config['with_structure'],
            $config['chunk_size'],
            function ($processed, $total) {
                // 进度回调
                if ($total > 0) {
                    $percent = round(($processed / $total) * 100, 1);
                    $this->info("  进度: {$processed}/{$total} ({$percent}%)");
                }
            }
        );

        // 写入文件
        $this->fileManager->writeBackupFile($filepath, $sql);

        $this->info('  文件: '.basename($filepath));
        $this->info('  大小: '.$this->fileManager->formatBytes(filesize($filepath)));

        // 压缩文件
        if ($config['compress']) {
            $compressedPath = $this->fileManager->compressFile($filepath);
            $this->info('  压缩: '.basename($compressedPath));
            $this->info('  压缩后大小: '.$this->fileManager->formatBytes(filesize($compressedPath)));
        }
    }

    /**
     * 清理旧备份
     */
    protected function cleanupBackups(array $tables, array $config): void
    {
        $totalDeleted = 0;

        foreach ($tables as $table) {
            $deleted = $this->fileManager->cleanupOldBackups(
                $config['path'],
                $table,
                $config['retention_count']
            );

            if ($deleted > 0) {
                $this->info("  [{$table}] 删除了 {$deleted} 个旧备份");
                $totalDeleted += $deleted;
            }
        }

        if ($totalDeleted > 0) {
            $this->info("总共删除了 {$totalDeleted} 个旧备份文件");
        } else {
            $this->info('没有需要清理的旧备份');
        }
    }
}
