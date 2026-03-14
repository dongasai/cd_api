<?php

namespace App\Admin\Extensions;

use Dcat\Admin\Show\Field;

/**
 * Show Field 宏扩展
 *
 * 为 Dcat Admin Show Field 提供额外的显示方法
 */
class ShowFieldMacro
{
    /**
     * JSON 字段查看链接
     *
     * 用于在详情页生成跳转到 JsonPreview 页面的链接按钮
     *
     * @return Field
     */
    public function json_view_link()
    {
        return function ($label = null, $icon = null, $newTab = true) {
            // $this 是 Field 对象
            $field = $this;

            return $this->unescape()->as(function ($value) use ($field, $label, $icon, $newTab) {
                // 如果值为空，显示占位符
                if (empty($value)) {
                    return '<span class="text-muted">-</span>';
                }

                // $this 在闭包中是模型对象
                $model = $this;

                // 获取字段名
                $fieldName = $field->getName();

                // 获取表名并转换为路由格式
                // channel_request_logs -> channel-request-logs
                $tableName = $model->getTable();
                $routeName = str_replace('_', '-', $tableName);

                // 获取记录 ID
                $id = $model->getKey();

                // 生成 JSON 预览 URL
                $url = admin_url("json-preview/{$routeName}/{$id}/{$fieldName}");

                // 默认按钮文字和图标
                $label = $label ?? '查看JSON';
                $icon = $icon ?? 'fa fa-eye';

                // 新标签页属性
                $target = $newTab ? ' target="_blank"' : '';

                // 返回链接按钮
                return '<a href="'.$url.'" class="btn btn-sm btn-primary"'.$target.'>'.
                       '<i class="'.$icon.'"></i> '.$label.
                       '</a>';
            });
        };
    }
}