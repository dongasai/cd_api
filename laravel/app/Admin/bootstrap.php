<?php

use App\Admin\Extensions\Grid\Displayers\CopyableValue;
use App\Admin\Extensions\Grid\Displayers\MultiFields;
use App\Admin\Extensions\ShowFieldMacro;
use Dcat\Admin\Admin;
use Dcat\Admin\Grid\Column;
use Dcat\Admin\Show\Field;

/**
 * Dcat Admin 扩展注册
 *
 * 在此文件中注册自定义的扩展功能
 */

// 注册 Grid Column 扩展方法
// 使用方式：
// - copyableValue(): $grid->column('display_field')->copyableValue('copy_field')
// - copyableValue(): $grid->column('display_field')->copyableValue(function() { return $this->id; })
// - multiFields(): $grid->column('info', '信息')->multiFields(['name', 'email'])
// - multiFields(): $grid->column('info', '信息')->multiFields(['name' => '姓名', 'email' => '邮箱'])
Column::extend('copyableValue', CopyableValue::class);
Column::extend('multiFields', MultiFields::class);

// 注册 Show Field 宏方法
// 使用方式：
// - json_view_link(): $show->field('field_name', '字段标签')->json_view_link()
// - json_view_iframe(): $show->field('field_name', '字段标签')->json_view_iframe($height = 400)
// - copyable(): $show->field('field_name', '字段标签')->copyable($buttonText = null, $successText = null)
// - sseChunks(): $show->field('field_name', '字段标签')->sseChunks($height = 600)
Field::mixin(new ShowFieldMacro);

/**
 * 自定义CSS样式 - 增强菜单激活项可见性
 */
Admin::style(<<<'CSS'
/* 菜单激活项增强样式 - 激活类在 .nav-link 上 */
.nav-pills .nav-link.active,
.nav-sidebar .nav-link.active {
    background-color: rgba(255, 255, 255, 0.2) !important;
    font-weight: 600 !important;
    box-shadow: inset 3px 0 0 rgba(255, 255, 255, 0.8) !important;
}

/* 子菜单激活项 */
.nav-treeview .nav-link.active {
    background-color: rgba(255, 255, 255, 0.15) !important;
    font-weight: 500 !important;
    box-shadow: inset 2px 0 0 rgba(255, 255, 255, 0.6) !important;
}

/* 菜单悬停效果增强 */
.nav-pills .nav-link:hover,
.nav-sidebar .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
}
CSS);
