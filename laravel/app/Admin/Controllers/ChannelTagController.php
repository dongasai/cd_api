<?php

namespace App\Admin\Controllers;

use App\Models\ChannelTag;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

/**
 * 渠道标签管理控制器
 */
class ChannelTagController extends AdminController
{
    /**
     * 获取模型标题
     *
     * @return string
     */
    protected function title()
    {
        return '渠道标签';
    }

    /**
     * 列表页面
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new ChannelTag, function (Grid $grid) {
            // 默认排序
            $grid->model()->orderBy('id', 'desc');

            // 列表字段
            $grid->column('id', 'ID')->sortable();
            $grid->column('name', '标签名称');
            $grid->column('color', '颜色')->display(function ($color) {
                // 显示颜色块
                return "<span style='display: inline-block; width: 24px; height: 24px; background-color: {$color}; border-radius: 4px; border: 1px solid #ddd; vertical-align: middle;'></span> <span style='vertical-align: middle;'>{$color}</span>";
            });
            $grid->column('description', '描述')->limit(50);
            $grid->column('created_at', '创建时间')->sortable();

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                // 不使用抽屉模式，直接展开
                $filter->panel();
                $filter->expand(true);

                $filter->equal('id', 'ID');
                $filter->like('name', '标签名称');
            });

            // 启用快捷搜索
            $grid->quickSearch('id', 'name');

            // 启用批量操作
            $grid->enableDialogCreate();
            $grid->showColumnSelector();
            $grid->showQuickEditButton();
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
        return Show::make($id, new ChannelTag, function (Show $show) {
            $show->field('id', 'ID');
            $show->field('name', '标签名称');
            $show->field('color', '颜色')->as(function ($color) {
                return "<span style='display: inline-block; width: 24px; height: 24px; background-color: {$color}; border-radius: 4px; border: 1px solid #ddd; vertical-align: middle;'></span> <span style='vertical-align: middle;'>{$color}</span>";
            })->unescape();
            $show->field('description', '描述');
            $show->field('created_at', '创建时间');
            $show->field('updated_at', '更新时间');
        });
    }

    /**
     * 表单页面
     *
     * @return Form
     */
    protected function form()
    {
        return Form::make(new ChannelTag, function (Form $form) {
            $form->display('id', 'ID');

            // 标签名称
            $form->text('name', '标签名称')
                ->required()
                ->maxLength(50)
                ->rules('unique:channel_tags,name,'.$form->getKey().',id,deleted_at,NULL', [
                    'unique' => '标签名称已存在',
                ]);

            // 颜色选择器
            $form->color('color', '颜色')
                ->required()
                ->default('#FF5733')
                ->help('请选择标签颜色');

            // 描述
            $form->textarea('description', '描述')
                ->maxLength(255)
                ->rows(3);

            $form->display('created_at', '创建时间');
            $form->display('updated_at', '更新时间');

            // 保存前验证
            $form->saving(function (Form $form) {
                // 确保颜色格式正确
                if ($form->color && ! preg_match('/^#[0-9A-Fa-f]{6}$/', $form->color)) {
                    return $form->response()->error('颜色格式不正确，请使用 #RRGGBB 格式');
                }
            });
        });
    }
}
