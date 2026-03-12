<?php

namespace App\Admin\Controllers;

use App\Models\ChannelGroup;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;

/**
 * 渠道分组管理控制器
 */
class ChannelGroupController extends AdminController
{
    /**
     * 页面标题
     *
     * @var string
     */
    protected $title = '渠道分组';

    /**
     * 获取模型
     *
     * @return string
     */
    protected function model()
    {
        return ChannelGroup::class;
    }

    /**
     * 列表页面
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new ChannelGroup, function (Grid $grid) {
            // 默认排序
            $grid->model()->orderBy('id', 'desc');

            // 列表字段
            $grid->column('id', 'ID')->sortable();
            $grid->column('name', '分组名称');
            $grid->column('slug', '标识符');
            $grid->column('description', '描述')->limit(50);
            $grid->column('created_at', '创建时间')->sortable();

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('id', 'ID');
                $filter->like('name', '分组名称');
                $filter->like('slug', '标识符');
            });

            // 操作按钮
            $grid->actions(function (Grid\Displayers\Actions $actions) {
                // 禁用查看按钮
                $actions->disableView();
            });

            // 快速创建按钮
            $grid->quickCreateButton();

            // 启用导出
            $grid->export();
        });
    }

    /**
     * 表单页面
     *
     * @return Form
     */
    protected function form()
    {
        return Form::make(new ChannelGroup, function (Form $form) {
            // 表单字段
            $form->display('id', 'ID');

            $form->text('name', '分组名称')
                ->required()
                ->maxLength(100)
                ->help('分组显示名称，如：生产环境、测试环境');

            $form->text('slug', '标识符')
                ->required()
                ->maxLength(50)
                ->pattern('[a-z0-9_-]+')
                ->help('唯一标识符，只能包含小写字母、数字、下划线和连字符，如：production、test-env');

            $form->textarea('description', '描述')
                ->maxLength(500)
                ->rows(3)
                ->help('分组描述信息');

            $form->textarea('config', '配置')
                ->rows(10)
                ->help('JSON 格式的配置信息，如：{"priority": 1, "fallback_enabled": true}');

            $form->display('created_at', '创建时间');
            $form->display('updated_at', '更新时间');

            // 保存前验证 JSON 格式
            $form->saving(function (Form $form) {
                $config = $form->input('config');
                if (! empty($config)) {
                    // 如果是字符串，验证 JSON 格式
                    if (is_string($config)) {
                        $decoded = json_decode($config, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            return $form->response()->error('配置必须是有效的 JSON 格式');
                        }
                    }
                }
            });

            // 表单底部按钮
            $form->footer(function ($footer) {
                // 禁用查看按钮
                $footer->disableViewCheck();
            });
        });
    }
}
