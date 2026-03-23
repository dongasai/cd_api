<?php

namespace App\Admin\Grids;

use App\Enums\ChannelStatus;
use App\Models\Channel;
use Dcat\Admin\Grid;
use Dcat\Admin\Grid\LazyRenderable;

/**
 * 渠道选择表格
 */
class ChannelSelectGrid extends LazyRenderable
{
    /**
     * 创建表格
     */
    public function grid(): Grid
    {
        return Grid::make(Channel::query()->select('id', 'name', 'slug', 'provider', 'status'), function (Grid $grid) {
            // 启用行选择器（多选）
            // $grid->rowSelector()->click();

            $grid->column('id', 'ID')->sortable();
            $grid->column('name', '渠道名称');
            $grid->column('slug', '标识符');
            $grid->column('provider', '提供商');
            $grid->column('status', '状态')->display(function ($value) {
                // 处理枚举类型
                if ($value instanceof \BackedEnum) {
                    $options = ChannelStatus::options();
                    return $options[$value->value] ?? $value->value;
                }
                return $value;
            });

            $grid->quickSearch(['id', 'name', 'slug']);
            $grid->paginate(10);

            // 禁用不需要的功能
            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->disableBatchDelete();
        });
    }
}
