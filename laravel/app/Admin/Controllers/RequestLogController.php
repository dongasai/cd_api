<?php

namespace App\Admin\Controllers;

use App\Models\RequestLog;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

/**
 * 请求日志控制器
 *
 * 只读控制器，用于查看请求日志
 */
class RequestLogController extends AdminController
{
    /**
     * 当前控制器对应的模型
     *
     * @var string
     */
    protected $title = '请求日志';

    /**
     * 列表页
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(RequestLog::with(['auditLog']), function (Grid $grid) {
            // 禁用创建、编辑、删除操作
            $grid->disableCreateButton();
            $grid->disableEditButton();
            $grid->disableDeleteButton();
            $grid->showViewButton();

            // 禁用批量删除
            $grid->disableBatchDelete();

            // 列表字段
            $grid->column('id', 'ID')->sortable();
            $grid->column('request_id', '请求ID')->copyable();
            $grid->column('channel_name', '渠道名称');
            $grid->column('method', '请求方法')->using([
                'GET' => 'GET',
                'POST' => 'POST',
                'PUT' => 'PUT',
                'DELETE' => 'DELETE',
                'PATCH' => 'PATCH',
            ])->label([
                'GET' => 'info',
                'POST' => 'success',
                'PUT' => 'warning',
                'DELETE' => 'danger',
                'PATCH' => 'primary',
            ]);
            $grid->column('path', '请求路径')->limit(50);
            $grid->column('model', '模型');
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
            $grid->column('created_at', '创建时间')->sortable();

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                // 不使用抽屉模式，直接展开
                $filter->panel();
                $filter->expand(true);

                $filter->equal('id', 'ID');
                $filter->equal('request_id', '请求ID');
                $filter->like('channel_name', '渠道名称');
                $filter->equal('method', '请求方法')->select([
                    'GET' => 'GET',
                    'POST' => 'POST',
                    'PUT' => 'PUT',
                    'DELETE' => 'DELETE',
                    'PATCH' => 'PATCH',
                ]);
                $filter->like('model', '模型');
                $filter->like('path', '请求路径');
                $filter->between('created_at', '创建时间')->datetime();
            });

            // 默认排序
            $grid->model()->orderBy('id', 'desc');

            // 快速搜索
            $grid->quickSearch(['id', 'request_id', 'channel_name', 'model', 'path']);
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
        return Show::make($id, RequestLog::with(['auditLog']), function (Show $show) {
            // 基本信息
            $show->field('id', 'ID');
            $show->field('audit_log_id', '审计日志ID');
            $show->field('request_id', '请求ID')->copyable();
            $show->field('run_unid', '运行UNID');
            $show->field('channel_id', '渠道ID');
            $show->field('channel_name', '渠道名称');
            $show->field('method', '请求方法');
            $show->field('path', '请求路径');
            $show->field('query_string', '查询字符串');
            $show->field('content_type', '内容类型');
            $show->field('content_length', '内容长度');
            $show->field('created_at', '创建时间');

            // JSON 字段使用代码高亮显示
            $show->field('headers', '请求头')->json();

            $show->field('model_params', '模型参数')->json();

            $show->field('messages', '消息列表')->messagesList();

            $show->field('metadata', '元数据')->json_view();

            // 模型相关字段
            $show->field('model', '请求模型');
            $show->field('upstream_model', '上游模型');
            $show->field('prompt', '提示词');

            // // 请求体使用代码高亮
            $show->field('body_text', '请求体')->json_view();

            // 敏感数据标记
            $show->field('has_sensitive', '包含敏感数据')->using([
                0 => '否',
                1 => '是',
            ]);

            // 禁用编辑和删除按钮
            $show->disableEditButton();
            $show->disableDeleteButton();

            // // 分组显示字段
            // $show->divider('基本信息');
            // $show->fields(['id', 'audit_log_id', 'request_id', 'run_unid', 'created_at']);

            $show->divider('渠道信息');
            $show->fields(['channel_id', 'channel_name']);

            // $show->divider('请求信息');
            // $show->fields(['method', 'path', 'query_string', 'headers', 'content_type', 'content_length']);

            // $show->divider('模型信息');
            // $show->fields(['model', 'upstream_model', 'model_params', 'messages', 'prompt']);

            // $show->divider('请求体');
            // $show->fields(['body_text', 'body_binary']);

            // $show->divider('敏感数据');
            // $show->fields(['sensitive_fields', 'has_sensitive']);

            // $show->divider('其他');
            // $show->fields(['metadata']);
        });
    }
}
