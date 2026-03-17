<?php

use App\Admin\Extensions\Grid\Displayers\CopyableValue;
use App\Admin\Extensions\Grid\Displayers\MultiFields;
use App\Admin\Extensions\ShowFieldMacro;
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
