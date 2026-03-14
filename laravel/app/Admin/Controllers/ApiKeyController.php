<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\RefreshModelCache;
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
     * 模型
     *
     * @var string
     */
    protected $model = ApiKey::class;

    /**
     * 列表页面
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(ApiKey::query()->orderBy('id', 'desc'), function (Grid $grid) {
            // 列表字段
            $grid->column('id', 'ID')->sortable();
            $grid->column('name', '名称');
            $grid->column('key_prefix', '密钥前缀')->copyable();
            $grid->column('status', '状态')->using([
                'active' => '激活',
                'revoked' => '已撤销',
                'expired' => '已过期',
            ])->label([
                'active' => 'success',
                'revoked' => 'danger',
                'expired' => 'warning',
            ]);
            $grid->column('expires_at', '过期时间');
            $grid->column('last_used_at', '最后使用时间');
            $grid->column('created_at', '创建时间')->sortable();

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                // 不使用抽屉模式，直接展开
                $filter->panel();
                $filter->expand(true);

                $filter->equal('status', '状态')->select([
                    'active' => '激活',
                    'revoked' => '已撤销',
                    'expired' => '已过期',
                ]);
                $filter->like('name', '名称');
                $filter->like('key_prefix', '密钥前缀');
            });

            // 快速搜索
            $grid->quickSearch(['id', 'name', 'key_prefix']);

            // 操作按钮
            $grid->actions([
                // 可以添加自定义操作
            ]);

            // 批量操作
            $grid->batchActions([
                // 可以添加自定义批量操作
            ]);
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
        return Show::make($id, new ApiKey, function (Show $show) {
            $show->field('id', 'ID');
            $show->field('name', '名称');
            $show->field('key_prefix', '密钥前缀');
            $show->field('status', '状态')->using([
                'active' => '激活',
                'revoked' => '已撤销',
                'expired' => '已过期',
            ]);
            $show->field('model_mappings', '模型映射')->json();

            // 允许的渠道 - 显示渠道名称
            $show->field('allowed_channels', '允许的渠道')
                ->unescape()
                ->as(function ($value) {
                    // 确保是数组
                    if (is_string($value)) {
                        $value = json_decode($value, true);
                    }
                    if (empty($value) || ! is_array($value)) {
                        return '<span class="text-muted">不限制</span>';
                    }
                    // 转换为整数数组
                    $channelIds = array_map('intval', $value);
                    $channels = Channel::whereIn('id', $channelIds)->pluck('name')->toArray();

                    if (empty($channels)) {
                        return '<span class="text-muted">不限制</span>';
                    }

                    // 返回带标签样式的 HTML
                    return collect($channels)->map(function ($name) {
                        return "<span class='label bg-success'>$name</span>";
                    })->implode(' ');
                });

            // 禁止的渠道 - 显示渠道名称
            $show->field('not_allowed_channels', '禁止的渠道')
                ->unescape()
                ->as(function ($value) {
                    // 确保是数组
                    if (is_string($value)) {
                        $value = json_decode($value, true);
                    }
                    if (empty($value) || ! is_array($value)) {
                        return '<span class="text-muted">无</span>';
                    }
                    // 转换为整数数组
                    $channelIds = array_map('intval', $value);
                    $channels = Channel::whereIn('id', $channelIds)->pluck('name')->toArray();

                    if (empty($channels)) {
                        return '<span class="text-muted">无</span>';
                    }

                    // 返回带标签样式的 HTML
                    return collect($channels)->map(function ($name) {
                        return "<span class='label bg-danger'>$name</span>";
                    })->implode(' ');
                });

            // 可用模型列表 - 从允许的渠道中获取启用的模型
            $show->field('available_models', '可用模型')
                ->unescape()
                ->as(function ($value) {
                    // 使用 ModelService 获取可用渠道模型
                    $channelModels = ModelService::getAvailableChannelModels($this);

                    if (empty($channelModels)) {
                        return '<span class="text-muted">无可用模型</span>';
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
                                return "<span class='label bg-primary' title=\"实际模型: {$model->mapped_model}\">{$displayName} ({$modelName})</span>";
                            }

                            return "<span class='label bg-primary'>{$displayName} ({$modelName})</span>";
                        })->implode(' ');
                        $modelsHtml[] = $modelLabels;
                        $modelsHtml[] = '</div>';
                    }

                    return implode('', $modelsHtml);
                });

            // 速率限制 - 友好显示
            $show->field('rate_limit', '速率限制')
                ->unescape()
                ->as(function ($value) {
                    // 确保是数组
                    if (is_string($value)) {
                        $value = json_decode($value, true);
                    }
                    if (empty($value) || ! is_array($value)) {
                        return '<span class="text-muted">不限制</span>';
                    }
                    $parts = [];
                    if (! empty($value['requests_per_minute'])) {
                        $parts[] = "<span class='label bg-info'>{$value['requests_per_minute']} 次/分钟</span>";
                    }
                    if (! empty($value['requests_per_day'])) {
                        $parts[] = "<span class='label bg-info'>{$value['requests_per_day']} 次/天</span>";
                    }
                    if (! empty($value['tokens_per_day'])) {
                        $parts[] = "<span class='label bg-info'>{$value['tokens_per_day']} Token/天</span>";
                    }

                    return $parts ? implode(' ', $parts) : '<span class="text-muted">不限制</span>';
                });

            $show->field('expires_at', '过期时间');
            $show->field('last_used_at', '最后使用时间');
            $show->field('created_at', '创建时间');
            $show->field('updated_at', '更新时间');

            // 添加刷新模型缓存工具按钮
            $show->tools(function (Show\Tools $tools) {
                $tools->append(new RefreshModelCache);
            });
        });
    }

    /**
     * 表单页面
     *
     * @return Form
     */
    protected function form()
    {
        return Form::make(new ApiKey, function (Form $form) {
            // 基本信息
            $form->display('id', 'ID');

            $form->text('name', '名称')
                ->required()
                ->maxLength(100)
                ->help('API密钥的名称，方便识别');

            // 密钥字段
            if ($form->isCreating()) {
                // 创建时自动生成密钥并显示
                $form->display('generated_key', '密钥')
                    ->default($this->generateApiKey())
                    ->help('创建时自动生成，请妥善保存。密钥只会显示一次！');
                $form->hidden('key_hash');
                $form->hidden('key_prefix');
            } else {
                // 编辑时只显示前缀
                $form->display('key_prefix', '密钥前缀')
                    ->help('密钥已隐藏，仅显示前缀');
            }

            // 状态
            $form->select('status', '状态')
                ->options([
                    'active' => '激活',
                    'revoked' => '已撤销',
                    'expired' => '已过期',
                ])
                ->default('active')
                ->required();

            // 模型映射
            $form->keyValue('model_mappings', '模型映射')
                ->help('配置模型别名映射，格式：别名 => 实际模型名。例如：cd-coding-latest => gpt-4');

            // 渠道限制,
            // 允许的渠道,多选
            $form->multipleSelectTable('allowed_channels', '允许的渠道')

                ->from(new ChannelSelectGrid)
                ->model(Channel::class, 'id', 'name')
                ->help('选择允许访问的渠道，留空表示不限制');
            // 禁止的渠道,多选
            $form->multipleSelectTable('not_allowed_channels', '禁止的渠道')

                ->from(new ChannelSelectGrid)
                ->model(Channel::class, 'id', 'name')
                ->help('选择禁止访问的渠道');

            // 速率限制
            $form->embeds('rate_limit', '速率限制', function ($form) {
                $form->number('requests_per_minute', '每分钟请求数')
                    ->min(0)
                    ->help('每分钟最大请求数，0表示不限制');
                $form->number('requests_per_day', '每日请求数')
                    ->min(0)
                    ->help('每日最大请求数，0表示不限制');
                $form->number('tokens_per_day', '每日Token数')
                    ->min(0)
                    ->help('每日最大Token数，0表示不限制');
            })->help('配置API密钥的速率限制');

            // 过期时间
            $form->datetime('expires_at', '过期时间')
                ->help('留空表示永不过期');

            // 时间戳
            $form->display('created_at', '创建时间');
            $form->display('updated_at', '更新时间');

            // 保存前回调
            $form->saving(function (Form $form) {
                // 创建时生成密钥
                if ($form->isCreating()) {
                    // 从 generated_key 字段获取密钥
                    $key = request('generated_key', $this->generateApiKey());
                    $form->key_hash = hash('sha256', $key);
                    $form->key_prefix = substr($key, 0, 10);

                    // 删除临时字段
                    $form->deleteInput('generated_key');
                }
            });

            // 保存后回调 - 清除模型缓存
            $form->saved(function (Form $form) {
                $id = $form->model()->id;
                if ($id) {
                    ModelService::clearCache((int) $id);
                }
            });
        });
    }

    /**
     * 生成API密钥
     */
    protected function generateApiKey(): string
    {
        return 'sk-'.Str::random(48);
    }

    /**
     * 标题
     *
     * @return string
     */
    protected function title()
    {
        return 'API密钥管理';
    }
}
