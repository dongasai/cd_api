<?php

use App\Admin\Extensions\ShowFieldMacro;
use Dcat\Admin\Show\Field;

/**
 * Dcat Admin 扩展注册
 *
 * 在此文件中注册自定义的扩展功能
 */

// 注册 Show Field 宏方法：json_view_link
// 使用方式：$show->field('field_name', '字段标签')->json_view_link()
// 参数：json_view_link($label = null, $icon = null, $newTab = true)
Field::mixin(new ShowFieldMacro);
