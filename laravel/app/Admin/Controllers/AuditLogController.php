<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\ViewAffinityHit;
use App\Admin\Actions\ViewChannelRequestLog;
use App\Admin\Actions\ViewRequestLog;
use App\Admin\Actions\ViewResponseLog;
use App\Models\AuditLog;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

/**
 * 审计日志控制器
 *
 * 用于查看API请求审计日志,只读模式
 */
class AuditLogController extends AdminController
{
    /**
     * 数据模型
     *
     * @var string
     */
    protected $model = AuditLog::class;

    /**
     * 禁用的操作
     *
     * @var array
     */
    protected $disableActions = ['create', 'update', 'delete'];

    /**
     * 列表页面
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(AuditLog::with(['channel', 'requestLog', 'responseLog', 'channelRequestLogs']), function (Grid $grid) {
            // 默认按创建时间倒序排序
            $grid->model()->orderBy('id', 'desc');

            // 列表字段
            $grid->column('id', 'ID')->sortable();
            $grid->column('api_key_and_affinity', 'API Key ')->display(function () {
                $apiKey = $this->api_key_name ?: '-';
                $affinity = $this->channel_affinity ?? [];
                $isHit = isset($affinity['is_affinity_hit']) && $affinity['is_affinity_hit'];

                if ($isHit) {
                    return "<span class='border border-success rounded px-1' title='渠道亲和命中'>{$apiKey}</span>";
                }

                return "<span title='渠道亲和未命中'>{$apiKey}</span>";
            });
            $grid->column('channel_name', '渠道名称');
            $grid->column('model_info', '模型')->display(function () {
                $model = $this->model ?? '-';
                $actual = $this->actual_model ?? '-';

                return "请求: {$model}<br>实际: {$actual}";
            });
            $grid->column('tokens', 'Token数')->display(function () {
                $total = number_format($this->total_tokens);
                $prompt = number_format($this->prompt_tokens);
                $completion = number_format($this->completion_tokens);

                $cacheInfo = '';
                if ($this->cache_read_tokens > 0 || $this->cache_write_tokens > 0) {
                    $cacheRead = number_format($this->cache_read_tokens);
                    $cacheWrite = number_format($this->cache_write_tokens);
                    $cacheInfo = "<br>缓存读: {$cacheRead} / 写: {$cacheWrite}";
                }

                return "总: {$total}<br>入: {$prompt} / 出: {$completion}{$cacheInfo}";
            });
            $grid->column('latency', '耗时(s)')->display(function () {
                $first = $this->first_token_ms ? round($this->first_token_ms / 1000, 2) : '-';
                $total = round($this->latency_ms / 1000, 2);

                return "首字: {$first} / 总计: {$total}";
            });
            $grid->column('status_code', '状态码')->display(function ($value) {
                if (is_null($value)) {
                    return '-';
                }
                if ($value >= 200 && $value < 300) {
                    return "<span class='badge bg-success'>$value</span>";
                }
                if ($value >= 400 && $value < 500) {
                    return "<span class='badge bg-warning'>$value</span>";
                }
                if ($value >= 500) {
                    return "<span class='badge bg-danger'>$value</span>";
                }

                return $value;
            });
            $grid->column('created_at', '创建时间')->sortable();

            // 筛选器
            $grid->filter(function ($filter) {
                // 不使用抽屉模式，直接展开
                $filter->panel();
                $filter->expand(true);

                // 用户名筛选
                $filter->like('username', '用户名');

                // 渠道名称筛选
                $filter->like('channel_name', '渠道名称');

                // 模型筛选
                $filter->like('model', '请求模型');

                // 状态码筛选
                $filter->equal('status_code', '状态码');

                // 创建时间范围筛选
                $filter->between('created_at', '创建时间')->datetime();
            });

            // 禁用创建按钮
            $grid->disableCreateButton();

            // 禁用编辑按钮
            $grid->disableEditButton();

            // 禁用删除按钮
            $grid->disableDeleteButton();

            // 禁用批量删除
            $grid->disableBatchDelete();

            // 启用详情按钮
            $grid->showViewButton();

            // 直接显示操作按钮（不使用下拉菜单）
            $grid->setActionClass(\Dcat\Admin\Grid\Displayers\Actions::class);

            // 添加自定义行操作按钮
            $grid->actions(function (Grid\Displayers\Actions $actions) {
                $actions->append(new ViewRequestLog);
                $actions->append(new ViewChannelRequestLog);
                $actions->append(new ViewResponseLog);
                $actions->append(new ViewAffinityHit);
            });

            // 设置每页显示行数
            $grid->paginate(20);

            // 显示横向滚动条
            $grid->scrollbarX();
        });
    }

    /**
     * 详情页面
     *
     * @param  mixed  $id
     * @return Show
     */
    protected function detail($id)
    {
        return Show::make(AuditLog::with(['user', 'channel', 'apiKey'])->findOrFail($id), function (Show $show) {
            // 基本信息
            $show->field('id', 'ID');
            $show->field('user_id', '用户ID');
            $show->field('username', '用户名');
            $show->field('api_key_id', 'API Key ID');
            $show->field('api_key_name', 'API Key名称');
            $show->field('cached_key_prefix', '缓存Key前缀');

            // 渠道信息
            $show->field('channel_id', '渠道ID');
            $show->field('channel_name', '渠道名称');

            // 请求信息
            $show->field('request_id', '请求ID');
            $show->field('run_unid', '运行UNID');
            $show->field('request_type', '请求类型')->using(AuditLog::getRequestTypes());
            $show->field('model', '请求模型');
            $show->field('actual_model', '实际模型');

            // Token信息
            $show->field('prompt_tokens', '提示Token数')->as(function ($value) {
                return number_format($value);
            });
            $show->field('completion_tokens', '完成Token数')->as(function ($value) {
                return number_format($value);
            });
            $show->field('total_tokens', '总Token数')->as(function ($value) {
                return number_format($value);
            });
            $show->field('cache_read_tokens', '缓存读取Token数')->as(function ($value) {
                return number_format($value);
            });
            $show->field('cache_write_tokens', '缓存写入Token数')->as(function ($value) {
                return number_format($value);
            });

            // 费用信息
            $show->field('cost', '费用')->as(function ($value) {
                return $value ? '$'.number_format($value, 6) : '-';
            });
            $show->field('quota', '配额')->as(function ($value) {
                return $value ? number_format($value, 6) : '-';
            });
            $show->field('billing_source', '计费来源')->using(AuditLog::getBillingSources());

            // 状态信息
            $show->field('status_code', '状态码');
            $show->field('latency_ms', '延迟(ms)')->as(function ($value) {
                return number_format($value);
            });
            $show->field('first_token_ms', '首Token延迟(ms)')->as(function ($value) {
                return $value ? number_format($value) : '-';
            });

            // 流式信息
            $show->field('is_stream', '是否流式')->using([0 => '否', 1 => '是']);
            $show->field('finish_reason', '完成原因');

            // 错误信息
            $show->field('error_type', '错误类型');
            $show->field('error_message', '错误信息');

            // 客户端信息
            $show->field('client_ip', '客户端IP');
            $show->field('user_agent', 'User Agent');
            $show->field('group_name', '分组名称');

            // 其他信息
            $show->field('channel_affinity', '渠道亲和性')->json();
            $show->field('metadata', '元数据')->json();

            // 时间信息
            $show->field('created_at', '创建时间');

            // 禁用编辑按钮
            $show->disableEditButton();

            // 禁用删除按钮
            $show->disableDeleteButton();
        });
    }

    /**
     * 禁用表单
     *
     * @return \Illuminate\Http\Response
     */
    protected function form()
    {
        // 只读模式,不提供表单
        abort(404);
    }
}
