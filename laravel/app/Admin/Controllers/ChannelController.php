<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Channel\CopyAsChildChannel;
use App\Admin\Actions\Channel\CopyChannel;
use App\Admin\Actions\Channel\TestChannelNative;
use App\Admin\Actions\Channel\TestChannelProxy;
use App\Enums\ChannelHealthStatus;
use App\Enums\ChannelStatus;
use App\Models\Channel;
use App\Models\ChannelModel;
use App\Models\CodingAccount;
use App\Models\UserAgent;
use App\Services\ChannelInheritance\ChannelInheritanceResolver;
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
     * 语言包名称
     *
     * @var string
     */
    public $translation = 'admin-channel';

    /**
     * 列表页面
     */
    protected function grid()
    {
        return Grid::make(Channel::with('codingAccount'), function (Grid $grid) {
            // 默认排序
            $grid->model()->orderBy('id', 'desc');

            // 列字段配置 - 自动读取 channel.php 语言包 fields 翻译
            $grid->column('id')->sortable();
            $grid->column('name')->link(function () {
                return admin_url('channels/'.$this->id);
            });
            $grid->column('slug');
            $grid->column('provider');

            // 关联的Coding账户
            $grid->column('coding_account.name', admin_trans_field('coding_account'))->display(function () {
                if ($this->codingAccount) {
                    $status = $this->codingAccount->status;
                    $statusLabels = CodingAccount::getStatuses();
                    $statusLabel = $statusLabels[$status] ?? $status;

                    $colors = [
                        CodingAccount::STATUS_ACTIVE => 'success',
                        CodingAccount::STATUS_WARNING => 'warning',
                        CodingAccount::STATUS_CRITICAL => 'danger',
                        CodingAccount::STATUS_EXHAUSTED => 'secondary',
                        CodingAccount::STATUS_EXPIRED => 'info',
                        CodingAccount::STATUS_SUSPENDED => 'dark',
                        CodingAccount::STATUS_ERROR => 'danger',
                    ];
                    $color = $colors[$status] ?? 'secondary';

                    $url = admin_url('coding-accounts/'.$this->codingAccount->id);

                    return "<a href='".e($url)."'><span class='badge bg-".e($color)."'>".e($this->codingAccount->name).' ('.e($statusLabel).')</span></a>';
                }

                return '<span class="text-muted">-</span>';
            });
            // 状态列 - 支持直接切换（启用/禁用）
            $grid->column('status')->display(function ($value) {
                // 将枚举转换为原始整数值供 switch 组件判断
                return $value instanceof ChannelStatus ? $value->value : $value;
            })->switch();
            $grid->column('status2', admin_trans_field('health_status'))->display(function ($value) {
                $status = $value instanceof ChannelHealthStatus ? $value : ChannelHealthStatus::tryFrom($value);
                if (! $status) {
                    return $value;
                }

                if ($status === ChannelHealthStatus::NORMAL) {
                    return '<span class="badge bg-success">'.admin_trans_option('normal', 'status2').'</span>';
                }

                $html = '<span class="badge bg-danger">'.admin_trans_option('disabled', 'status2').'</span>';

                if ($this->status2_remark) {
                    $html .= ' <span class="text-danger small">'.$this->status2_remark.'</span>';
                }

                return $html;
            });
            $grid->column('success_rate')->display(function ($value) {
                return $value ? number_format($value * 100, 2).'%' : '-';
            });
            $grid->column('total_requests')->sortable();
            $grid->column('last_check_at')->sortable();

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                $filter->panel();
                $filter->expand(true);

                $filter->equal('id');
                $filter->like('name');
                $filter->equal('status')->select(ChannelStatus::options());
                $filter->equal('status2', admin_trans_field('health_status'))->select(ChannelHealthStatus::options());
                $filter->equal('provider')->select(
                    Channel::query()->distinct()->pluck('provider', 'provider')->toArray()
                );
            });

            // 快捷搜索
            $grid->quickSearch(['id', 'name', 'slug', 'provider']);

            // 操作按钮
            $grid->actions(function (Grid\Displayers\Actions $actions) {
                $actions->append('<a href="'.admin_url('channels/'.$this->id).'" class="btn btn-primary btn-sm mr-1"><i class="fa fa-eye"></i> '.admin_trans_label('view').'</a>');
                $actions->append(new CopyChannel);
                $actions->append(new CopyAsChildChannel);
                $actions->append(new TestChannelNative);
                $actions->append(new TestChannelProxy);
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
            $show->field('id')->width(3);
            $show->field('name')->width(3);
            $show->field('slug')->width(3);
            $show->field('provider')->using(admin_trans_options('provider'))->width(3);

            $show->field('base_url')->width(6);

            // 关联的 Coding 账户
            $show->field('coding_account_id', admin_trans_label('related_coding_account'))->as(function () {
                if ($this->codingAccount) {
                    $status = $this->codingAccount->status;
                    $statusLabels = CodingAccount::getStatuses();
                    $statusLabel = $statusLabels[$status] ?? $status;

                    $colors = [
                        CodingAccount::STATUS_ACTIVE => 'success',
                        CodingAccount::STATUS_WARNING => 'warning',
                        CodingAccount::STATUS_CRITICAL => 'danger',
                        CodingAccount::STATUS_EXHAUSTED => 'secondary',
                        CodingAccount::STATUS_EXPIRED => 'info',
                        CodingAccount::STATUS_SUSPENDED => 'dark',
                        CodingAccount::STATUS_ERROR => 'danger',
                    ];
                    $color = $colors[$status] ?? 'secondary';

                    $url = admin_url('coding-accounts/'.$this->codingAccount->id);

                    return "<a href='".e($url)."'><span class='badge bg-".e($color)."'>".e($this->codingAccount->name).' ('.e($statusLabel).')</span></a>';
                }

                return '<span class="text-muted">'.admin_trans_label('not_related').'</span>';
            })->width(6);

            $show->field('status')->as(function ($value) {
                $status = $value instanceof ChannelStatus ? $value : ChannelStatus::tryFrom($value);
                if ($status) {
                    return '<span class="badge bg-'.$status->labelStyle().'">'.$status->label().'</span>';
                }

                return $value;
            })->unescape()->width(3);
            $show->field('status2', admin_trans_field('health_status'))->as(function ($value) {
                $status = $value instanceof ChannelHealthStatus ? $value : ChannelHealthStatus::tryFrom($value);
                if (! $status) {
                    return $value;
                }

                $html = '<span class="badge bg-'.$status->labelStyle().'">'.$status->label().'</span>';

                if ($status === ChannelHealthStatus::DISABLED && $this->status2_remark) {
                    $html .= ' <small class="text-muted">('.$this->status2_remark.')</small>';
                }

                return $html;
            })->unescape()->width(3);
            $show->field('weight')->width(3);

            $show->divider(admin_trans_label('config_info'));

            $show->field('priority')->width(3);
            $show->field('inherit_mode')->using(admin_trans_options('inherit_mode'))->width(3);
            $show->field('parent_id')->as(function ($value) {
                if (! $value) {
                    return '<span class="text-muted">-</span>';
                }
                $parent = Channel::find($value);
                if (! $parent) {
                    return '<span class="text-danger">父渠道不存在 (ID: '.$value.')</span>';
                }
                $url = admin_url('channels/'.$parent->id);

                return "<a href='".e($url)."'>".e($parent->name).' (ID: '.e($parent->id).')</a>';
            })->unescape()->width(3);

            // 继承配置预览
            $show->divider(admin_trans_label('inheritance_info'));

            // 继承链可视化
            $show->field('inheritance_chain', admin_trans_field('inheritance_chain'))->as(function () {
                $chain = $this->getInheritanceChain();
                if (empty($chain)) {
                    return '<span class="text-muted">'.admin_trans_label('no_inheritance').'</span>';
                }

                $html = '<div class="inheritance-chain">';
                $isFirst = true;
                foreach ($chain as $channel) {
                    if (! $isFirst) {
                        $html .= ' <span class="text-muted">→</span> ';
                    }
                    $isFirst = false;
                    $modeLabel = $channel->inherit_mode ? $channel->inherit_mode->label() : '-';
                    $url = admin_url('channels/'.$channel->id);
                    $badgeClass = $channel->id === $this->id ? 'bg-primary' : 'bg-secondary';
                    $html .= "<a href='".e($url)."'><span class='badge ".e($badgeClass)."'>".e($channel->name).' ('.e($modeLabel).')</span></a>';
                }
                $html .= '</div>';

                return $html;
            })->unescape();

            // 继承深度
            $show->field('inheritance_depth', admin_trans_field('inheritance_depth'))->as(function () {
                $depth = $this->getInheritanceDepth();
                $badgeClass = $depth === 0 ? 'secondary' : 'info';

                return '<span class="badge bg-'.$badgeClass.'">'.$depth.'</span>';
            })->unescape()->width(3);

            // 生效配置预览
            $show->field('effective_config', admin_trans_field('effective_config'))->as(function () {
                if (! $this->hasParent()) {
                    return '<span class="text-muted">'.admin_trans_label('no_inheritance').'</span>';
                }

                $html = '<table class="table table-sm table-bordered">';
                $html .= '<thead><tr><th>'.admin_trans_field('field_name').'</th><th>'.admin_trans_field('original_value').'</th><th>'.admin_trans_field('effective_value').'</th><th>'.admin_trans_field('inherited_from').'</th></tr></thead>';
                $html .= '<tbody>';

                // 标量字段对比
                $scalarFields = [
                    'provider' => admin_trans_field('provider'),
                    'base_url' => admin_trans_field('base_url'),
                    'api_key' => admin_trans_field('api_key'),
                ];

                foreach ($scalarFields as $field => $label) {
                    $originalValue = $this->$field;
                    $effectiveValue = match ($field) {
                        'provider' => $this->getEffectiveProvider(),
                        'base_url' => $this->getEffectiveBaseUrl(),
                        'api_key' => $this->getEffectiveApiKey(),
                        default => $originalValue,
                    };

                    $originalDisplay = $originalValue !== null && $originalValue !== '' ? e($originalValue) : '<span class="text-muted">-</span>';
                    $effectiveDisplay = $effectiveValue !== null && $effectiveValue !== '' ? e($effectiveValue) : '<span class="text-muted">-</span>';
                    $isInherited = $originalValue !== $effectiveValue && ! empty($effectiveValue);
                    $valueClass = $isInherited ? 'text-info' : '';

                    // 查找继承来源
                    $inheritedFrom = '-';
                    if ($isInherited) {
                        $chain = $this->getInheritanceChain();
                        foreach ($chain as $ancestor) {
                            if ($ancestor->id === $this->id) {
                                continue;
                            }
                            $ancestorValue = $ancestor->$field;
                            if ($ancestorValue === $effectiveValue) {
                                $inheritedFrom = "<a href='".e(admin_url('channels/'.$ancestor->id))."'>".e($ancestor->name).'</a>';
                                break;
                            }
                        }
                    }

                    $html .= '<tr><td>'.e($label).'</td><td>'.$originalDisplay.'</td><td class="'.e($valueClass).'">'.$effectiveDisplay.'</td><td>'.$inheritedFrom.'</td></tr>';
                }

                // 数组字段对比
                $originalConfig = $this->config ?? [];
                $effectiveConfig = $this->getResolvedConfig()['config'] ?? [];
                $configChanged = $originalConfig !== $effectiveConfig;

                if ($configChanged || ! empty($effectiveConfig)) {
                    $originalJson = json_encode($originalConfig, JSON_UNESCAPED_UNICODE);
                    $effectiveJson = json_encode($effectiveConfig, JSON_UNESCAPED_UNICODE);
                    $valueClass = $configChanged ? 'text-info' : '';
                    $html .= "<tr><td>config</td><td><code class=\"small\">{$originalJson}</code></td><td class=\"{$valueClass}\"><code class=\"small\">{$effectiveJson}</code></td><td>".($configChanged ? admin_trans_label('inherited_from') : '-').'</td></tr>';
                }

                // forward_headers 对比
                $originalHeaders = $this->forward_headers ?? [];
                $effectiveHeaders = $this->getEffectiveForwardHeaders();
                $headersChanged = $originalHeaders !== $effectiveHeaders;

                if ($headersChanged || ! empty($effectiveHeaders)) {
                    $originalJson = json_encode($originalHeaders, JSON_UNESCAPED_UNICODE);
                    $effectiveJson = json_encode($effectiveHeaders, JSON_UNESCAPED_UNICODE);
                    $valueClass = $headersChanged ? 'text-info' : '';
                    $html .= "<tr><td>forward_headers</td><td><code class=\"small\">{$originalJson}</code></td><td class=\"{$valueClass}\"><code class=\"small\">{$effectiveJson}</code></td><td>".($headersChanged ? admin_trans_label('inherited_from') : '-').'</td></tr>';
                }

                $html .= '</tbody></table>';

                return $html;
            })->unescape();
            $show->field('api_key_hash', admin_trans_label('api_key_fingerprint'))->as(function ($value) {
                return $value ? '<code>'.substr($value, 0, 8).'...</code>' : '-';
            })->width(3);
            $show->field('description');

            // 继承模型列表
            $show->field('channel_models_inherited', admin_trans_field('channel_models_inherited'))->as(function () {
                if (! $this->hasParent()) {
                    return '<span class="text-muted">'.admin_trans_label('no_inheritance').'</span>';
                }

                $models = $this->getEffectiveChannelModels();
                if (empty($models)) {
                    return '<span class="text-muted">无模型配置</span>';
                }

                $html = '<div class="inherited-models">';
                $html .= '<p class="text-muted small">'.admin_trans_label('channel_models_inherited_desc').'</p>';
                $html .= '<ul class="list-group list-group-flush">';
                foreach ($models as $model) {
                    $enabledBadge = $model['is_enabled'] ? '<span class="badge bg-success">启用</span>' : '<span class="badge bg-secondary">禁用</span>';
                    $defaultBadge = $model['is_default'] ? '<span class="badge bg-primary">默认</span>' : '';
                    $html .= '<li class="list-group-item d-flex justify-content-between align-items-center">';
                    $html .= '<span><strong>'.e($model['model_name']).'</strong>';
                    if ($model['display_name']) {
                        $html .= ' <span class="text-muted">('.e($model['display_name']).')</span>';
                    }
                    $html .= '</span>';
                    $html .= '<span>'.$enabledBadge.' '.$defaultBadge.'</span>';
                    $html .= '</li>';
                }
                $html .= '</ul></div>';

                return $html;
            })->unescape();

            $show->divider(admin_trans_label('runtime_stats'));

            $show->field('success_count')->as(function ($value) {
                return '<span class="text-success font-weight-bold" style="font-size:1.2em">'.($value ?? 0).'</span>';
            })->unescape()->width(3);
            $show->field('failure_count')->as(function ($value) {
                return '<span class="text-danger font-weight-bold" style="font-size:1.2em">'.($value ?? 0).'</span>';
            })->unescape()->width(3);
            $show->field('total_requests')->as(function () {
                $total = ($this->success_count ?? 0) + ($this->failure_count ?? 0);

                return '<span class="text-primary font-weight-bold" style="font-size:1.2em">'.$total.'</span>';
            })->unescape()->width(3);
            $show->field('success_rate')->as(function () {
                $successCount = $this->success_count ?? 0;
                $failureCount = $this->failure_count ?? 0;
                $total = $successCount + $failureCount;

                $rate = $total > 0 ? ($successCount / $total) * 100 : 0;
                $color = $rate >= 95 ? 'success' : ($rate >= 80 ? 'warning' : 'danger');

                return '<span class="text-'.$color.' font-weight-bold" style="font-size:1.2em">'.number_format($rate, 2).'%</span>';
            })->unescape()->width(3);
            $show->field('total_cost')->as(function ($value) {
                return '<span class="text-info font-weight-bold">$'.number_format($value ?? 0, 4).'</span>';
            })->unescape()->width(3);
            $show->field('avg_latency_ms')->as(function ($value) {
                return '<span class="text-muted">'.($value ? number_format($value, 2).' ms' : '-').'</span>';
            })->unescape()->width(3);

            $show->divider(admin_trans_label('time_records'));

            $show->field('created_at')->width(4);
            $show->field('updated_at')->width(4);
            $show->field('last_check_at')->width(4);
            $show->field('last_success_at')->width(4);
            $show->field('last_failure_at')->width(4);

            $show->divider(admin_trans_label('channel_models'));

            $show->relation('channelModels', function ($model) {
                $grid = new Grid(new ChannelModel);
                $grid->model()->where('channel_id', $model->id);
                $grid->column('id')->sortable();
                $grid->column('model_name')->label('primary');
                $grid->column('display_name');
                $grid->column('mapped_model')->label('info');
                $grid->column('is_enabled')->bool();
                $grid->column('is_default')->bool(['0' => '', '1' => admin_trans_option(1, 'is_default')]);
                $grid->column('multiplier');
                $grid->column('rpm_limit');
                $grid->column('context_length');
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
            if ($form->isEditing()) {
                $form->model()->load('channelModels');
            }

            // 基本信息
            $form->tab(admin_trans_label('basic_info'), function (Form $form) {
                $form->text('name')->required()->maxLength(100);
                $form->text('slug')->required()->maxLength(50)
                    ->help(admin_trans_label('slug_help'));
                $form->select('provider')
                    ->options(admin_trans_options('provider'))
                    ->required()
                    ->help(admin_trans_label('provider_help', [], 'channel'));
                $form->textarea('description')->rows(3);
            });

            // API配置
            $form->tab(admin_trans_label('api_config'), function (Form $form) {
                $form->url('base_url')
                    ->help(admin_trans_label('base_url_help'));
                $form->password('api_key')
                    ->help(admin_trans_label('api_key_help'));
            });

            // 状态设置
            $form->tab(admin_trans_label('status_settings'), function (Form $form) {
                $form->select('status')->options(ChannelStatus::options())
                    ->default(1)->required();

                $form->select('status2', admin_trans_field('health_status'))
                    ->options(admin_trans_options('status2'))
                    ->default('normal')->required()
                    ->help(admin_trans_label('health_status_help'));

                $form->textarea('status2_remark')
                    ->rows(2)
                    ->help(admin_trans_label('health_status_remark_help'));

                $form->select('coding_account_id')
                    ->options(CodingAccount::pluck('name', 'id')->toArray())
                    ->help(admin_trans_label('coding_account_help'));

                $form->number('weight')->default(1)->min(0)->max(100)
                    ->help(admin_trans_label('weight_help'));
                $form->number('priority')->default(1)->min(1)->max(100)
                    ->help(admin_trans_label('priority_help'));
            });

            // 统计信息（只读）
            $form->tab(admin_trans_label('statistics'), function (Form $form) {
                $form->display('success_count');
                $form->display('failure_count');
                $form->display('total_requests');
                $form->display('total_cost');
                $form->display('success_rate')->with(function ($value) {
                    return $value ? number_format($value * 100, 2).'%' : '-';
                });
                $form->display('last_check_at');
                $form->display('last_success_at');
                $form->display('last_failure_at');
            });

            // 渠道模型配置
            $form->tab(admin_trans_label('channel_models_tab'), function (Form $form) {
                $form->hasMany('channelModels', admin_trans_label('model_list'), function (Form\NestedForm $form) {
                    $form->text('model_name')
                        ->required()
                        ->placeholder(admin_trans_label('model_name_placeholder'));
                    $form->text('display_name')
                        ->placeholder(admin_trans_label('display_name_placeholder'));
                    $form->text('mapped_model')
                        ->placeholder(admin_trans_label('mapped_model_placeholder'));
                    $form->switch('is_enabled')->default(true);
                    $form->switch('is_default')->default(false);
                    $form->number('rpm_limit')
                        ->min(0)
                        ->placeholder(admin_trans_label('rpm_limit_placeholder'));
                    $form->number('context_length')
                        ->min(0)
                        ->placeholder(admin_trans_label('context_length_placeholder'));
                    $form->text('multiplier')
                        ->value('1.0000')
                        ->placeholder(admin_trans_label('multiplier_placeholder'));
                });
            });

            // 高级配置
            $form->tab(admin_trans_label('advanced_config'), function (Form $form) {
                $form->embeds('config', admin_trans_label('extended_config'), function (Form\EmbeddedForm $form) {
                    $form->switch('filter_thinking')
                        ->help(admin_trans_label('filter_thinking_help'))
                        ->default(false);
                    $form->switch('filter_request_thinking')
                        ->help(admin_trans_label('filter_request_thinking_help'))
                        ->default(false);
                    $form->switch('body_passthrough')
                        ->help(admin_trans_label('body_passthrough_help'))
                        ->default(false);
                    $form->switch('force_stream_option_include_usage')
                        ->help(admin_trans_label('force_stream_option_include_usage_help'))
                        ->default(false);
                });
                $form->list('forward_headers')
                    ->help(admin_trans_label('forward_headers_help'));
                $form->number('parent_id')->min(0)
                    ->help(admin_trans_label('parent_channel_id_help'));
                $form->select('inherit_mode')->options(admin_trans_options('inherit_mode'))
                    ->default('merge');
            });

            // User-Agent限制
            $form->tab(admin_trans_label('user_agent_restriction'), function (Form $form) {
                $form->multipleSelect('allowedUserAgents', admin_trans_label('allowed_user_agents'))
                    ->options(UserAgent::where('is_enabled', true)->pluck('name', 'id'))
                    ->customFormat(function ($v) {
                        if (! $v) {
                            return [];
                        }

                        return $v->pluck('id')->toArray();
                    })
                    ->saving(function ($value) {
                        return $value;
                    })
                    ->help(admin_trans_label('allowed_user_agents_help'));

                $form->display('has_user_agent_restriction', admin_trans_label('restriction_status'))->with(function ($value) {
                    return $value ? '<span class="badge badge-warning">'.admin_trans_label('restriction_enabled').'</span>' : '<span class="badge badge-success">'.admin_trans_label('restriction_disabled').'</span>';
                });
            });

            // 保存时处理API Key
            $form->saving(function (Form $form) {
                if ($form->api_key && $form->api_key !== $form->model()->api_key) {
                    $form->api_key_hash = substr(hash('sha256', $form->api_key), 0, 8);
                }

                if (empty($form->inherit_mode)) {
                    $form->inherit_mode = 'merge';
                }

                // 检测循环继承和深度限制
                $parentId = $form->parent_id;
                if ($parentId) {
                    // 校验 parent_id 是否指向有效渠道
                    if (! Channel::where('id', $parentId)->exists()) {
                        return $form->response()->error('父渠道不存在');
                    }

                    // 使用 clone 创建临时模型进行校验，避免污染原始模型
                    $tempChannel = clone $form->model();
                    $tempChannel->parent_id = $parentId;

                    // 循环检测
                    if ($tempChannel->hasCircularInheritance()) {
                        return $form->response()->error('检测到循环继承，无法保存');
                    }

                    // 深度检测（使用常量确保一致性）
                    if ($tempChannel->getInheritanceDepth() >= ChannelInheritanceResolver::MAX_DEPTH) {
                        return $form->response()->error('继承深度超过'.ChannelInheritanceResolver::MAX_DEPTH.'级限制，无法保存');
                    }
                }
            });

            // 保存后更新has_user_agent_restriction标志
            $form->saved(function (Form $form) {
                $channelId = $form->model()->id;
                // 从数据库重新查询模型，因为 $form->model() 返回的是 Fluent 对象
                $channel = Channel::find($channelId);
                if ($channel) {
                    $hasRestriction = $channel->allowedUserAgents()->exists();
                    $channel->update(['has_user_agent_restriction' => $hasRestriction]);
                }
            });
        });
    }
}
