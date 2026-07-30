<?php

namespace App\Services\Install;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * 数据库迁移服务
 *
 * 执行数据库迁移和 Seeder
 */
class MigrationService
{
    /**
     * 获取待执行的迁移文件列表
     */
    public function getPendingMigrations(): array
    {
        $migrationPath = database_path('migrations');
        $allMigrations = glob($migrationPath.'/*.php');
        $allMigrations = array_map('basename', $allMigrations);

        try {
            $ranMigrations = DB::table('migrations')->pluck('migration')->toArray();
        } catch (\Exception) {
            $ranMigrations = [];
        }

        $pending = [];
        foreach ($allMigrations as $migration) {
            $migrationName = str_replace('.php', '', $migration);
            if (! in_array($migrationName, $ranMigrations)) {
                $pending[] = [
                    'file' => $migration,
                    'name' => $migrationName,
                ];
            }
        }

        // 按文件名排序
        usort($pending, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $pending;
    }

    /**
     * 执行单个迁移文件
     */
    public function migrateOne(string $migrationName): array
    {
        try {
            Artisan::call('migrate', [
                '--force' => true,
                '--path' => 'database/migrations/'.$migrationName.'.php',
            ]);
            $output = Artisan::output();

            return [
                'success' => true,
                'output' => $output,
                'message' => '迁移成功',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => Artisan::output(),
                'message' => '迁移失败: '.$e->getMessage(),
            ];
        }
    }

    /**
     * 执行数据库迁移（全部）
     *
     * @return array 执行结果
     */
    public function migrate(): array
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();

            return [
                'success' => true,
                'output' => $output,
                'message' => '数据库迁移成功',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => Artisan::output(),
                'message' => '数据库迁移失败: '.$e->getMessage(),
            ];
        }
    }

    /**
     * 回滚单个迁移文件
     */
    public function rollbackOne(string $migrationName): array
    {
        try {
            $filePath = database_path("migrations/{$migrationName}.php");
            if (! file_exists($filePath)) {
                return [
                    'success' => false,
                    'message' => "迁移文件不存在: {$migrationName}",
                ];
            }

            // 检查迁移是否已执行
            $exists = DB::table('migrations')->where('migration', $migrationName)->exists();
            if (! $exists) {
                return [
                    'success' => false,
                    'message' => '该迁移尚未执行，无需回滚',
                ];
            }

            // 加载并执行迁移的 down 方法
            $migrationInstance = require $filePath;
            $migrationInstance->down();

            // 从 migrations 表删除记录
            DB::table('migrations')->where('migration', $migrationName)->delete();

            return [
                'success' => true,
                'message' => '回滚成功',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '回滚失败: '.$e->getMessage(),
            ];
        }
    }

    /**
     * 执行数据初始化（已迁移至迁移文件）
     *
     * 数据填充已通过迁移文件实现，此方法保留兼容性但仅执行 migrate
     *
     * @return array 执行结果
     */
    public function seed(): array
    {
        return $this->migrate();
    }
}
