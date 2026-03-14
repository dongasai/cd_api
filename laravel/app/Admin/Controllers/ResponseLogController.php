<?php

namespace App\Admin\Controllers;

use App\Models\ResponseLog;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

/**
 * 响应日志控制器
 *
 * 只读控制器，用于查看响应日志
 */
class ResponseLogController extends AdminController
{
    /**
     * 当前控制器对应的模型
     *
     * @var string
     */
    protected $title = '响应日志';

    /**
     * 列表页
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(ResponseLog::with(['auditLog', 'requestLog']), function (Grid $grid) {
            // 禁用创建、编辑、删除操作
            $grid->disableCreateButton();
            $grid->disableEditButton();
            $grid->disableDeleteButton();
            $grid->disableViewButton();

            // 禁用批量删除
            $grid->disableBatchDelete();

            // 列表字段
            $grid->column('id', 'ID')->sortable();
            $grid->column('request_id', '请求ID')->copyable();
            $grid->column('status_code', '状态码')->display(function ($value) {
                if ($value >= 200 && $value < 300) {
                    return "<span class='label label-success'>$value</span>";
                } elseif ($value >= 400 && $value < 500) {
                    return "<span class='label label-warning'>$value</span>";
                } elseif ($value >= 500) {
                    return "<span class='label label-danger'>$value</span>";
                }

                return $value;
            });
            $grid->column('content_type', '内容类型')->limit(30);
            $grid->column('content_length', '内容长度')->display(function ($value) {
                if ($value === null) {
                    return '-';
                }
                // 格式化文件大小
                $units = ['B', 'KB', 'MB', 'GB'];
                $unit = 0;
                while ($value >= 1024 && $unit < count($units) - 1) {
                    $value /= 1024;
                    $unit++;
                }

                return round($value, 2).' '.$units[$unit];
            });
            $grid->column('upstream_model', '上游模型');
            $grid->column('finish_reason', '完成原因');
            $grid->column('created_at', '创建时间')->sortable();

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                // 不使用抽屉模式，直接展开
                $filter->panel();
                $filter->expand(true);

                $filter->equal('id', 'ID');
                $filter->equal('request_id', '请求ID');
                $filter->equal('status_code', '状态码');
                $filter->like('upstream_provider', '上游提供商');
                $filter->like('upstream_model', '上游模型');
                $filter->equal('finish_reason', '完成原因')->select([
                    'stop' => 'stop',
                    'length' => 'length',
                    'content_filter' => 'content_filter',
                    'null' => 'null',
                    'error' => 'error',
                ]);
                $filter->between('created_at', '创建时间')->datetime();
            });

            // 默认排序
            $grid->model()->orderBy('id', 'desc');

            // 快速搜索
            $grid->quickSearch(['id', 'request_id', 'upstream_model', 'finish_reason']);
        });
    }

    /**
     * 详情页
     *
     * @param  int  $id
     * @return Show
     */
    protected function detail($id)
    {
        return Show::make($id, ResponseLog::with(['auditLog', 'requestLog']), function (Show $show) {
            // 基本信息
            $show->field('id', 'ID');
            $show->field('audit_log_id', '审计日志ID');
            $show->field('request_id', '请求ID');
            $show->field('request_log_id', '请求日志ID');
            $show->field('created_at', '创建时间');

            // // 响应状态信息
            $show->field('status_code', '状态码');
            $show->field('status_message', '状态消息');
            $show->field('content_type', '内容类型');
            $show->field('content_length', '内容长度')->as(function ($value) {
                if ($value === null) {
                    return '-';
                }
                // 格式化文件大小
                $units = ['B', 'KB', 'MB', 'GB'];
                $unit = 0;
                while ($value >= 1024 && $unit < count($units) - 1) {
                    $value /= 1024;
                    $unit++;
                }
                // \dd($units);

                return round($value, 2).' '.$units[$unit];
            });

            // // 响应类型
            $show->field('response_type', '响应类型')->using(ResponseLog::getResponseTypes());

            // 响应头使用代码高亮显示
            $show->field('headers', '响应头')->json();

            // 响应体使用代码高亮
            $show->field('body_text', '响应体')->json();

            // 二进制数据不直接显示
            $show->field('body_binary', '二进制数据')->as(function ($value) {
                return $value ? '已存储 ('.strlen($value).' bytes)' : '无';
            });

            // 生成文本使用代码高亮
            $show->field('generated_text', '生成文本')->as(function ($value) {
                if (empty($value)) {
                    return '-';
                }

                return $value;
            })->unescape();

            // 生成块使用代码高亮显示
            // $show->field('generated_chunks', '生成块')->as(function ($value) {
            //     if (empty($value)) {
            //         return '-';
            //     }

            //     return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            // })->unescape();

            // 完成原因
            // $show->field('finish_reason', '完成原因');

            // // 使用情况使用代码高亮显示
            // $show->field('usage', '使用情况')->as(function ($value) {
            //     if (empty($value)) {
            //         return '-';
            //     }

            //     return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            // })->unescape();

            // // 错误信息
            // $show->field('error_type', '错误类型');
            // $show->field('error_code', '错误代码');
            // $show->field('error_message', '错误消息');
            // $show->field('error_details', '错误详情')->as(function ($value) {
            //     if (empty($value)) {
            //         return '-';
            //     }

            //     return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            // })->unescape();

            // // 上游信息
            // $show->field('upstream_provider', '上游提供商');
            // $show->field('upstream_model', '上游模型');
            // $show->field('upstream_latency_ms', '上游延迟(ms)')->display(function ($value) {
            //     return $value ? number_format($value) : '-';
            // });

            // // 元数据使用代码高亮显示
            // // $show->field('metadata', '元数据')->as(function ($value) {
            // //     if (empty($value)) {
            // //         return '-';
            // //     }

            // //     return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            // // })->unescape();

            // // 禁用编辑和删除按钮
            // $show->disableEditButton();
            // $show->disableDeleteButton();

            // // 分组显示字段
            // $show->divider('基本信息');
            // $show->fields(['id', 'audit_log_id', 'request_id', 'request_log_id', 'created_at']);

            // $show->divider('响应状态');
            // $show->fields(['status_code', 'status_message', 'content_type', 'content_length', 'response_type', 'headers']);

            // $show->divider('响应内容');
            // $show->fields(['body_text', 'body_binary']);

            // $show->divider('生成内容');
            // $show->fields(['generated_text', 'generated_chunks', 'finish_reason']);

            // $show->divider('使用情况');
            // $show->fields(['usage']);

            // $show->divider('错误信息');
            // $show->fields(['error_type', 'error_code', 'error_message', 'error_details']);

            // $show->divider('上游信息');
            // $show->fields(['upstream_provider', 'upstream_model', 'upstream_latency_ms']);

            // $show->divider('其他');
            // $show->fields(['metadata']);
        });
    }
}
