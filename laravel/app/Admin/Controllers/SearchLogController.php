<?php

namespace App\Admin\Controllers;

use App\Models\SearchLog;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

/**
 * 搜索日志控制器
 */
class SearchLogController extends AdminController
{
    /**
     * 语言包名称
     *
     * @var string
     */
    public $translation = 'admin-search-log';

    /**
     * 列表页面
     */
    protected function grid()
    {
        return Grid::make(SearchLog::query()->with('searchDriver'), function (Grid $grid) {
            // 默认排序
            $grid->model()->orderBy('id', 'desc');

            // 列字段配置
            $grid->column('id')->sortable();
            $grid->column('query')->limit(30);
            $grid->column('driver')->display(function ($value) {
                $driver = $this->searchDriver;
                if ($driver) {
                    return "<a href='".admin_url('search-drivers/'.$driver->id)."'>{$driver->name}</a>";
                }

                return $value;
            });
            $grid->column('success')->display(function ($value) {
                return $value
                    ? "<span class='badge bg-success'>成功</span>"
                    : "<span class='badge bg-danger'>失败</span>";
            });
            $grid->column('result_count')->sortable();
            $grid->column('total_count')->sortable();
            $grid->column('response_time_ms')->display(function ($value) {
                if ($value === null) {
                    return '-';
                }

                return $value.' ms';
            })->sortable();
            $grid->column('client_ip')->limit(20);
            $grid->column('searched_at')->sortable();

            // 禁用新增和编辑按钮
            $grid->disableCreateButton();
            $grid->disableEditButton();
            $grid->disableViewButton();

            // 行操作：详情
            $grid->actions(function (Grid\Displayers\Actions $actions) {
                $actions->append(
                    "<a href='".admin_url('search-logs/'.$this->id)."'><i class='feather icon-eye'></i> 详情</a>"
                );
            });

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                $filter->panel();
                $filter->like('query');
                $filter->equal('driver')->select(
                    SearchLog::query()->distinct()->pluck('driver', 'driver')->toArray()
                );
                $filter->equal('success')->select([
                    1 => '成功',
                    0 => '失败',
                ]);
                $filter->between('searched_at')->datetime();
            });

            // 快速搜索
            $grid->quickSearch(['query', 'client_ip']);
        });
    }

    /**
     * 详情页面
     */
    protected function detail($id)
    {
        return Show::make($id, SearchLog::query()->with('searchDriver'), function (Show $show) {
            $show->field('id');
            $show->field('query');
            $show->field('driver')->as(function ($value) {
                $driver = $this->searchDriver;

                return $driver ? $driver->name : $value;
            });
            $show->field('success')->as(function ($value) {
                return $value ? '成功' : '失败';
            });
            $show->field('result_count');
            $show->field('total_count');
            $show->field('response_time_ms')->as(function ($value) {
                return $value ? $value.' ms' : '-';
            });
            $show->field('error_message');
            $show->field('filters')->json();
            $show->field('results')->json();
            $show->field('client_ip');
            $show->field('searched_at');

            // 禁用编辑和删除按钮
            $show->disableEditButton();
            $show->disableDeleteButton();
        });
    }

    /**
     * 不允许新建
     */
    protected function form()
    {
        return null;
    }
}
