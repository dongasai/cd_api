<?php

namespace App\Http\Controllers;

use App\Services\Install\MigrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 升级控制器
 *
 * 处理系统升级（数据库迁移）流程
 */
class UpgradeController extends Controller
{
    /**
     * 检查是否已安装
     */
    private function checkInstalled(): bool
    {
        return file_exists(storage_path('installed.lock'));
    }

    /**
     * 升级首页 - 显示待执行的迁移
     */
    public function index(): View
    {
        // 未安装则跳转到安装页面
        if (! $this->checkInstalled()) {
            return redirect('/install');
        }

        $pendingMigrations = $this->getPendingMigrations();
        $currentVersion = $this->getCurrentVersion();

        return view('upgrade.index', compact('pendingMigrations', 'currentVersion'));
    }

    /**
     * 执行升级 API
     */
    public function execute(): JsonResponse
    {
        // 未安装则返回错误
        if (! $this->checkInstalled()) {
            return response()->json(['success' => false, 'message' => '系统未安装'], 403);
        }

        $migrationService = new MigrationService;
        $result = $migrationService->migrate();

        return response()->json($result);
    }

    /**
     * 获取待执行的迁移文件列表
     *
     * @return array 待执行的迁移文件
     */
    private function getPendingMigrations(): array
    {
        try {
            // 获取所有迁移文件
            $migrationPath = database_path('migrations');
            $allMigrations = glob($migrationPath.'/*.php');
            $allMigrations = array_map('basename', $allMigrations);

            // 获取已执行的迁移
            $ranMigrations = DB::table('migrations')->pluck('migration')->toArray();

            // 计算待执行的迁移
            $pendingMigrations = [];
            foreach ($allMigrations as $migration) {
                $migrationName = str_replace('.php', '', $migration);
                if (! in_array($migrationName, $ranMigrations)) {
                    $pendingMigrations[] = [
                        'file' => $migration,
                        'name' => $migrationName,
                    ];
                }
            }

            return $pendingMigrations;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 获取当前版本信息
     *
     * @return string 版本信息
     */
    private function getCurrentVersion(): string
    {
        // 从 migrations 表中获取最新迁移时间
        try {
            $lastMigration = DB::table('migrations')->orderBy('id', 'desc')->first();

            if ($lastMigration) {
                return $lastMigration->migration;
            }
        } catch (\Exception $e) {
        }

        return '未初始化';
    }
}
