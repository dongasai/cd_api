<?php

namespace App\Admin\Actions\Migration;

use App\Services\Install\MigrationService;
use Dcat\Admin\Grid\BatchAction;
use Illuminate\Support\Facades\DB;

/**
 * 批量执行迁移操作
 */
class BatchMigrate extends BatchAction
{
    /**
     * 按钮标题
     */
    public function title()
    {
        return '<i class="feather icon-play-circle"></i> '.admin_trans('admin-migration.actions.batch_migrate');
    }

    /**
     * 处理请求
     */
    public function handle()
    {
        $names = $this->getSelectedKeys();

        if (empty($names)) {
            return $this->response()->error('请选择要执行的迁移');
        }

        // 文件锁，防止同时执行迁移
        $lockFile = storage_path('framework/migration_executing.lock');
        $fp = fopen($lockFile, 'w+');
        if (! flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);

            return $this->response()->error('有迁移正在执行中，请稍后再试');
        }

        try {
            $successCount = 0;
            $skipCount = 0;
            $failCount = 0;
            $errors = [];

            // 获取已执行迁移，避免重复执行
            $ranMigrations = DB::table('migrations')->pluck('migration')->toArray();

            foreach ($names as $name) {
                if (in_array($name, $ranMigrations)) {
                    $skipCount++;

                    continue;
                }

                $result = app(MigrationService::class)->migrateOne($name);
                if ($result['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                    $errors[] = "{$name}: {$result['message']}";
                }
            }

            if ($failCount === 0) {
                $msg = "批量迁移成功，共执行 {$successCount} 个";
                if ($skipCount > 0) {
                    $msg .= "，跳过已执行 {$skipCount} 个";
                }

                return $this->response()->success($msg)->refresh();
            }

            $errorMsg = "成功 {$successCount} 个，失败 {$failCount} 个";
            if (! empty($errors)) {
                $errorMsg .= "\n".implode("\n", array_slice($errors, 0, 3));
            }

            return $this->response()->error($errorMsg)->refresh();
        } catch (\Exception $e) {
            return $this->response()->error('批量迁移异常: '.$e->getMessage());
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * 确认对话框
     */
    public function confirm()
    {
        return [
            admin_trans('admin-migration.confirm.batch_migrate'),
            admin_trans('admin-migration.confirm.batch_migrate_desc'),
        ];
    }
}
