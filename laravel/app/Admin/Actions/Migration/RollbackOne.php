<?php

namespace App\Admin\Actions\Migration;

use App\Services\Install\MigrationService;
use Dcat\Admin\Grid\RowAction;

/**
 * 回滚单个迁移操作
 */
class RollbackOne extends RowAction
{
    /**
     * 按钮标题（仅对已执行状态显示）
     */
    public function title()
    {
        if (isset($this->row->status) && $this->row->status === 'pending') {
            return '';
        }

        return '<i class="feather icon-rotate-ccw"></i> '.admin_trans('admin-migration.actions.rollback_one');
    }

    /**
     * 处理请求
     */
    public function handle()
    {
        $name = $this->getKey();

        // 文件锁，防止同时执行迁移
        $lockFile = storage_path('framework/migration_executing.lock');
        $fp = fopen($lockFile, 'w+');
        if (! flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);

            return $this->response()->error('有迁移正在执行中，请稍后再试');
        }

        try {
            $result = app(MigrationService::class)->rollbackOne($name);

            if ($result['success']) {
                return $this->response()->success('回滚成功')->refresh();
            }

            return $this->response()->error('回滚失败: '.$result['message']);
        } catch (\Exception $e) {
            return $this->response()->error('回滚异常: '.$e->getMessage());
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
            admin_trans('admin-migration.confirm.rollback_one'),
            admin_trans('admin-migration.confirm.rollback_one_desc'),
        ];
    }
}
