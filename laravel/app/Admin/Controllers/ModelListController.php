<?php

namespace App\Admin\Controllers;

use App\Models\ModelList;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

/**
 * 模型列表管理控制器
 *
 * 管理AI模型的配置信息，包括模型名称、提供商、定价等
 */
class ModelListController extends AdminController
{
    /**
     * 模型标题
     *
     * @var string
     */
    protected $title = '模型列表';

    /**
     * 获取模型实例
     *
     * @return ModelList
     */
    protected function model()
    {
        return new ModelList;
    }

    /**
     * 列表页面
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make($this->model(), function (Grid $grid) {
            // 默认排序
            $grid->model()->orderBy('id', 'desc');

            // 列表字段
            $grid->column('id', 'ID')->sortable();
            $grid->column('model_name', '模型名称')->copyable();
            $grid->column('display_name', '显示名称');
            $grid->column('provider', '提供商')->label([
                'openai' => 'primary',
                'anthropic' => 'success',
                'google' => 'warning',
                'meta' => 'info',
                'alibaba' => 'danger',
                'baidu' => 'secondary',
            ]);
            $grid->column('is_enabled', '状态')->switch();
            $grid->column('context_length', '上下文长度')->display(function ($value) {
                return $value ? number_format($value) : '-';
            });
            $grid->column('pricing_prompt', '输入价格')->display(function ($value) {
                return $value ? '$'.number_format($value, 6) : '-';
            });

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('id', 'ID');
                $filter->like('model_name', '模型名称');
                $filter->like('display_name', '显示名称');
                $filter->equal('provider', '提供商')->select([
                    'openai' => 'OpenAI',
                    'anthropic' => 'Anthropic',
                    'google' => 'Google',
                    'meta' => 'Meta',
                    'alibaba' => 'Alibaba',
                    'baidu' => 'Baidu',
                ]);
                $filter->equal('is_enabled', '状态')->select([
                    1 => '启用',
                    0 => '禁用',
                ]);
            });

            // 快速搜索
            $grid->quickSearch(['id', 'model_name', 'display_name', 'provider']);

            // 操作按钮
            $grid->actions(function (Grid\Displayers\Actions $actions) {
                // 查看按钮
                $actions->append('<a href="'.$actions->getResource().'/'.$actions->getKey().'" class="btn btn-primary btn-sm" style="margin-right:3px;"><i class="feather icon-eye"></i> 查看</a>');
            });

            // 批量操作
            $grid->batchActions(function (Grid\Tools\BatchActions $batch) {
                $batch->enableDelete();
            });
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
        return Show::make($id, $this->model(), function (Show $show) {
            $show->field('id', 'ID');
            $show->field('model_name', '模型名称');
            $show->field('display_name', '显示名称');
            $show->field('provider', '提供商');
            $show->field('common_name', '通用名称');
            $show->field('hugging_face_id', 'Hugging Face ID');
            $show->field('description', '描述');
            $show->field('capabilities', '能力标签')->json();
            $show->field('context_length', '上下文长度');
            $show->field('pricing_prompt', '输入价格');
            $show->field('pricing_completion', '输出价格');
            $show->field('pricing_input_cache_read', '缓存读取价格');
            $show->field('is_enabled', '状态')->using([1 => '启用', 0 => '禁用']);
            $show->field('config', '配置')->json();
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
        return Form::make($this->model(), function (Form $form) {
            // 基本信息
            $form->tab('基本信息', function (Form $form) {
                $form->text('model_name', '模型名称')
                    ->required()
                    ->help('模型的唯一标识符，如 gpt-4, claude-3-opus 等');

                $form->text('display_name', '显示名称')
                    ->help('模型的友好显示名称，如 GPT-4, Claude 3 Opus 等');

                $form->select('provider', '提供商')
                    ->options([
                        'openai' => 'OpenAI',
                        'anthropic' => 'Anthropic',
                        'google' => 'Google',
                        'meta' => 'Meta',
                        'alibaba' => 'Alibaba',
                        'baidu' => 'Baidu',
                    ])
                    ->required();

                $form->text('common_name', '通用名称')
                    ->help('模型的通用名称，用于分类和分组');

                $form->text('hugging_face_id', 'Hugging Face ID')
                    ->help('Hugging Face 模型标识符');
            });

            // 描述和能力
            $form->tab('描述和能力', function (Form $form) {
                $form->textarea('description', '描述')
                    ->rows(3)
                    ->help('模型的详细描述信息');

                $form->tags('capabilities', '能力标签')
                    ->options([
                        'chat' => '对话',
                        'completion' => '文本补全',
                        'embedding' => '向量嵌入',
                        'image' => '图像理解',
                        'vision' => '视觉',
                        'code' => '代码生成',
                        'function_call' => '函数调用',
                        'streaming' => '流式输出',
                        'json_mode' => 'JSON模式',
                    ])
                    ->help('模型支持的能力标签');

                $form->number('context_length', '上下文长度')
                    ->min(0)
                    ->default(4096)
                    ->help('模型支持的最大上下文长度（tokens）');
            });

            // 定价信息
            $form->tab('定价信息', function (Form $form) {
                $form->currency('pricing_prompt', '输入价格')
                    ->symbol('$')
                    ->digits(6)
                    ->help('每1K tokens输入价格（美元）');

                $form->currency('pricing_completion', '输出价格')
                    ->symbol('$')
                    ->digits(6)
                    ->help('每1K tokens输出价格（美元）');

                $form->currency('pricing_input_cache_read', '缓存读取价格')
                    ->symbol('$')
                    ->digits(6)
                    ->help('每1K tokens缓存读取价格（美元）');
            });

            // 配置和状态
            $form->tab('配置和状态', function (Form $form) {
                $form->switch('is_enabled', '启用状态')
                    ->default(true);

                $form->textarea('config', '扩展配置')
                    ->rows(5)
                    ->help('模型的扩展配置信息（JSON格式）')
                    ->customFormat(function ($value) {
                        // 显示时格式化JSON
                        if (is_array($value)) {
                            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        }

                        return $value;
                    })
                    ->saving(function ($value) {
                        // 保存时解析JSON
                        if (is_string($value) && ! empty($value)) {
                            $decoded = json_decode($value, true);

                            return $decoded ?? $value;
                        }

                        return $value;
                    });
            });

            // 保存前验证
            $form->saving(function (Form $form) {
                // 如果没有填写显示名称，使用模型名称
                if (empty($form->display_name)) {
                    $form->display_name = $form->model_name;
                }
            });

            // 保存后回调
            $form->saved(function (Form $form) {
                // 可以在这里添加日志记录等操作
            });
        });
    }
}
