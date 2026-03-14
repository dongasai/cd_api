<?php

use App\Admin\Extensions\ShowFieldMacro;
use Dcat\Admin\Show\Field;

/**
 * Dcat Admin 扩展注册
 *
 * 在此文件中注册自定义的扩展功能
 */

// 注册 Show Field 宏方法
// 使用方式：
// - json_view_link(): $show->field('field_name', '字段标签')->json_view_link()
// - json_view_iframe(): $show->field('field_name', '字段标签')->json_view_iframe($height = 400)
// - copyable(): $show->field('field_name', '字段标签')->copyable($buttonText = null, $successText = null)
Field::mixin(new ShowFieldMacro);
