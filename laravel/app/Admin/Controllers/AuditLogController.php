<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\CompareRequestDiff;
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
    public $translation = 'admin-audit-log';

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
     */
    protected function grid(): Grid
    {
        return Grid::make(AuditLog::with(['channel', 'requestLog', 'responseLog', 'channelRequestLogs']), function (Grid $grid) {
            // 默认按创建时间倒序排序
            $grid->model()->orderBy('id', 'desc');

            // 列表字段
            $grid->column('id')->sortable();
            $grid->column('api_key_and_affinity')->display(function () {
                $apiKey = $this->api_key_name ?: '-';
                $apiKeyId = $this->api_key_id ?? 0;
                $affinity = $this->channel_affinity ?? [];
                $isHit = isset($affinity['is_affinity_hit']) && $affinity['is_affinity_hit'];

                // 根据不同的 API Key ID 分配不同的边框颜色
                $colors = [
                    1 => 'border-primary',      // 蓝色
                    2 => 'border-secondary',    // 灰色
                    3 => 'border-success',      // 绿色
                    4 => 'border-danger',       // 红色
                    5 => 'border-warning',      // 黄色
                    6 => 'border-info',         // 青色
                    7 => 'border-dark',         // 深色
                    8 => 'border-primary',      // 循环使用
                ];

                $borderColor = $colors[$apiKeyId] ?? 'border-secondary';

                // 亲和性命中时右上角添加星号
                if ($isHit) {
                    return "<span class='border {$borderColor} rounded px-2 position-relative d-inline-block' title='".admin_trans_label('affinity_hit')."'>{$apiKey}<span class='position-absolute' style='top:-8px;right:-8px;font-size:14px;'>⭐</span></span>";
                }

                return "<span class='border {$borderColor} rounded px-2' title='".admin_trans_label('affinity_not_hit')."'>{$apiKey}</span>";
            });
            $grid->column('channel_protocol', admin_trans_field('channel_protocol'))->display(function () {
                $channelName = $this->channel_name ?? '-';

                $source = $this->source_protocol ?? '-';
                $target = $this->target_protocol ?? '-';

                // 协议徽章颜色
                $sourceBadge = $source === 'anthropic'
                    ? "<span class='badge bg-info'>{$source}</span>"
                    : "<span class='badge bg-primary'>{$source}</span>";
                $targetBadge = $target === 'anthropic'
                    ? "<span class='badge bg-info'>{$target}</span>"
                    : "<span class='badge bg-primary'>{$target}</span>";

                return "<strong>{$channelName}</strong><br>".admin_trans_label('request').": {$sourceBadge}<br>".admin_trans_label('upstream').": {$targetBadge}";
            });
            $grid->column('model_info', admin_trans_field('model_info'))->display(function () {
                $model = $this->model ?? '-';
                $actual = $this->actual_model ?? '-';

                // 解析 apply_data（模型流转过程数据）
                $applyData = $this->apply_data;
                $matchedModels = [];
                $channelRequestModel = '-';

                if ($applyData && is_array($applyData)) {
                    // 别名扩展结果（参与匹配的模型列表）
                    $matchedModels = $applyData['matched_models'] ?? [];
                    // 渠道请求模型（发送给渠道的模型名）
                    $channelRequestModel = $applyData['channel_request_model'] ?? '-';
                }

                // 构建显示内容（限制列宽度，自动换行）
                $display = "<div style='max-width: 350px; word-wrap: break-word;'>";
                $display .= admin_trans_label('request').": <strong>{$model}</strong><br>";

                // 渠道请求模型（如果有）
                if ($channelRequestModel !== '-') {
                    $display .= admin_trans_label('channel_request_model').": <span class='text-warning'>{$channelRequestModel}</span><br>";
                }

                // 渠道响应模型（实际模型）
                $display .= admin_trans_field('actual_model').": <span class='text-success'>{$actual}</span><br>";

                // 别名扩展（如果有，放在最底部自动换行）
                if (! empty($matchedModels)) {
                    $matchedList = implode('<br>', $matchedModels);  // 使用换行分隔
                    $display .= "<span class='text-muted small'>".admin_trans_label('matched_models').':</span><br>';
                    $display .= "<span class='text-info small' style='line-height: 1.4;'>{$matchedList}</span>";
                }

                $display .= '</div>';

                return $display;
            });
            $grid->column('tokens', admin_trans_field('tokens'))->display(function () {
                $total = number_format($this->total_tokens);
                $prompt = number_format($this->prompt_tokens);
                $completion = number_format($this->completion_tokens);

                $cacheInfo = '';
                if ($this->cache_read_tokens > 0 || $this->cache_write_tokens > 0) {
                    $cacheRead = number_format($this->cache_read_tokens);
                    $cacheWrite = number_format($this->cache_write_tokens);
                    $cacheInfo = '<br>'.admin_trans_label('cache_read').": {$cacheRead} / ".admin_trans_label('cache_write').": {$cacheWrite}";
                }

                return admin_trans_label('total').": {$total}<br>".admin_trans_label('input').": {$prompt} / ".admin_trans_label('output').": {$completion}{$cacheInfo}";
            });
            $grid->column('latency', admin_trans_field('latency'))->display(function () {
                $first = $this->first_token_ms ? round($this->first_token_ms / 1000, 2) : '-';
                $total = round($this->latency_ms / 1000, 2);

                return admin_trans_label('first_token').": {$first} / ".admin_trans_label('total_time').": {$total}";
            });
            $grid->column('status_stream', admin_trans_field('status_stream'))->display(function () {
                $statusCode = $this->status_code;
                $isStream = $this->is_stream;

                // 状态码徽章
                $statusBadge = '-';
                if (! is_null($statusCode)) {
                    if ($statusCode >= 200 && $statusCode < 300) {
                        $statusBadge = "<span class='badge bg-success'>{$statusCode}</span>";
                    } elseif ($statusCode >= 400 && $statusCode < 500) {
                        $statusBadge = "<span class='badge bg-warning'>{$statusCode}</span>";
                    } elseif ($statusCode >= 500) {
                        $statusBadge = "<span class='badge bg-danger'>{$statusCode}</span>";
                    } else {
                        $statusBadge = $statusCode;
                    }
                }

                // 流式徽章
                $streamBadge = $isStream
                    ? "<span class='badge bg-primary'>".admin_trans_label('stream').'</span>'
                    : "<span class='badge bg-secondary'>".admin_trans_label('non_stream').'</span>';

                return "{$statusBadge} {$streamBadge}";
            });
            $grid->column('created_at')->sortable();

            // 筛选器
            $grid->filter(function ($filter) {
                // 不使用抽屉模式，直接展开
                $filter->panel();
                $filter->expand(true);

                // 用户名筛选
                $filter->like('username');

                // 渠道名称筛选
                $filter->like('channel_name');

                // 模型筛选
                $filter->like('model');

                // 状态码筛选
                $filter->equal('status_code');

                // 创建时间范围筛选
                $filter->between('created_at')->datetime();
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
                $actions->append(new CompareRequestDiff);
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
     */
    protected function detail($id): Show
    {
        return Show::make(AuditLog::with(['user', 'channel', 'apiKey'])->findOrFail($id), function (Show $show) {
            // 基本信息
            $show->field('id');
            $show->field('user_id');
            $show->field('username');
            $show->field('api_key_id');
            $show->field('api_key_name');
            $show->field('cached_key_prefix');

            // 渠道信息
            $show->field('channel_id');
            $show->field('channel_name');

            // 请求信息
            $show->field('request_id');
            $show->field('run_unid');
            $show->field('request_type')->using(AuditLog::getRequestTypes());
            $show->field('model');
            $show->field('actual_model');
            $show->field('apply_data')->as(function ($value) {
                if (! $value) {
                    return '-';
                }

                // JSON 格式化显示
                return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            })->unescape();
            $show->field('source_protocol');
            $show->field('target_protocol');

            // Token信息
            $show->field('prompt_tokens')->as(function ($value) {
                return number_format($value);
            });
            $show->field('completion_tokens')->as(function ($value) {
                return number_format($value);
            });
            $show->field('total_tokens')->as(function ($value) {
                return number_format($value);
            });
            $show->field('cache_read_tokens')->as(function ($value) {
                return number_format($value);
            });
            $show->field('cache_write_tokens')->as(function ($value) {
                return number_format($value);
            });

            // 费用信息
            $show->field('cost')->as(function ($value) {
                return $value ? '$'.number_format($value, 6) : '-';
            });
            $show->field('quota')->as(function ($value) {
                return $value ? number_format($value, 6) : '-';
            });
            $show->field('billing_source')->using(AuditLog::getBillingSources());

            // 状态信息
            $show->field('status_code');
            $show->field('latency_ms')->as(function ($value) {
                return number_format($value);
            });
            $show->field('first_token_ms')->as(function ($value) {
                return $value ? number_format($value) : '-';
            });

            // 流式信息
            $show->field('is_stream')->using(admin_trans_options('is_stream'));
            $show->field('finish_reason');

            // 错误信息
            $show->field('error_type');
            $show->field('error_message');

            // 客户端信息
            $show->field('client_ip');
            $show->field('user_agent');
            $show->field('group_name');

            // 其他信息
            $show->field('channel_affinity')->json();
            $show->field('metadata')->json();

            // 时间信息
            $show->field('created_at');

            // 禁用编辑按钮
            $show->disableEditButton();

            // 禁用删除按钮
            $show->disableDeleteButton();
        });
    }

    /**
     * 禁用表单
     */
    protected function form()
    {
        // 只读模式,不提供表单
        abort(404);
    }

    /**
     * 标题
     */
    protected function title(): string
    {
        return admin_trans_label('title');
    }
}
