<?php

namespace App\Admin\Controllers;

use App\Enums\OperationTarget;
use App\Enums\OperationType;
use App\Models\OperationLog;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

/**
 * 操作日志控制器
 *
 * 用于查看系统操作日志,只读模式
 */
class OperationLogController extends AdminController
{
    /**
     * 数据模型
     *
     * @var string
     */
    protected $model = OperationLog::class;

    /**
     * 禁用的操作
     *
     * @var array
     */
    protected $disableActions = ['create', 'update', 'delete'];

    /**
     * 列表页面
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(OperationLog::with(['user']), function (Grid $grid) {
            // 默认按创建时间倒序排序
            $grid->model()->orderBy('id', 'desc');

            // 列表字段
            $grid->column('id', 'ID')->sortable();
            $grid->column('type', '操作类型')->display(function ($value) {
                return $this->type?->label() ?? $value;
            });
            $grid->column('target', '操作对象')->display(function ($value) {
                return $this->target?->label() ?? $value;
            });
            $grid->column('target_name', '对象名称');
            $grid->column('username', '操作用户');
            $grid->column('description', '操作描述');
            $grid->column('ip', 'IP地址');
            $grid->column('created_at', '创建时间')->sortable();

            // 筛选器
            $grid->filter(function ($filter) {
                // 操作类型筛选
                $filter->equal('type', '操作类型')->select(OperationType::options());

                // 操作对象筛选
                $filter->equal('target', '操作对象')->select(OperationTarget::options());

                // 用户名筛选
                $filter->like('username', '操作用户');

                // 创建时间范围筛选
                $filter->between('created_at', '创建时间')->datetime();
            });

            // 禁用创建按钮
            $grid->disableCreateButton();

            // 禁用编辑按钮
            $grid->disableEditButton();

            // 禁用删除按钮
            $grid->disableDeleteButton();

            // 禁用批量删除
            $grid->disableBatchDelete();

            // 启用详情按钮
            $grid->showViewButton();

            // 设置每页显示行数
            $grid->paginate(20);

            // 显示横向滚动条
            $grid->scrollbarX();
        });
    }

    /**
     * 详情页面
     *
     * @param  mixed  $id
     * @return Show
     */
    protected function detail($id)
    {
        return Show::make(OperationLog::with(['user'])->findOrFail($id), function (Show $show) {
            // 基本信息
            $show->field('id', 'ID');
            $show->field('type', '操作类型')->as(function ($value) {
                return $this->type?->label() ?? $value;
            });
            $show->field('target', '操作对象')->as(function ($value) {
                return $this->target?->label() ?? $value;
            });
            $show->field('target_id', '对象ID');
            $show->field('target_name', '对象名称');

            // 操作来源
            $show->field('source', '操作来源')->as(function ($value) {
                return $this->source?->label() ?? $value;
            });

            // 操作用户信息
            $show->field('user_id', '用户ID');
            $show->field('username', '用户名');

            // 操作详情
            $show->field('description', '操作描述');
            $show->field('reason', '操作原因');

            // 数据变更 - 格式化JSON显示
            $show->field('before_data', '变更前数据')->json();
            $show->field('after_data', '变更后数据')->json();

            // 客户端信息
            $show->field('ip', 'IP地址');
            $show->field('user_agent', 'User Agent');

            // 时间信息
            $show->field('created_at', '创建时间');

            // 禁用编辑按钮
            $show->disableEditButton();

            // 禁用删除按钮
            $show->disableDeleteButton();
        });
    }

    /**
     * 禁用表单
     *
     * @return \Illuminate\Http\Response
     */
    protected function form()
    {
        // 只读模式,不提供表单
        abort(404);
    }
}
