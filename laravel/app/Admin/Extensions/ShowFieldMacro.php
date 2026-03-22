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

    public function json_view()
    {
        return $this->json_view_iframe();
    }

    /**
     * JSON 字段 iframe 嵌入显示
     *
     * 用于在详情页直接嵌入 iframe 显示 JSON 数据
     *
     * @return Field
     */
    public function json_view_iframe()
    {
        return function ($height = 400) {
            // $this 是 Field 对象
            $field = $this;

            return $this->unescape()->as(function ($value) use ($field, $height) {
                // 如果值为空，显示占位符
                if (empty($value)) {
                    return '<span class="text-muted">-</span>';
                }

                // $this 在闭包中是模型对象
                $model = $this;

                // 获取字段名
                $fieldName = $field->getName();

                // 获取表名并转换为路由格式
                $tableName = $model->getTable();
                $routeName = str_replace('_', '-', $tableName);

                // 获取记录 ID
                $id = $model->getKey();

                // 生成 JSON 预览 URL（使用 embed 路由）
                $url = admin_url("json-preview-embed/{$routeName}/{$id}/{$fieldName}");

                // 返回 iframe
                return '<iframe src="'.$url.'" '.
                       'style="width: 100%; height: '.$height.'px; border: 1px solid #dee2e6; border-radius: 4px;" '.
                       'frameborder="0"></iframe>';
            });
        };
    }

    /**
     * 可复制内容显示
     *
     * 用于在详情页显示可一键复制的内容，支持自定义提示文字
     *
     * @return Field
     */
    public function copyable()
    {
        return function ($buttonText = null, $successText = null) {
            return $this->as(function ($value) use ($buttonText, $successText) {
                // 如果值为空，显示占位符
                if (empty($value)) {
                    return '<span class="text-muted">-</span>';
                }

                // 默认按钮文字和成功提示
                $buttonText = $buttonText ?? '复制';
                $successText = $successText ?? '已复制到剪贴板';

                // 生成唯一 ID
                $uniqueId = 'copyable-'.uniqid();

                // 转义内容
                $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

                // 使用视图渲染
                return view('admin.components.copyable', [
                    'value' => $escapedValue,
                    'uniqueId' => $uniqueId,
                    'buttonText' => $buttonText,
                    'successText' => $successText,
                ])->render();
            })->unescape();
        };
    }

    /**
     * SSE Chunks 流式响应展示
     *
     * 专用于展示 generated_chunks 字段中的 SSE 流式响应数据
     * 使用 iframe 嵌入独立页面进行渲染
     *
     * @return Field
     */
    public function sseChunks()
    {
        return function ($height = 600) {
            // $this 是 Field 对象
            $field = $this;

            return $this->unescape()->as(function ($value) use ($field, $height) {
                // 如果值为空，显示占位符
                if (empty($value)) {
                    return '<span class="text-muted">-</span>';
                }

                // $this 在闭包中是模型对象
                $model = $this;

                // 获取字段名
                $fieldName = $field->getName();

                // 获取表名并转换为路由格式
                $tableName = $model->getTable();
                $routeName = str_replace('_', '-', $tableName);

                // 获取记录 ID
                $id = $model->getKey();

                // 生成 SSE Chunks 预览 URL
                $url = admin_url("sse-chunks-embed/{$routeName}/{$id}/{$fieldName}");

                // 返回 iframe
                return '<iframe src="'.$url.'" '.
                       'style="width: 100%; height: '.$height.'px; border: 1px solid #dee2e6; border-radius: 4px;" '.
                       'frameborder="0"></iframe>';
            });
        };
    }

    /**
     * 消息列表预览
     *
     * 专用于展示 messages 字段中的聊天消息列表
     * 使用 iframe 嵌入独立页面进行渲染
     * AI 消息显示在左边，用户消息显示在右边
     *
     * @return Field
     */
    public function messagesList()
    {
        return function ($height = 500) {
            // $this 是 Field 对象
            $field = $this;

            return $this->unescape()->as(function ($value) use ($field, $height) {
                // 如果值为空，显示占位符
                if (empty($value)) {
                    return '<span class="text-muted">-</span>';
                }

                // $this 在闭包中是模型对象
                $model = $this;

                // 获取字段名
                $fieldName = $field->getName();

                // 获取表名并转换为路由格式
                $tableName = $model->getTable();
                $routeName = str_replace('_', '-', $tableName);

                // 获取记录 ID
                $id = $model->getKey();

                // 生成消息列表预览 URL
                $url = admin_url("messages-list-embed/{$routeName}/{$id}/{$fieldName}");

                // 返回 iframe
                return '<iframe src="'.$url.'" '.
                       'style="width: 100%; height: '.$height.'px; border: 1px solid #dee2e6; border-radius: 4px;" '.
                       'frameborder="0"></iframe>';
            });
        };
    }

    /**
     * 格式化 JSON 数据为紧凑的可读字符串
     *
     * @param  mixed  $data
     * @return string
     */
    private function formatJson($data)
    {
        if (is_string($data)) {
            return htmlspecialchars($data);
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // 如果太长，截断显示
        if (strlen($json) > 200) {
            return htmlspecialchars(substr($json, 0, 200)).' <span style="color: #5c6370;">...</span>';
        }

        return htmlspecialchars($json);
    }
}
