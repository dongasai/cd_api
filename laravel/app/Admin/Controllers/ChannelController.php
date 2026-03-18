<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\CopyChannel;
use App\Enums\ChannelHealthStatus;
use App\Enums\ChannelStatus;
use App\Models\Channel;
use App\Models\ChannelModel;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

/**
 * 渠道管理控制器
 */
class ChannelController extends AdminController
{
    /**
     * 渠道模型
     */
    protected $title = '渠道管理';

    /**
     * 列表页面
     */
    protected function grid()
    {
        return Grid::make(Channel::with('codingAccount'), function (Grid $grid) {
            // 默认排序
            $grid->model()->orderBy('id', 'desc');

            // 列字段配置
            $grid->column('id', 'ID')->sortable();
            $grid->column('name', '渠道名称')->link(function () {
                return admin_url('channels/'.$this->id);
            });
            $grid->column('slug', '标识符');
            $grid->column('provider', '提供商');

            // 关联的Coding账户
            $grid->column('coding_account.name', '关联账户')->display(function () {
                if ($this->codingAccount) {
                    $status = $this->codingAccount->status;
                    $statusLabels = \App\Models\CodingAccount::getStatuses();
                    $statusLabel = $statusLabels[$status] ?? $status;

                    $colors = [
                        \App\Models\CodingAccount::STATUS_ACTIVE => 'success',
                        \App\Models\CodingAccount::STATUS_WARNING => 'warning',
                        \App\Models\CodingAccount::STATUS_CRITICAL => 'danger',
                        \App\Models\CodingAccount::STATUS_EXHAUSTED => 'secondary',
                        \App\Models\CodingAccount::STATUS_EXPIRED => 'info',
                        \App\Models\CodingAccount::STATUS_SUSPENDED => 'dark',
                        \App\Models\CodingAccount::STATUS_ERROR => 'danger',
                    ];
                    $color = $colors[$status] ?? 'secondary';

                    $url = admin_url('coding-accounts/'.$this->codingAccount->id);

                    return "<a href='{$url}'><span class='badge bg-{$color}'>{$this->codingAccount->name} ({$statusLabel})</span></a>";
                }

                return '<span class="text-muted">-</span>';
            });
            $grid->column('status', '状态')->display(function ($value) {
                $status = ChannelStatus::tryFrom($value);
                if ($status) {
                    return '<span class="badge bg-'.$status->labelStyle().'">'.$status->label().'</span>';
                }

                return $value;
            });
            $grid->column('status2', '健康状态')->display(function ($value) {
                $status = ChannelHealthStatus::tryFrom($value);
                if (! $status) {
                    return $value;
                }

                if ($status === ChannelHealthStatus::NORMAL) {
                    return '<span class="badge bg-success">正常</span>';
                }

                $html = '<span class="badge bg-danger">禁用</span>';

                // 如果禁用且有备注，显示备注
                if ($this->status2_remark) {
                    $html .= ' <span class="text-danger small">'.$this->status2_remark.'</span>';
                }

                return $html;
            });
            $grid->column('success_rate', '成功率')->display(function ($value) {
                return $value ? number_format($value * 100, 2).'%' : '-';
            });
            $grid->column('total_requests', '总请求数')->sortable();
            $grid->column('last_check_at', '最后检查时间')->sortable();

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                // 不使用抽屉模式，直接展开
                $filter->panel();
                $filter->expand(true);

                $filter->equal('id', 'ID');
                $filter->like('name', '渠道名称');
                $filter->equal('status', '状态')->select(ChannelStatus::options());
                $filter->equal('status2', '健康状态')->select(ChannelHealthStatus::options());
                $filter->equal('provider', '提供商')->select(
                    Channel::query()->distinct()->pluck('provider', 'provider')->toArray()
                );
            });

            // 快捷搜索
            $grid->quickSearch(['id', 'name', 'slug', 'provider']);

            // 操作按钮
            $grid->actions(function (Grid\Displayers\Actions $actions) {
                // 查看按钮
                $actions->append('<a href="'.admin_url('channels/'.$this->id).'" class="btn btn-primary btn-sm mr-1"><i class="fa fa-eye"></i> 查看</a>');

                // 复制按钮
                $actions->append(new CopyChannel);
            });

            // 批量操作
            $grid->batchActions(function (Grid\Tools\BatchActions $batch) {
                $batch->disableDelete();
            });

            // 启用导出
            $grid->export();
        });
    }

    /**
     * 详情页面
     */
    protected function detail($id)
    {
        $channel = Channel::with(['channelModels', 'codingAccount'])->findOrFail($id);

        return Show::make($channel, function (Show $show) {
            // 使用 width 方法设置字段宽度，实现双列布局
            $show->field('id', 'ID')->width(3);
            $show->field('name', '渠道名称')->width(3);
            $show->field('slug', '标识符')->width(3);
            $show->field('provider', '提供商')->using([
                'openai' => 'OpenAI',
                'anthropic' => 'Anthropic',
                'google' => 'Google',
                'openai_compatible' => 'OpenAI Compatible',
            ])->width(3);

            $show->field('base_url', 'API地址')->width(6);

            // 关联的 Coding 账户
            $show->field('coding_account_id', '关联Coding账户')->as(function () {
                if ($this->codingAccount) {
                    $status = $this->codingAccount->status;
                    $statusLabels = \App\Models\CodingAccount::getStatuses();
                    $statusLabel = $statusLabels[$status] ?? $status;

                    $colors = [
                        \App\Models\CodingAccount::STATUS_ACTIVE => 'success',
                        \App\Models\CodingAccount::STATUS_WARNING => 'warning',
                        \App\Models\CodingAccount::STATUS_CRITICAL => 'danger',
                        \App\Models\CodingAccount::STATUS_EXHAUSTED => 'secondary',
                        \App\Models\CodingAccount::STATUS_EXPIRED => 'info',
                        \App\Models\CodingAccount::STATUS_SUSPENDED => 'dark',
                        \App\Models\CodingAccount::STATUS_ERROR => 'danger',
                    ];
                    $color = $colors[$status] ?? 'secondary';

                    $url = admin_url('coding-accounts/'.$this->codingAccount->id);

                    return "<a href='{$url}'><span class='badge bg-{$color}'>{$this->codingAccount->name} ({$statusLabel})</span></a>";
                }

                return '<span class="text-muted">未关联</span>';
            })->width(6);

            $show->field('status', '状态')->as(function ($value) {
                $status = ChannelStatus::tryFrom($value);
                if ($status) {
                    return '<span class="badge bg-'.$status->labelStyle().'">'.$status->label().'</span>';
                }

                return $value;
            })->unescape()->width(3);
            $show->field('status2', '健康状态')->as(function ($value) {
                $status = ChannelHealthStatus::tryFrom($value);
                if (! $status) {
                    return $value;
                }

                $html = '<span class="badge bg-'.$status->labelStyle().'">'.$status->label().'</span>';

                // 如果禁用且有备注，显示备注
                if ($status === ChannelHealthStatus::DISABLED && $this->status2_remark) {
                    $html .= ' <small class="text-muted">('.$this->status2_remark.')</small>';
                }

                return $html;
            })->unescape()->width(3);
            $show->field('weight', '权重')->width(3);

            $show->divider('配置信息');

            $show->field('priority', '优先级')->width(3);
            $show->field('inherit_mode', '继承模式')->using([
                'merge' => '合并继承',
                'override' => '覆盖继承',
                'extend' => '扩展继承',
            ])->width(3);
            $show->field('parent_id', '父渠道ID')->width(3);
            $show->field('api_key_hash', 'API Key指纹')->as(function ($value) {
                return $value ? '<code>'.substr($value, 0, 8).'...</code>' : '-';
            })->width(3);
            $show->field('description', '描述');

            $show->divider('运行统计');

            $show->field('success_count', '成功次数')->as(function ($value) {
                return '<span class="text-success font-weight-bold" style="font-size:1.2em">'.($value ?? 0).'</span>';
            })->unescape()->width(3);
            $show->field('failure_count', '失败次数')->as(function ($value) {
                return '<span class="text-danger font-weight-bold" style="font-size:1.2em">'.($value ?? 0).'</span>';
            })->unescape()->width(3);
            $show->field('total_requests', '总请求数')->as(function ($value) {
                // 计算实际总请求数 = 成功次数 + 失败次数
                $total = ($this->success_count ?? 0) + ($this->failure_count ?? 0);

                return '<span class="text-primary font-weight-bold" style="font-size:1.2em">'.$total.'</span>';
            })->unescape()->width(3);
            $show->field('success_rate', '成功率')->as(function ($value) {
                // 重新计算成功率
                $successCount = $this->success_count ?? 0;
                $failureCount = $this->failure_count ?? 0;
                $total = $successCount + $failureCount;

                $rate = $total > 0 ? ($successCount / $total) * 100 : 0;
                $color = $rate >= 95 ? 'success' : ($rate >= 80 ? 'warning' : 'danger');

                return '<span class="text-'.$color.' font-weight-bold" style="font-size:1.2em">'.number_format($rate, 2).'%</span>';
            })->unescape()->width(3);
            $show->field('total_cost', '总成本')->as(function ($value) {
                return '<span class="text-info font-weight-bold">$'.number_format($value ?? 0, 4).'</span>';
            })->unescape()->width(3);
            $show->field('avg_latency_ms', '平均延迟')->as(function ($value) {
                return '<span class="text-muted">'.($value ? number_format($value, 2).' ms' : '-').'</span>';
            })->unescape()->width(3);

            $show->divider('时间记录');

            $show->field('created_at', '创建时间')->width(4);
            $show->field('updated_at', '更新时间')->width(4);
            $show->field('last_check_at', '最后检查时间')->width(4);
            $show->field('last_success_at', '最后成功时间')->width(4);
            $show->field('last_failure_at', '最后失败时间')->width(4);

            $show->divider('渠道模型列表');

            $show->relation('channelModels', function ($model) {
                $grid = new Grid(new ChannelModel);
                $grid->model()->where('channel_id', $model->id);
                $grid->column('id', 'ID')->sortable();
                $grid->column('model_name', '模型名称')->label('primary');
                $grid->column('display_name', '显示名称');
                $grid->column('mapped_model', '映射模型')->label('info');
                $grid->column('is_enabled', '状态')->bool();
                $grid->column('is_default', '默认模型')->bool(['0' => '', '1' => '是']);
                $grid->column('multiplier', '倍率');
                $grid->column('rpm_limit', 'RPM限制');
                $grid->column('context_length', '上下文长度');
                $grid->disableActions();
                $grid->disableCreateButton();
                $grid->disablePagination();

                return $grid;
            });
        });
    }

    /**
     * 表单页面
     */
    protected function form()
    {
        return Form::make(Channel::class, function (Form $form) {
            // 编辑时加载关联数据
            if ($form->isEditing()) {
                $form->model()->load('channelModels');
            }
            // 基本信息
            $form->tab('基本信息', function (Form $form) {
                $form->text('name', '渠道名称')->required()->maxLength(100);
                $form->text('slug', '标识符')->required()->maxLength(50)
                    ->help('唯一标识符，用于API调用时的渠道识别');
                $form->select('provider', '提供商')
                    ->options([
                        'openai' => 'OpenAI',
                        'anthropic' => 'Anthropic',
                        'google' => 'Google',
                        'openai_compatible' => 'OpenAI Compatible',
                    ])
                    ->required()
                    ->help('选择渠道驱动类型');
                $form->textarea('description', '描述')->rows(3);
            });

            // API配置
            $form->tab('API配置', function (Form $form) {
                $form->url('base_url', 'API地址')->required()
                    ->help('上游API的基础URL地址');
                $form->password('api_key', 'API Key')
                    ->help('上游API的密钥，将安全存储');
            });

            // 状态设置
            $form->tab('状态设置', function (Form $form) {
                $form->select('status', '状态')->options([
                    'active' => '正常',
                    'disabled' => '禁用',
                    'maintenance' => '维护中',
                ])->default('active')->required();

                $form->select('status2', '健康状态')->options([
                    'normal' => '正常',
                    'disabled' => '禁用',
                ])->default('normal')->required()
                    ->help('健康状态为禁用时，该渠道不参与渠道选择');

                $form->textarea('status2_remark', '健康状态备注')
                    ->rows(2)
                    ->help('记录健康状态变更原因，如：CodingStatus禁用');

                // 关联 Coding 账户
                $form->select('coding_account_id', '关联Coding账户')
                    ->options(\App\Models\CodingAccount::pluck('name', 'id')->toArray())
                    ->help('关联后，渠道将根据 Coding 账户的配额状态自动调整可用性');

                $form->number('weight', '权重')->default(1)->min(0)->max(100)
                    ->help('负载均衡时的权重，值越大分配的请求越多');
                $form->number('priority', '优先级')->default(1)->min(1)->max(100)
                    ->help('渠道选择的优先级，值越小优先级越高');
            });

            // 统计信息（只读）
            $form->tab('统计信息', function (Form $form) {
                $form->display('success_count', '成功次数');
                $form->display('failure_count', '失败次数');
                $form->display('total_requests', '总请求数');
                $form->display('total_cost', '总成本');
                $form->display('success_rate', '成功率')->with(function ($value) {
                    return $value ? number_format($value * 100, 2).'%' : '-';
                });
                $form->display('last_check_at', '最后检查时间');
                $form->display('last_success_at', '最后成功时间');
                $form->display('last_failure_at', '最后失败时间');
            });

            // 渠道模型配置
            $form->tab('渠道模型', function (Form $form) {
                $form->hasMany('channelModels', '模型列表', function (Form\NestedForm $form) {
                    $form->text('model_name', '模型名称')
                        ->required()
                        ->placeholder('如: gpt-4, claude-3-opus');
                    $form->text('display_name', '显示名称')
                        ->placeholder('用于显示的友好名称');
                    $form->text('mapped_model', '映射模型')
                        ->placeholder('实际调用上游的模型名称');
                    $form->switch('is_enabled', '启用')->default(true);
                    $form->switch('is_default', '默认模型')->default(false);
                    $form->number('rpm_limit', 'RPM限制')
                        ->min(0)
                        ->placeholder('0表示不限制');
                    $form->number('context_length', '上下文长度')
                        ->min(0)
                        ->placeholder('最大token数');
                    $form->text('multiplier', '倍率')
                        ->value('1.0000')
                        ->placeholder('如: 1.0, 1.5');
                });
            });

            // 高级配置
            $form->tab('高级配置', function (Form $form) {
                $form->embeds('config', '扩展配置', function (Form\EmbeddedForm $form) {
                    $form->switch('filter_thinking', '过滤 Thinking')
                        ->help('是否过滤模型响应中的 thinking 内容块')
                        ->default(false);
                    $form->switch('filter_request_thinking', '过滤 Request-Thinking')
                        ->help('是否过滤模型请求中的 thinking 内容块')
                        ->default(false);
                    $form->switch('body_passthrough', 'Body透传')
                        ->help('开启后，来自客户端的请求体将不进行任何处理直接发送给上游渠道')
                        ->default(false);
                });
                $form->list('forward_headers', '转发Headers')
                    ->help('需要转发到上游的请求头名称列表，支持通配符如 x-*');
                $form->number('parent_id', '父渠道ID')->min(0)
                    ->help('继承配置的父渠道ID');
                $form->select('inherit_mode', '继承模式')->options([
                    'merge' => '合并继承',
                    'override' => '覆盖继承',
                    'extend' => '扩展继承',
                ])->default('merge');
            });

            // User-Agent限制
            $form->tab('User-Agent限制', function (Form $form) {
                $form->multipleSelect('allowedUserAgents', '允许的User-Agent')
                    ->options(\App\Models\UserAgent::where('is_enabled', true)->pluck('name', 'id'))
                    ->customFormat(function ($v) {
                        if (! $v) {
                            return [];
                        }

                        return $v->pluck('id')->toArray();
                    })
                    ->saving(function ($value) {
                        return $value;
                    })
                    ->help('选择允许访问此渠道的User-Agent规则，留空表示允许所有');

                $form->display('has_user_agent_restriction', '限制状态')->with(function ($value) {
                    return $value ? '<span class="badge badge-warning">已启用限制</span>' : '<span class="badge badge-success">无限制</span>';
                });
            });

            // 保存时处理API Key
            $form->saving(function (Form $form) {
                // 如果设置了新的API Key，生成hash
                if ($form->api_key && $form->api_key !== $form->model()->api_key) {
                    $form->api_key_hash = substr(hash('sha256', $form->api_key), 0, 8);
                }

                // 确保 inherit_mode 有值
                if (empty($form->inherit_mode)) {
                    $form->inherit_mode = 'merge';
                }
            });

            // 保存后更新has_user_agent_restriction标志
            $form->saved(function (Form $form) {
                $channel = $form->model();
                $hasRestriction = $channel->allowedUserAgents()->exists();
                $channel->update(['has_user_agent_restriction' => $hasRestriction]);
            });

            // 删除时确认
            $form->deleting(function (Form $form) {
                // 可以添加删除前的检查逻辑
            });
        });
    }
}
