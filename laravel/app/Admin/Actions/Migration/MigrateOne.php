<?php

namespace App\Admin\Actions\Migration;

use App\Services\Install\MigrationService;
use Dcat\Admin\Grid\RowAction;

/**
 * 执行单个迁移操作
 */
class MigrateOne extends RowAction
{
    /**
     * 按钮标题（仅对 pending 状态显示）
     */
    public function title()
    {
        if (isset($this->row->status) && $this->row->status === 'ran') {
            return '';
        }

        return '<i class="feather icon-play"></i> '.admin_trans('admin-migration.actions.migrate_one');
    }

    /**
     * 处理请求
     */
    public function handle()
    {
        $name = $this->getKey();

        // 验证迁移文件存在
        $filePath = database_path("migrations/{$name}.php");
        if (! file_exists($filePath)) {
            return $this->response()->error("迁移文件不存在: {$name}");
        }

        // 文件锁，防止同时执行迁移
        $lockFile = storage_path('framework/migration_executing.lock');
        $fp = fopen($lockFile, 'w+');
        if (! flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);

            return $this->response()->error('有迁移正在执行中，请稍后再试');
        }

        try {
            $result = app(MigrationService::class)->migrateOne($name);

            if ($result['success']) {
                return $this->response()->success('迁移执行成功')->refresh();
            }

            return $this->response()->error('迁移执行失败: '.$result['message']);
        } catch (\Exception $e) {
            return $this->response()->error('迁移执行异常: '.$e->getMessage());
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
            admin_trans('admin-migration.confirm.migrate_one'),
            admin_trans('admin-migration.confirm.migrate_one_desc'),
        ];
    }
}
