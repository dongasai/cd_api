<?php

namespace App\Admin\Controllers;

use App\Admin\Controllers\Actions\SetDefaultSearchDriver;
use App\Models\SearchDriver;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Grid\Displayers\Actions;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

/**
 * 搜索驱动配置管理控制器
 */
class SearchDriverController extends AdminController
{
    /**
     * 语言包名称
     *
     * @var string
     */
    public $translation = 'admin-search-driver';

    /**
     * 列表页面
     */
    protected function grid()
    {
        return Grid::make(SearchDriver::query(), function (Grid $grid) {
            // 默认排序
            $grid->model()->orderBy('priority', 'desc')->orderBy('id', 'desc');

            // 列字段配置
            $grid->column('id')->sortable();
            $grid->column('name')->link(function () {
                return admin_url('search-drivers/'.$this->id);
            });
            $grid->column('slug');
            $grid->column('driver_class')->limit(30);
            $grid->column('priority')->sortable();
            $grid->column('is_default')->display(function ($value) {
                return $value
                    ? '<span class="badge bg-success">默认</span>'
                    : '<span class="badge bg-secondary">-</span>';
            });
            $grid->column('status')->display(function ($value) {
                $labels = SearchDriver::getStatuses();
                $colors = [
                    SearchDriver::STATUS_ACTIVE => 'success',
                    SearchDriver::STATUS_INACTIVE => 'secondary',
                    SearchDriver::STATUS_ERROR => 'danger',
                ];
                $color = $colors[$value] ?? 'secondary';
                $label = $labels[$value] ?? $value;

                return "<span class='badge bg-{$color}'>{$label}</span>";
            });
            $grid->column('timeout')->display(function ($value) {
                return "{$value}s";
            });
            $grid->column('usage_count')->sortable();
            $grid->column('last_used_at')->sortable();
            $grid->column('created_at')->sortable();

            // 操作按钮
            $grid->actions(function (Actions $actions) {
                // 设置为默认
                $actions->append(new SetDefaultSearchDriver);
            });

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                $filter->panel();
                $filter->like('name');
                $filter->equal('status')->select(SearchDriver::getStatuses());
                $filter->equal('is_default')->select([0 => '否', 1 => '是']);
            });

            // 快速搜索
            $grid->quickSearch(['name', 'slug']);
        });
    }

    /**
     * 详情页面
     */
    protected function detail($id)
    {
        return Show::make($id, SearchDriver::query(), function (Show $show) {
            $show->field('id');
            $show->field('name');
            $show->field('slug');
            $show->field('driver_class');
            $show->field('config')->json();
            $show->field('timeout');
            $show->field('priority');
            $show->field('is_default');
            $show->field('status')->as(function ($value) {
                return SearchDriver::getStatuses()[$value] ?? $value;
            });
            $show->field('description');
            $show->field('usage_count');
            $show->field('last_used_at');
            $show->field('error_message');
            $show->field('created_at');
            $show->field('updated_at');
        });
    }

    /**
     * 表单页面
     */
    protected function form()
    {
        return Form::make(SearchDriver::query(), function (Form $form) {
            $form->display('id');

            $form->text('name')->required();
            $form->text('slug')->required()->help('唯一标识符，用于配置引用');

            $form->select('driver_class')
                ->options([
                    'App\Services\Search\Driver\MockSearchDriver' => 'Mock (模拟)',
                    'App\Services\Search\Driver\SerperSearchDriver' => 'Serper (Google)',
                    'App\Services\Search\Driver\DuckDuckGoSearchDriver' => 'DuckDuckGo',
                ])
                ->required();

            $form->keyValue('config')
                ->help('驱动配置参数，如 api_key、endpoint 等');

            $form->number('timeout')
                ->min(1)
                ->max(120)
                ->default(10)
                ->help('请求超时秒数');

            $form->number('priority')
                ->min(0)
                ->max(100)
                ->default(0)
                ->help('优先级，数值越大优先级越高');

            $form->switch('is_default')
                ->help('设为默认驱动');

            $form->select('status')
                ->options(SearchDriver::getStatuses())
                ->default('active');

            $form->textarea('description')->rows(3);

            $form->display('usage_count');
            $form->display('last_used_at');
            $form->display('error_message');
            $form->display('created_at');
            $form->display('updated_at');

            // 保存时处理默认驱动
            $form->saving(function (Form $form) {
                if ($form->is_default === 1) {
                    // 清除其他默认
                    SearchDriver::where('is_default', true)
                        ->where('id', '!=', $form->model()->id ?? 0)
                        ->update(['is_default' => false]);
                }
            });
        });
    }
}
