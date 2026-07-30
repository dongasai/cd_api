<?php

namespace App\Admin\Extensions\Grid\Displayers;

use Dcat\Admin\Grid\Displayers\Actions;

/**
 * 中文操作按钮显示器
 *
 * 将默认的图标按钮替换为中文文字按钮，提升可读性
 */
class ChineseActions extends Actions
{
    /**
     * 查看按钮 - 中文标签
     */
    protected function getViewLabel()
    {
        $label = trans('admin.show');

        return "<i class=\"feather icon-eye grid-action-icon\"></i> {$label}";
    }

    /**
     * 编辑按钮 - 中文标签
     */
    protected function getEditLabel()
    {
        $label = trans('admin.edit');

        return "<i class=\"feather icon-edit-1 grid-action-icon\"></i> {$label}";
    }

    /**
     * 快捷编辑按钮 - 中文标签
     */
    protected function getQuickEditLabel()
    {
        $label = trans('admin.quick_edit');

        return "<i class=\"feather icon-edit grid-action-icon\"></i> {$label}";
    }

    /**
     * 删除按钮 - 中文标签
     */
    protected function getDeleteLabel()
    {
        $label = trans('admin.delete');

        return "<i class=\"feather icon-trash grid-action-icon\"></i> {$label}";
    }
}
