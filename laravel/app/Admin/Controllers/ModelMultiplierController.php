<?php

namespace App\Admin\Controllers;

use App\Models\ModelMultiplier;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

/**
 * 模型倍率管理控制器
 */
class ModelMultiplierController extends AdminController
{
    /**
     * 模型
     *
     * @var string
     */
    protected $model = ModelMultiplier::class;

    /**
     * 标题
     *
     * @return string
     */
    protected function title()
    {
        return '模型倍率管理';
    }

    /**
     * 列表页面
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(ModelMultiplier::query()->orderBy('priority', 'desc')->orderBy('id'), function (Grid $grid) {
            // 列表字段
            $grid->column('id', 'ID')->sortable();
            $grid->column('platform', '平台名称')->display(function ($value) {
                return $value ?: '全局';
            })->label('info');
            $grid->column('model_pattern', '模型匹配模式')->copyable();
            $grid->column('multiplier', '计费倍率')->display(function ($value) {
                return number_format($value, 2);
            })->label('primary');
            $grid->column('category', '分类')->display(function ($value) {
                return ModelMultiplier::getCategories()[$value] ?? $value;
            })->label(function () {
                return $this->getCategoryColor();
            });
            $grid->column('is_active', '是否启用')->switch();
            $grid->column('priority', '优先级')->sortable();
            $grid->column('created_at', '创建时间')->sortable();

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('id', 'ID');
                $filter->like('platform', '平台名称');
                $filter->equal('category', '分类')->select(ModelMultiplier::getCategories());
                $filter->equal('is_active', '是否启用')->select([
                    1 => '启用',
                    0 => '禁用',
                ]);
            });

            // 快速搜索
            $grid->quickSearch(['id', 'platform', 'model_pattern', 'description']);

            // 默认排序
            $grid->model()->orderBy('priority', 'desc')->orderBy('id');

            // 启用导出
            $grid->export();
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
        return Show::make($id, new ModelMultiplier, function (Show $show) {
            $show->field('id', 'ID');
            $show->field('platform', '平台名称')->as(function ($value) {
                return $value ?: '全局';
            });
            $show->field('model_pattern', '模型匹配模式');
            $show->field('multiplier', '计费倍率');
            $show->field('category', '分类')->using(ModelMultiplier::getCategories());
            $show->field('description', '描述');
            $show->field('is_active', '是否启用')->using([
                1 => '启用',
                0 => '禁用',
            ]);
            $show->field('priority', '优先级');
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
        return Form::make(new ModelMultiplier, function (Form $form) {
            // 基本信息
            $form->display('id', 'ID');

            $form->text('platform', '平台名称')
                ->maxLength(50)
                ->help('留空表示全局配置，适用于所有平台');

            $form->text('model_pattern', '模型匹配模式')
                ->required()
                ->maxLength(100)
                ->help('支持通配符，例如：gpt-4* 匹配所有 gpt-4 开头的模型');

            $form->decimal('multiplier', '计费倍率')
                ->required()
                ->default(1.00)
                ->attribute('step', '0.01')
                ->help('计费倍率，例如：1.5 表示按 1.5 倍计费');

            $form->select('category', '分类')
                ->options(ModelMultiplier::getCategories())
                ->default(ModelMultiplier::CATEGORY_STANDARD)
                ->required();

            $form->textarea('description', '描述')
                ->rows(3)
                ->maxLength(500);

            $form->switch('is_active', '是否启用')
                ->default(true);

            $form->number('priority', '优先级')
                ->default(0)
                ->min(0)
                ->max(1000)
                ->help('优先级越高越先匹配，相同优先级按ID排序');

            // 时间戳
            $form->display('created_at', '创建时间');
            $form->display('updated_at', '更新时间');
        });
    }
}
