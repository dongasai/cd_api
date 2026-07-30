<?php

namespace App\Admin\Grids;

use App\Models\ChannelTag;
use Dcat\Admin\Grid;
use Dcat\Admin\Grid\LazyRenderable;

/**
 * 渠道标签选择表格
 */
class ChannelTagSelectGrid extends LazyRenderable
{
    /**
     * 创建表格
     */
    public function grid(): Grid
    {
        return Grid::make(ChannelTag::query()->select('id', 'name', 'color', 'description'), function (Grid $grid) {
            $grid->column('id', 'ID')->sortable();
            $grid->column('name', '标签名称');
            $grid->column('color', '颜色')->display(function ($value) {
                return "<span style='background:{$value};padding:2px 8px;border-radius:3px;'>{$value}</span>";
            });
            $grid->column('description', '描述')->limit(30);

            $grid->quickSearch(['id', 'name']);
            $grid->paginate(10);

            // 禁用不需要的功能
            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->disableBatchDelete();
        });
    }
}
