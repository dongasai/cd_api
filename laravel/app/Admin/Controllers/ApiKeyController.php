<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\RefreshModelCache;
use App\Admin\Actions\ResetApiKey;
use App\Admin\Grids\ChannelSelectGrid;
use App\Models\ApiKey;
use App\Models\Channel;
use App\Services\ModelService;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;
use Illuminate\Support\Str;

/**
 * API密钥管理控制器
 */
class ApiKeyController extends AdminController
{
    /**
     * 语言包名称
     *
     * @var string
     */
    public $translation = 'admin-api-key';

    /**
     * 模型
     *
     * @var string
     */
    protected $model = ApiKey::class;

    /**
     * 列表页面
     */
    protected function grid(): Grid
    {
        return Grid::make(ApiKey::query()->orderBy('id', 'desc'), function (Grid $grid) {
            // 列表字段
            $grid->column('id', admin_trans_field('id'))->sortable();
            $grid->column('name', admin_trans_field('name'))->copyableValue('key');  // 显示名称，点击复制图标可复制密钥
            $grid->column('key', admin_trans_field('key'))
                ->display(function ($value) {
                    return substr($value, 0, 15).'...';  // 显示部分密钥
                })
                ->copyableValue();  // 复制完整密钥
            $grid->column('status', admin_trans_field('status'))->using([
                'active' => admin_trans_option('active', 'status'),
                'revoked' => admin_trans_option('revoked', 'status'),
                'expired' => admin_trans_option('expired', 'status'),
            ])->label([
                'active' => 'success',
                'revoked' => 'danger',
                'expired' => 'warning',
            ]);

            // 允许的渠道列
            $grid->column('allowed_channels', admin_trans_field('allowed_channels'))
                ->display(function ($value) {
                    // 确保是数组
                    if (is_string($value)) {
                        $value = json_decode($value, true);
                    }
                    if (empty($value) || ! is_array($value)) {
                        return admin_trans_label('no_limit');
                    }
                    // 转换为整数数组
                    $channelIds = array_map('intval', $value);
                    $channels = Channel::whereIn('id', $channelIds)->pluck('name')->toArray();

                    return empty($channels) ? admin_trans_label('no_limit') : implode(', ', $channels);
                });

            $grid->column('expires_at', admin_trans_field('expires_at'));
            $grid->column('last_used_at', admin_trans_field('last_used_at'));
            $grid->column('created_at', admin_trans_field('created_at'))->sortable();

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                // 不使用抽屉模式，直接展开
                $filter->panel();
                $filter->expand(true);

                $filter->equal('status', admin_trans_field('status'))->select([
                    'active' => admin_trans_option('active', 'status'),
                    'revoked' => admin_trans_option('revoked', 'status'),
                    'expired' => admin_trans_option('expired', 'status'),
                ]);
                $filter->like('name', admin_trans_field('name'));
                $filter->like('key', admin_trans_field('key'));
            });

            // 快速搜索
            $grid->quickSearch(['id', 'name', 'key']);

            // 操作按钮
            $grid->actions([
                new ResetApiKey,
            ]);

            // 批量操作
            $grid->batchActions([
                // 可以添加自定义批量操作
            ]);
        });
    }

    /**
     * 详情页面
     */
    protected function detail($id): Show
    {
        return Show::make($id, new ApiKey, function (Show $show) {
            $show->field('id', admin_trans_field('id'));
            $show->field('name', admin_trans_field('name'));
            $show->field('key', admin_trans_field('key'))->display(function ($value) {
                return substr($value, 0, 10).'...';
            });
            $show->field('status', admin_trans_field('status'))->using([
                'active' => admin_trans_option('active', 'status'),
                'revoked' => admin_trans_option('revoked', 'status'),
                'expired' => admin_trans_option('expired', 'status'),
            ]);
            $show->field('model_mappings', admin_trans_field('model_mappings'))->json();

            // 允许的渠道 - 显示渠道名称
            $show->field('allowed_channels', admin_trans_field('allowed_channels'))
                ->unescape()
                ->as(function ($value) {
                    // 确保是数组
                    if (is_string($value)) {
                        $value = json_decode($value, true);
                    }
                    if (empty($value) || ! is_array($value)) {
                        return '<span class="text-muted">'.admin_trans_label('no_limit').'</span>';
                    }
                    // 转换为整数数组
                    $channelIds = array_map('intval', $value);
                    $channels = Channel::whereIn('id', $channelIds)->pluck('name')->toArray();

                    if (empty($channels)) {
                        return '<span class="text-muted">'.admin_trans_label('no_limit').'</span>';
                    }

                    // 返回带标签样式的 HTML
                    return collect($channels)->map(function ($name) {
                        return "<span class='label bg-success'>$name</span>";
                    })->implode(' ');
                });

            // 禁止的渠道 - 显示渠道名称
            $show->field('not_allowed_channels', admin_trans_field('not_allowed_channels'))
                ->unescape()
                ->as(function ($value) {
                    // 确保是数组
                    if (is_string($value)) {
                        $value = json_decode($value, true);
                    }
                    if (empty($value) || ! is_array($value)) {
                        return '<span class="text-muted">'.admin_trans_label('none').'</span>';
                    }
                    // 转换为整数数组
                    $channelIds = array_map('intval', $value);
                    $channels = Channel::whereIn('id', $channelIds)->pluck('name')->toArray();

                    if (empty($channels)) {
                        return '<span class="text-muted">'.admin_trans_label('none').'</span>';
                    }

                    // 返回带标签样式的 HTML
                    return collect($channels)->map(function ($name) {
                        return "<span class='label bg-danger'>$name</span>";
                    })->implode(' ');
                });

            // 可用模型列表 - 从允许的渠道中获取启用的模型
            $show->field('available_models', admin_trans_field('available_models'))
                ->unescape()
                ->as(function ($value) {
                    // 使用 ModelService 获取可用渠道模型
                    /** @var ApiKey $this */
                    $channelModels = ModelService::getAvailableChannelModels($this);

                    if (empty($channelModels)) {
                        return '<span class="text-muted">'.admin_trans_label('no_available_models').'</span>';
                    }

                    // 构建显示HTML
                    $modelsHtml = [];
                    foreach ($channelModels as $item) {
                        $channel = $item['channel'];
                        $models = $item['models'];

                        $modelsHtml[] = "<div style='margin-bottom: 10px;'>";
                        $modelsHtml[] = "<strong>{$channel->name}</strong>: ";
                        $modelLabels = $models->map(function ($model) {
                            $displayName = $model->getDisplayName();
                            $modelName = $model->model_name;
                            if ($model->mapped_model) {
                                $actualModel = admin_trans_label('actual_model');

                                return "<span class='label bg-primary' title=\"{$actualModel}: {$model->mapped_model}\">{$displayName} ({$modelName})</span>";
                            }

                            return "<span class='label bg-primary'>{$displayName} ({$modelName})</span>";
                        })->implode(' ');
                        $modelsHtml[] = $modelLabels;
                        $modelsHtml[] = '</div>';
                    }

                    return implode('', $modelsHtml);
                });

            // 速率限制 - 友好显示
            $show->field('rate_limit', admin_trans_field('rate_limit'))
                ->unescape()
                ->as(function ($value) {
                    // 确保是数组
                    if (is_string($value)) {
                        $value = json_decode($value, true);
                    }
                    if (empty($value) || ! is_array($value)) {
                        return '<span class="text-muted">'.admin_trans_label('no_limit').'</span>';
                    }
                    $parts = [];
                    if (! empty($value['requests_per_minute'])) {
                        $parts[] = "<span class='label bg-info'>{$value['requests_per_minute']} ".admin_trans_label('times_per_minute').'</span>';
                    }
                    if (! empty($value['requests_per_day'])) {
                        $parts[] = "<span class='label bg-info'>{$value['requests_per_day']} ".admin_trans_label('times_per_day').'</span>';
                    }
                    if (! empty($value['tokens_per_day'])) {
                        $parts[] = "<span class='label bg-info'>{$value['tokens_per_day']} ".admin_trans_label('tokens_per_day').'</span>';
                    }

                    return $parts ? implode(' ', $parts) : '<span class="text-muted">'.admin_trans_label('no_limit').'</span>';
                });

            $show->field('expires_at', admin_trans_field('expires_at'));
            $show->field('last_used_at', admin_trans_field('last_used_at'));
            $show->field('created_at', admin_trans_field('created_at'));
            $show->field('updated_at', admin_trans_field('updated_at'));

            // 添加刷新模型缓存工具按钮
            $show->tools(function (Show\Tools $tools) {
                $tools->append(new RefreshModelCache);
            });
        });
    }

    /**
     * 表单页面
     */
    protected function form(): Form
    {
        return Form::make(new ApiKey, function (Form $form) {
            // 基本信息
            $form->display('id', admin_trans_field('id'));

            $form->text('name', admin_trans_field('name'))
                ->required()
                ->maxLength(100)
                ->help(admin_trans_label('name_help'));

            // 密钥字段 - 生成一次
            $defaultKey = $this->generateApiKey();
            $form->text('key', admin_trans_field('key'))
                ->default($defaultKey)
                ->required()
                ->help(admin_trans_label('key_help'))
                ->readOnly();

            // 状态
            $form->select('status', admin_trans_field('status'))
                ->options([
                    'active' => admin_trans_option('active', 'status'),
                    'revoked' => admin_trans_option('revoked', 'status'),
                    'expired' => admin_trans_option('expired', 'status'),
                ])
                ->default('active')
                ->required();

            // 模型映射
            $form->keyValue('model_mappings', admin_trans_field('model_mappings'))
                ->help(admin_trans_label('model_mappings_help'));

            // 渠道限制,
            // 允许的渠道,多选
            $form->multipleSelectTable('allowed_channels', admin_trans_field('allowed_channels'))

                ->from(new ChannelSelectGrid)
                ->model(Channel::class, 'id', 'name')
                ->help(admin_trans_label('allowed_channels_help'));
            // 禁止的渠道,多选
            $form->multipleSelectTable('not_allowed_channels', admin_trans_field('not_allowed_channels'))

                ->from(new ChannelSelectGrid)
                ->model(Channel::class, 'id', 'name')
                ->help(admin_trans_label('not_allowed_channels_help'));

            // 速率限制
            $form->embeds('rate_limit', admin_trans_field('rate_limit'), function ($form) {
                $form->number('requests_per_minute', admin_trans_field('requests_per_minute'))
                    ->min(0)
                    ->help(admin_trans_label('requests_per_minute_help'));
                $form->number('requests_per_day', admin_trans_field('requests_per_day'))
                    ->min(0)
                    ->help(admin_trans_label('requests_per_day_help'));
                $form->number('tokens_per_day', admin_trans_field('tokens_per_day'))
                    ->min(0)
                    ->help(admin_trans_label('tokens_per_day_help'));
            })->help(admin_trans_label('rate_limit_help'));

            // 过期时间
            $form->datetime('expires_at', admin_trans_field('expires_at'))
                ->help(admin_trans_label('expires_at_help'));

            // 时间戳
            $form->display('created_at', admin_trans_field('created_at'));
            $form->display('updated_at', admin_trans_field('updated_at'));
        });
    }

    /**
     * 生成API密钥
     */
    protected function generateApiKey(): string
    {
        // 获取系统配置的密钥前缀，默认为 cdapi-
        $prefix = app(\App\Services\SettingService::class)->get('security.api_key_prefix', 'cdapi-');

        return $prefix.Str::random(48);
    }

    /**
     * 标题
     */
    protected function title(): string
    {
        return admin_trans_label('title');
    }
}
