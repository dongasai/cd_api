<?php

namespace App\Admin\Controllers;

use App\Models\ChannelRequestLog;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

/**
 * 渠道请求日志控制器
 *
 * 只读控制器，用于查看渠道请求日志
 */
class ChannelRequestLogController extends AdminController
{
    /**
     * 当前控制器对应的模型
     *
     * @var string
     */
    protected $title = '渠道请求日志';

    /**
     * 列表页
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(ChannelRequestLog::with(['auditLog', 'requestLog', 'channel']), function (Grid $grid) {
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
            $grid->column('channel_name', '渠道名称');
            $grid->column('provider', '提供商');
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
            $grid->column('response_status', '响应状态')->display(function ($value) {
                if ($value === null) {
                    return '-';
                }
                // 根据状态码设置颜色
                $color = 'default';
                if ($value >= 200 && $value < 300) {
                    $color = 'success';
                } elseif ($value >= 400 && $value < 500) {
                    $color = 'warning';
                } elseif ($value >= 500) {
                    $color = 'danger';
                }

                return "<span class='label label-{$color}'>{$value}</span>";
            });
            $grid->column('latency_ms', '延迟(ms)')->display(function ($value) {
                if ($value === null) {
                    return '-';
                }

                return number_format($value, 2);
            })->sortable();
            $grid->column('is_success', '是否成功')->using([
                0 => '失败',
                1 => '成功',
            ])->label([
                0 => 'danger',
                1 => 'success',
            ]);
            $grid->column('created_at', '创建时间')->sortable();

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                // 不使用抽屉模式，直接展开
                $filter->panel();
                $filter->expand(true);

                $filter->equal('id', 'ID');
                $filter->equal('request_id', '请求ID');
                $filter->like('channel_name', '渠道名称');
                $filter->like('provider', '提供商');
                $filter->equal('method', '请求方法')->select([
                    'GET' => 'GET',
                    'POST' => 'POST',
                    'PUT' => 'PUT',
                    'DELETE' => 'DELETE',
                    'PATCH' => 'PATCH',
                ]);
                $filter->equal('response_status', '响应状态');
                $filter->equal('is_success', '是否成功')->select([
                    0 => '失败',
                    1 => '成功',
                ]);
                $filter->between('created_at', '创建时间')->datetime();
            });

            // 默认排序
            $grid->model()->orderBy('id', 'desc');

            // 快速搜索
            $grid->quickSearch(['id', 'request_id', 'channel_name', 'provider', 'path']);
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
        return Show::make($id, ChannelRequestLog::with(['auditLog', 'requestLog']), function (Show $show) {
            // 基本信息
            $show->field('id', 'ID');
            $show->field('audit_log_id', '审计日志ID');
            $show->field('request_log_id', '请求日志ID');
            $show->field('request_id', '请求ID');

            // 渠道信息
            $show->field('channel_id', '渠道ID');
            $show->field('channel_name', '渠道名称');
            $show->field('provider', '提供商');

            // 请求信息
            $show->field('method', '请求方法');
            $show->field('path', '请求路径');
            $show->field('base_url', '基础URL');
            $show->field('full_url', '完整URL');

            // JSON 字段格式化显示
            $show->field('request_headers', '请求头')->json();

            $show->field('request_body', '请求体')->json_view_link();

            $show->field('request_size', '请求大小')->display(function ($value) {
                if ($value === null) {
                    return '-';
                }

                return $this->formatFileSize($value);
            });

            // 响应信息
            $show->field('response_status', '响应状态');
            // 使用链接跳转到独立页面查看
            // $show->field('response_headers', '响应头')->as(function ($value) {
            //     if (empty($value)) {
            //         return '-';
            //     }

            //     $url = admin_url('json-preview/channel-request-logs/'.$this->id.'/response_headers');

            //     return '<a href="'.$url.'" class="btn btn-sm btn-primary"><i class="fa fa-eye"></i> 查看JSON</a>';
            // })->unescape();

            // $show->field('response_body', '响应体')->as(function ($value) {
            //     if (empty($value)) {
            //         return '-';
            //     }

            //     $url = admin_url('json-preview/channel-request-logs/'.$this->id.'/response_body');

            //     return '<a href="'.$url.'" class="btn btn-sm btn-primary"><i class="fa fa-eye"></i> 查看JSON</a>';
            // })->unescape();

            // $show->field('response_size', '响应大小')->display(function ($value) {
            //     if ($value === null) {
            //         return '-';
            //     }

            //     return $this->formatFileSize($value);
            // });

            // 性能指标
            // $show->field('latency_ms', '延迟(ms)')->as(function ($value) {
            //     if ($value === null) {
            //         return '-';
            //     }

            //     return number_format($value, 2);
            // });
            // $show->field('ttfb_ms', '首字节时间(ms)')->as(function ($value) {
            //     if ($value === null) {
            //         return '-';
            //     }

            //     return number_format($value, 2);
            // });

            // 状态信息
            $show->field('is_success', '是否成功')->using([
                0 => '失败',
                1 => '成功',
            ]);
            $show->field('error_type', '错误类型');
            $show->field('error_message', '错误消息');

            // 使用链接跳转到独立页面查看
            // JSON 字段格式化显示
            // $show->field('usage', '使用量')->as(function ($value) {
            //     if (empty($value)) {
            //         return '-';
            //     }

            //     $url = admin_url('json-preview/channel-request-logs/'.$this->id.'/usage');

            //     return '<a href="'.$url.'" class="btn btn-sm btn-primary"><i class="fa fa-eye"></i> 查看JSON</a>';
            // })->unescape();

            // $show->field('metadata', '元数据')->as(function ($value) {
            //     if (empty($value)) {
            //         return '-';
            //     }

            //     $url = admin_url('json-preview/channel-request-logs/'.$this->id.'/metadata');

            //     return '<a href="'.$url.'" class="btn btn-sm btn-primary"><i class="fa fa-eye"></i> 查看JSON</a>';
            // })->unescape();

            // // 时间信息
            // $show->field('sent_at', '发送时间');
            // $show->field('created_at', '创建时间');
            // $show->field('updated_at', '更新时间');

            // // 禁用编辑和删除按钮
            // $show->disableEditButton();
            // $show->disableDeleteButton();

            // // 分组显示字段
            // $show->divider('基本信息');
            // $show->fields(['id', 'audit_log_id', 'request_log_id', 'request_id']);

            // $show->divider('渠道信息');
            // $show->fields(['channel_id', 'channel_name', 'provider']);

            // $show->divider('请求信息');
            // $show->fields(['method', 'path', 'base_url', 'full_url', 'request_headers', 'request_body', 'request_size']);

            // $show->divider('响应信息');
            // $show->fields(['response_status', 'response_headers', 'response_body', 'response_size']);

            // $show->divider('性能指标');
            // $show->fields(['latency_ms', 'ttfb_ms']);

            // $show->divider('状态信息');
            // $show->fields(['is_success', 'error_type', 'error_message']);

            // $show->divider('其他');
            // $show->fields(['usage', 'metadata', 'sent_at', 'created_at', 'updated_at']);
        });
    }

    /**
     * 格式化文件大小
     *
     * @param  int  $bytes
     * @return string
     */
    private function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;
        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        return round($bytes, 2).' '.$units[$unit];
    }
}
