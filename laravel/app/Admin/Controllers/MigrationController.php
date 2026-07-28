<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Migration\BatchMigrate;
use App\Admin\Actions\Migration\MigrateAll;
use App\Admin\Actions\Migration\MigrateOne;
use App\Admin\Actions\Migration\RollbackOne;
use App\Admin\Repositories\MigrationRepository;
use App\Services\Install\MigrationService;
use Dcat\Admin\Grid;
use Dcat\Admin\Grid\Displayers\Actions;
use Dcat\Admin\Http\Controllers\AdminController;

/**
 * 数据库迁移管理控制器
 *
 * 展示迁移列表，支持手动执行迁移
 */
class MigrationController extends AdminController
{
    public $translation = 'admin-migration';

    /**
     * 数据仓库
     *
     * @var string
     */
    protected $repository = MigrationRepository::class;

    /**
     * 禁用的操作
     *
     * @var array
     */
    protected $disableActions = ['create', 'update', 'delete'];

    /**
     * 列表页面
     */
    protected function grid(): Grid
    {
        return Grid::make(new MigrationRepository, function (Grid $grid) {
            // 列表字段
            $grid->column('name', admin_trans_field('name'))->display(function ($value) {
                // 截取显示，完整名称用 title 属性
                if (strlen($value) > 60) {
                    $short = substr($value, 0, 57).'...';

                    return "<span title='{$value}'>{$short}</span>";
                }

                return $value;
            })->sortable();
            $grid->column('status', admin_trans_field('status'))->display(function ($value) {
                if ($value === 'ran') {
                    return "<span class='badge bg-success'>".admin_trans('admin-migration.labels.ran').'</span>';
                }

                return "<span class='badge bg-warning'>".admin_trans('admin-migration.labels.pending').'</span>';
            })->filter('ran', 'pending')->sortable();
            $grid->column('batch', admin_trans_field('batch'))->display(function ($value) {
                return $value ?? '-';
            })->sortable();
            $grid->column('file', admin_trans_field('file'))->display(function ($value) {
                return "<span class='text-muted small'>{$value}</span>";
            });

            // 禁用创建按钮
            $grid->disableCreateButton();

            // 禁用编辑按钮
            $grid->disableEditButton();

            // 禁用删除按钮
            $grid->disableDeleteButton();

            // 禁用批量删除
            $grid->disableBatchDelete();

            // 禁用详情按钮
            $grid->disableViewButton();

            // 禁用快速编辑
            $grid->disableQuickEditButton();

            // 直接显示操作按钮（不使用下拉菜单）
            $grid->setActionClass(Actions::class);

            // 添加行操作：执行单个迁移
            $grid->actions(function (Actions $actions) {
                // 仅对 pending 状态添加执行按钮
                if ($actions->row->status === 'pending') {
                    $actions->append(new MigrateOne);
                } else {
                    // 已执行的迁移添加回滚按钮
                    $actions->append(new RollbackOne);
                }
            });

            // 添加批量操作
            $grid->batchActions(function ($batch) {
                $batch->add(new BatchMigrate);
            });

            // 添加工具栏按钮：执行全部
            $grid->tools(function (Grid\Tools $tools) {
                $tools->append(new MigrateAll);
            });

            // 筛选器
            $grid->filter(function ($filter) {
                $filter->panel();
                $filter->expand(true);

                $filter->equal('status', admin_trans_field('status'))->select([
                    'pending' => admin_trans('admin-migration.labels.pending'),
                    'ran' => admin_trans('admin-migration.labels.ran'),
                ]);
            });

            // 设置每页显示行数
            $grid->paginate(30);
        });
    }

    /**
     * 禁用表单
     */
    protected function form()
    {
        abort(404);
    }

    /**
     * 禁用详情
     */
    protected function detail($id)
    {
        abort(404);
    }

    /**
     * 标题
     */
    protected function title(): string
    {
        return admin_trans('admin-migration.labels.title');
    }

    /**
     * 执行全部待执行迁移（AJAX 接口）
     */
    public function migrateAll()
    {
        // 文件锁，防止同时执行迁移
        $lockFile = storage_path('framework/migration_executing.lock');
        $fp = fopen($lockFile, 'w+');
        if (! flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);

            return response()->json(['status' => false, 'message' => '有迁移正在执行中，请稍后再试']);
        }

        try {
            $result = app(MigrationService::class)->migrate();

            return response()->json([
                'status' => $result['success'],
                'message' => $result['success'] ? '全部迁移执行成功' : $result['message'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => '迁移执行异常: '.$e->getMessage()]);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
