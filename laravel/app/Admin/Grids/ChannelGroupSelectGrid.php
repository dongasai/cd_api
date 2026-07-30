<?php

namespace App\Admin\Grids;

use App\Models\ChannelGroup;
use Dcat\Admin\Grid;
use Dcat\Admin\Grid\LazyRenderable;

/**
 * 渠道分组选择表格
 */
class ChannelGroupSelectGrid extends LazyRenderable
{
    /**
     * 创建表格
     */
    public function grid(): Grid
    {
        return Grid::make(ChannelGroup::query()->select('id', 'name', 'slug', 'description'), function (Grid $grid) {
            $grid->column('id', 'ID')->sortable();
            $grid->column('name', '分组名称');
            $grid->column('slug', '标识符');
            $grid->column('description', '描述')->limit(30);

            $grid->quickSearch(['id', 'name', 'slug']);
            $grid->paginate(10);

            // 禁用不需要的功能
            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->disableBatchDelete();
        });
    }
}
