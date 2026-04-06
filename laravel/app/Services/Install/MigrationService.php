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
        } catch (\Exception $e) {
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
     * 执行数据初始化 Seeder
     *
     * 按顺序执行各 Seeder
     *
     * @return array 执行结果
     */
    public function seed(): array
    {
        $seeders = [
            'AdminTablesSeeder',    // 后台菜单、权限、角色（必须先执行）
            'SystemSettingSeeder',  // 系统配置
            'PresetPromptSeeder',   // 预设提示词
            'ChannelAffinityRuleSeeder', // 渠道亲和性规则
        ];

        $results = [];

        foreach ($seeders as $seeder) {
            try {
                Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
                $results[$seeder] = [
                    'success' => true,
                    'output' => Artisan::output(),
                ];
            } catch (\Exception $e) {
                $results[$seeder] = [
                    'success' => false,
                    'output' => Artisan::output(),
                    'message' => $e->getMessage(),
                ];

                // 如果 AdminTablesSeeder 失败，后续无法继续
                if ($seeder === 'AdminTablesSeeder') {
                    return [
                        'success' => false,
                        'results' => $results,
                        'message' => 'AdminTablesSeeder 执行失败，无法继续安装',
                    ];
                }
            }
        }

        $allSuccess = collect($results)->every(fn ($r) => $r['success']);

        return [
            'success' => $allSuccess,
            'results' => $results,
            'message' => $allSuccess ? '数据初始化成功' : '部分 Seeder 执行失败',
        ];
    }
}
