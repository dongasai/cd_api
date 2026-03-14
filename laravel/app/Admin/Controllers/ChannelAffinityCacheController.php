<?php

namespace App\Admin\Controllers;

use App\Models\ChannelAffinityCache;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

/**
 * 渠道亲和性缓存控制器
 *
 * 用于查看渠道亲和性缓存记录,只读模式
 */
class ChannelAffinityCacheController extends AdminController
{
    /**
     * 数据模型
     *
     * @var string
     */
    protected $model = ChannelAffinityCache::class;

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
        return Grid::make(ChannelAffinityCache::with(['channel']), function (Grid $grid) {
            // 默认按创建时间倒序排序
            $grid->model()->orderBy('id', 'desc');

            // 列表字段
            $grid->column('id', 'ID')->sortable();
            $grid->column('rule_id', '规则ID');
            $grid->column('key_hash', 'Key哈希')->limit(20);
            $grid->column('channel_name', '渠道名称');
            $grid->column('key_hint', 'Key提示')->limit(20);
            $grid->column('hit_count', '命中次数')->sortable();
            $grid->column('expires_at', '过期时间')->sortable();
            $grid->column('created_at', '创建时间')->sortable();

            // 筛选器
            $grid->filter(function ($filter) {
                // 不使用抽屉模式，直接展开
                $filter->panel();
                $filter->expand(true);

                // 规则ID筛选
                $filter->equal('rule_id', '规则ID');

                // 渠道名称筛选
                $filter->like('channel_name', '渠道名称');

                // Key哈希筛选
                $filter->like('key_hash', 'Key哈希');

                // Key提示筛选
                $filter->like('key_hint', 'Key提示');

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

            // 直接显示操作按钮（不使用下拉菜单）
            $grid->setActionClass(\Dcat\Admin\Grid\Displayers\Actions::class);

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
        return Show::make(ChannelAffinityCache::with(['channel'])->findOrFail($id), function (Show $show) {
            // 基本信息
            $show->field('id', 'ID');
            $show->field('rule_id', '规则ID');
            $show->field('key_hash', 'Key哈希');
            $show->field('channel_id', '渠道ID');
            $show->field('channel_name', '渠道名称');
            $show->field('key_hint', 'Key提示');
            $show->field('hit_count', '命中次数');
            $show->field('expires_at', '过期时间');
            $show->field('created_at', '创建时间');
            $show->field('updated_at', '更新时间');

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
