<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\CopyChannelAffinityRule;
use App\Enums\PathPattern;
use App\Models\ChannelAffinityRule;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

/**
 * 渠道亲和性规则管理控制器
 */
class ChannelAffinityRuleController extends AdminController
{
    /**
     * 页面标题
     *
     * @var string
     */
    protected $title = '渠道亲和性规则';

    /**
     * 列表页面
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(ChannelAffinityRule::class, function (Grid $grid) {
            // 默认按优先级和ID排序
            $grid->model()->orderBy('priority', 'desc')->orderBy('id', 'desc');

            // 列表字段
            $grid->column('id', 'ID')->sortable();
            $grid->column('name', '规则名称')->link(function () {
                return admin_url('channel-affinity-rules/'.$this->id);
            });
            $grid->column('priority', '优先级')->sortable()->label('primary');
            $grid->column('is_enabled', '状态')->switch();
            $grid->column('hit_count', '命中次数')->sortable();
            $grid->column('last_hit_at', '最后命中时间')->sortable();
            $grid->column('created_at', '创建时间')->sortable();

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                // 不使用抽屉模式，直接展开
                $filter->panel();
                $filter->expand(true);

                $filter->equal('id', 'ID');
                $filter->like('name', '规则名称');
                $filter->equal('is_enabled', '状态')->select([
                    1 => '启用',
                    0 => '禁用',
                ]);
            });

            // 快捷搜索
            $grid->quickSearch(['id', 'name', 'description']);

            // 操作按钮
            $grid->actions(function (Grid\Displayers\Actions $actions) {
                // 禁用查看按钮，使用详情页
                $actions->disableView();
                // 添加复制操作
                $actions->append(new CopyChannelAffinityRule);
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
     *
     * @param  mixed  $id
     * @return Show
     */
    protected function detail($id)
    {
        return Show::make($id, ChannelAffinityRule::class, function (Show $show) {
            $show->field('id', 'ID');
            $show->field('name', '规则名称');
            $show->field('description', '描述');
            $show->field('model_patterns', '模型匹配规则');
            $show->field('path_patterns', '路径匹配规则')->as(function ($value) {
                if (empty($value)) {
                    return '不限';
                }
                $pathPattern = PathPattern::tryFrom($value);
                if ($pathPattern) {
                    return $pathPattern->label();
                }

                return $value;
            });
            $show->field('key_sources', '键来源配置')->json();
            $show->field('key_combine_strategy', '键组合策略');
            $show->field('ttl_seconds', 'TTL秒数');
            $show->field('param_override_template', '参数覆盖模板')->json();
            $show->field('skip_retry_on_failure', '失败时跳过重试')->using([1 => '是', 0 => '否']);
            $show->field('include_group_in_key', '包含分组到键')->using([1 => '是', 0 => '否']);
            $show->field('is_enabled', '状态')->using([1 => '启用', 0 => '禁用']);
            $show->field('priority', '优先级');
            $show->field('hit_count', '命中次数');
            $show->field('last_hit_at', '最后命中时间');
            $show->field('created_at', '创建时间');
            $show->field('updated_at', '更新时间');
        });
    }

    /**
     * 表单页面
     *
     * @return Form
     */
    protected function form()
    {
        return Form::make(ChannelAffinityRule::class, function (Form $form) {
            // 基本信息
            $form->tab('基本信息', function (Form $form) {
                $form->text('name', '规则名称')
                    ->required()
                    ->maxLength(100)
                    ->help('规则的唯一名称，用于识别和管理');

                $form->textarea('description', '描述')
                    ->rows(3)
                    ->help('规则的详细描述信息，最多500字符');

                $form->number('priority', '优先级')
                    ->default(0)
                    ->min(0)
                    ->max(1000)
                    ->help('优先级数值越大越优先，范围0-1000');

                $form->switch('is_enabled', '启用状态')
                    ->default(true)
                    ->help('启用或禁用此规则');
            });

            // 匹配规则
            $form->tab('匹配规则', function (Form $form) {
                $form->text('model_patterns', '模型匹配规则')
                    ->help('正则表达式，如：/^gpt-.*$/ 匹配所有 gpt 开头的模型');

                $form->select('path_patterns', '路径匹配规则')
                    ->options(PathPattern::options())
                    ->help('选择要匹配的请求路径，留空表示不限');
            });

            // 键配置
            $form->tab('键配置', function (Form $form) {
                $form->textarea('key_sources', '键来源配置')
                    ->rows(10)
                    ->help('JSON格式的键来源配置，定义亲和性键的来源字段，如：{"sources": ["model", "path", "user_id"]}');

                $form->select('key_combine_strategy', '键组合策略')
                    ->options([
                        'concat' => '拼接',
                        'hash' => '哈希',
                        'json' => 'JSON序列化',
                    ])
                    ->default('hash')
                    ->help('多个键来源的组合方式');
            });

            // TTL和参数
            $form->tab('TTL和参数', function (Form $form) {
                $form->number('ttl_seconds', 'TTL秒数')
                    ->default(3600)
                    ->min(0)
                    ->max(86400)
                    ->help('亲和性缓存的有效期，单位秒，范围0-86400');

                $form->textarea('param_override_template', '参数覆盖模板')
                    ->rows(10)
                    ->help('JSON格式的参数覆盖模板，用于修改请求参数，如：{"temperature": 0.7, "max_tokens": 2000}');
            });

            // 行为选项
            $form->tab('行为选项', function (Form $form) {
                $form->switch('skip_retry_on_failure', '失败时跳过重试')
                    ->default(false)
                    ->help('当请求失败时是否跳过重试机制');

                $form->switch('include_group_in_key', '包含分组到键')
                    ->default(false)
                    ->help('是否将渠道分组信息包含在亲和性键中');

                // 统计信息（只读）
                $form->divider('统计信息');
                $form->display('hit_count', '命中次数');
                $form->display('last_hit_at', '最后命中时间');
            });

            // 保存前转换
            $form->saving(function (Form $form) {
                // 确保 key_combine_strategy 有默认值
                if (empty($form->input('key_combine_strategy'))) {
                    $form->model()->key_combine_strategy = 'first';
                }

                // 验证 key_sources JSON 格式
                $keySources = $form->input('key_sources');
                if (! empty($keySources) && is_string($keySources)) {
                    $decoded = json_decode($keySources, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return $form->response()->error('键来源配置必须是有效的 JSON 格式');
                    }
                }

                // 验证 param_override_template JSON 格式
                $paramOverride = $form->input('param_override_template');
                if (! empty($paramOverride) && is_string($paramOverride)) {
                    $decoded = json_decode($paramOverride, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return $form->response()->error('参数覆盖模板必须是有效的 JSON 格式');
                    }
                }
            });

            // 表单底部按钮
            $form->footer(function ($footer) {
                // 禁用查看按钮
                $footer->disableViewCheck();
            });
        });
    }
}
