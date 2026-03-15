<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Layout\Content;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * JSON 预览控制器
 *
 * 用于友好地展示模型中的 JSON 字段数据
 */
class JsonPreviewController extends Controller
{
    /**
     * 预览 JSON 数据（完整页面，带侧边栏）
     *
     * @param  string  $table  表名/路由名（如 channel-request-logs）
     * @param  int  $id  主键ID
     * @param  string  $field  字段名
     */
    public function show(string $table, int $id, string $field, Content $content)
    {
        // 将路由名转换为模型名
        $model = $this->getModelNameFromTable($table);

        // 获取模型类名
        $modelClass = $this->getModelClass($model);

        if (! $modelClass) {
            abort(404, "模型 {$model} 不存在");
        }

        // 查找模型实例
        $instance = $modelClass::find($id);

        if (! $instance) {
            abort(404, '记录不存在');
        }

        // 检查字段是否存在
        if (! isset($instance->$field) && ! Schema::hasColumn($instance->getTable(), $field)) {
            abort(404, "字段 {$field} 不存在");
        }

        // 获取字段值
        $value = $instance->$field;

        // 如果是字符串，尝试解析为 JSON
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        // 格式化 JSON
        $jsonString = is_array($value) || is_object($value)
            ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : (string) $value;

        // 获取模型的显示名称
        $modelTitle = $this->getModelTitle($model);

        return $content
            ->title("{$modelTitle} - {$field}")
            ->description("ID: {$id}")
            ->body(view('admin.json-preview', [
                'table' => $table,
                'model' => $model,
                'modelTitle' => $modelTitle,
                'id' => $id,
                'field' => $field,
                'fieldLabel' => $this->getFieldLabel($field),
                'jsonString' => $jsonString,
                'backUrl' => admin_url($table.'/'.$id),
            ]));
    }

    /**
     * 预览 JSON 数据（纯内容，无侧边栏）
     *
     * 用于 iframe 嵌入场景
     *
     * @param  string  $table  表名/路由名（如 channel-request-logs）
     * @param  int  $id  主键ID
     * @param  string  $field  字段名
     */
    public function embed(string $table, int $id, string $field)
    {
        // 将路由名转换为模型名
        $model = $this->getModelNameFromTable($table);

        // 获取模型类名
        $modelClass = $this->getModelClass($model);

        if (! $modelClass) {
            abort(404, "模型 {$model} 不存在");
        }

        // 查找模型实例
        $instance = $modelClass::find($id);

        if (! $instance) {
            abort(404, '记录不存在');
        }

        // 检查字段是否存在
        if (! isset($instance->$field) && ! Schema::hasColumn($instance->getTable(), $field)) {
            abort(404, "字段 {$field} 不存在");
        }

        // 获取字段值
        $value = $instance->$field;

        // 如果是字符串，尝试解析为 JSON
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        // 存储原始数据用于复制
        $originalData = $value;

        // 格式化 JSON 用于显示
        $jsonString = is_array($value) || is_object($value)
            ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : (string) $value;

        // 获取模型的显示名称
        $modelTitle = $this->getModelTitle($model);

        return view('admin.json-preview-embed', [
            'table' => $table,
            'model' => $model,
            'modelTitle' => $modelTitle,
            'id' => $id,
            'field' => $field,
            'fieldLabel' => $this->getFieldLabel($field),
            'jsonString' => $jsonString,
            'originalData' => $originalData,
        ]);
    }

    /**
     * SSE Chunks 预览（纯内容，无侧边栏）
     *
     * 专用于 iframe 嵌入展示 generated_chunks 字段
     *
     * @param  string  $table  表名/路由名（如 response-logs）
     * @param  int  $id  主键ID
     * @param  string  $field  字段名
     */
    public function sseChunksEmbed(string $table, int $id, string $field)
    {
        // 将路由名转换为模型名
        $model = $this->getModelNameFromTable($table);

        // 获取模型类名
        $modelClass = $this->getModelClass($model);

        if (! $modelClass) {
            abort(404, "模型 {$model} 不存在");
        }

        // 查找模型实例
        $instance = $modelClass::find($id);

        if (! $instance) {
            abort(404, '记录不存在');
        }

        // 检查字段是否存在
        if (! isset($instance->$field) && ! Schema::hasColumn($instance->getTable(), $field)) {
            abort(404, "字段 {$field} 不存在");
        }

        // 获取字段值
        $chunks = $instance->$field;

        // 如果是字符串，尝试解析为 JSON
        if (is_string($chunks)) {
            $decoded = json_decode($chunks, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $chunks = $decoded;
            }
        }

        // 如果不是数组或为空，显示错误
        if (! is_array($chunks) || empty($chunks)) {
            return view('admin.sse-chunks-embed', [
                'totalChunks' => 0,
                'eventTypes' => [],
                'chunks' => [],
                'fieldLabel' => $this->getFieldLabel($field),
            ]);
        }

        // 解析 SSE 事件
        $parseSSEEvent = function ($chunk) {
            if (empty($chunk)) {
                return null;
            }

            $event = [];

            // 匹配 event: xxx
            if (preg_match('/event:\s*(.+)/', $chunk, $matches)) {
                $event['event'] = trim($matches[1]);
            }

            // 匹配 data: {...}
            if (preg_match('/data:\s*(.+)/s', $chunk, $matches)) {
                $dataStr = trim($matches[1]);
                // 尝试解析 JSON
                $jsonData = json_decode($dataStr, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $event['data'] = $jsonData;
                } else {
                    $event['data'] = $dataStr;
                }
            }

            return ! empty($event) ? $event : null;
        };

        // 解析事件类型统计
        $eventTypes = [];
        $parsedChunks = [];
        foreach ($chunks as $chunk) {
            $event = $parseSSEEvent($chunk);
            if ($event && isset($event['event'])) {
                $eventType = $event['event'];
                $eventTypes[$eventType] = ($eventTypes[$eventType] ?? 0) + 1;
            }
            $parsedChunks[] = [
                'raw' => $chunk,
                'parsed' => $event,
            ];
        }

        return view('admin.sse-chunks-embed', [
            'totalChunks' => count($chunks),
            'eventTypes' => $eventTypes,
            'chunks' => $parsedChunks,
            'fieldLabel' => $this->getFieldLabel($field),
        ]);
    }

    /**
     * 将路由名转换为模型名
     */
    protected function getModelNameFromTable(string $table): string
    {
        // channel-request-logs -> ChannelRequestLog
        return Str::singular(Str::studly($table));
    }

    /**
     * 获取模型类名
     */
    protected function getModelClass(string $model): ?string
    {
        // 支持短名称和完整类名
        if (class_exists($model)) {
            return $model;
        }

        // 尝试在 App\Models 命名空间下查找
        $modelClass = 'App\\Models\\'.$model;
        if (class_exists($modelClass)) {
            return $modelClass;
        }

        // 尝试将下划线命名转换为驼峰命名
        $studlyModel = Str::studly($model);
        $modelClass = 'App\\Models\\'.$studlyModel;
        if (class_exists($modelClass)) {
            return $modelClass;
        }

        return null;
    }

    /**
     * 获取模型标题
     */
    protected function getModelTitle(string $model): string
    {
        $titles = [
            'ChannelRequestLog' => '渠道请求日志',
            'RequestLog' => '请求日志',
            'AuditLog' => '审计日志',
            'ResponseLog' => '响应日志',
        ];

        return $titles[$model] ?? Str::title(Str::snake($model, ' '));
    }

    /**
     * 获取字段标签
     */
    protected function getFieldLabel(string $field): string
    {
        $labels = [
            'request_headers' => '请求头',
            'response_headers' => '响应头',
            'request_body' => '请求体',
            'response_body' => '响应体',
            'usage' => '使用量',
            'metadata' => '元数据',
            'config' => '配置',
        ];

        return $labels[$field] ?? Str::title(Str::snake($field, ' '));
    }
}
