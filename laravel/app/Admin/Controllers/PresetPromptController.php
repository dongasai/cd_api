<?php

namespace App\Admin\Controllers;

use App\Models\PresetPrompt;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

/**
 * 预设提示词管理控制器
 */
class PresetPromptController extends AdminController
{
    /**
     * 页面标题
     */
    protected $title = '预设提示词';

    /**
     * 列表页面
     */
    protected function grid()
    {
        return Grid::make(PresetPrompt::class, function (Grid $grid) {
            // 默认排序
            $grid->model()->orderBy('sort_order')->orderBy('id', 'desc');

            // 列字段配置
            $grid->column('id', 'ID')->sortable();
            $grid->column('name', '名称')->link(function () {
                return admin_url('preset-prompts/'.$this->id);
            });
            $grid->column('category', '分类')->display(function ($value) {
                $categories = PresetPrompt::getCategories();

                return $categories[$value] ?? $value;
            })->label([
                'general' => 'primary',
                'programming' => 'success',
                'translation' => 'info',
                'analysis' => 'warning',
                'writing' => 'secondary',
                'other' => 'default',
            ]);
            $grid->column('content', '内容')->display(function ($value) {
                return mb_strlen($value) > 50 ? mb_substr($value, 0, 50).'...' : $value;
            })->limit(50);
            $grid->column('is_enabled', '状态')->switch();
            $grid->column('sort_order', '排序')->sortable();
            $grid->column('created_at', '创建时间')->sortable();

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                $filter->panel();
                $filter->expand(true);

                $filter->equal('id', 'ID');
                $filter->like('name', '名称');
                $filter->equal('category', '分类')->select(PresetPrompt::getCategories());
                $filter->equal('is_enabled', '状态')->select([
                    1 => '启用',
                    0 => '禁用',
                ]);
            });

            // 快捷搜索
            $grid->quickSearch(['id', 'name', 'content']);

            // 启用导出
            $grid->export();
        });
    }

    /**
     * 详情页面
     */
    protected function detail($id)
    {
        return Show::make($id, PresetPrompt::class, function (Show $show) {
            $show->field('id', 'ID');
            $show->field('name', '名称');
            $show->field('category', '分类')->as(function ($value) {
                return PresetPrompt::getCategories()[$value] ?? $value;
            });
            $show->field('content', '内容')->unescape();
            $show->field('variables', '变量模板')->json();
            $show->field('headers', '预设Headers')->json();
            $show->field('is_enabled', '状态')->as(function ($value) {
                return $value ? '启用' : '禁用';
            });
            $show->field('sort_order', '排序');
            $show->field('created_at', '创建时间');
            $show->field('updated_at', '更新时间');
        });
    }

    /**
     * 表单页面
     */
    protected function form()
    {
        return Form::make(PresetPrompt::class, function (Form $form) {
            $form->text('name', '名称')
                ->required()
                ->maxLength(100)
                ->help('提示词的友好名称');

            $form->select('category', '分类')
                ->options(PresetPrompt::getCategories())
                ->required()
                ->default('general');

            $form->textarea('content', '提示词内容')
                ->required()
                ->rows(5)
                ->help('系统提示词的完整内容');

            $form->table('variables', '变量模板', function ($table) {
                $table->text('key', '变量名');
                $table->text('label', '显示名称');
                $table->text('default', '默认值');
            })->help('可选：定义可替换的变量，如 {{name}}');

            $form->table('headers', '预设Headers', function ($table) {
                $table->text('key', 'Header名称');
                $table->text('value', 'Header值');
            })->help('可选：预设的HTTP请求头部，如 X-Custom-Auth');

            $form->switch('is_enabled', '启用')->default(true);

            $form->number('sort_order', '排序')
                ->default(0)
                ->min(0)
                ->max(9999)
                ->help('数字越小排序越靠前');

            // 保存前验证
            $form->saving(function (Form $form) {
                // 验证 headers 格式
                if ($form->headers && is_array($form->headers)) {
                    $headers = [];
                    foreach ($form->headers as $header) {
                        if (! empty($header['key']) && ! empty($header['value'])) {
                            $headers[$header['key']] = $header['value'];
                        }
                    }
                    $form->headers = $headers;
                }

                // 验证 variables 格式
                if ($form->variables && is_array($form->variables)) {
                    $variables = [];
                    foreach ($form->variables as $variable) {
                        if (! empty($variable['key'])) {
                            $variables[$variable['key']] = [
                                'label' => $variable['label'] ?? $variable['key'],
                                'default' => $variable['default'] ?? '',
                            ];
                        }
                    }
                    $form->variables = $variables;
                }
            });
        });
    }
}
