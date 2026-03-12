<?php

namespace App\Admin\Controllers;

use App\Models\SystemSetting;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

/**
 * 系统设置控制器
 */
class SystemSettingController extends AdminController
{
    /**
     * 页面标题
     */
    protected $title = '系统设置';

    /**
     * 列表页面
     */
    protected function grid()
    {
        return Grid::make(SystemSetting::class, function (Grid $grid) {
            // 按分组和排序排序
            $grid->model()->orderBy('group')->orderBy('sort_order')->orderBy('id');

            // 列字段配置
            $grid->column('id', 'ID')->sortable();
            $grid->column('group', '分组')->display(function ($value) {
                return SystemSetting::getGroups()[$value] ?? $value;
            })->label([
                SystemSetting::GROUP_SYSTEM => 'primary',
                SystemSetting::GROUP_QUOTA => 'success',
                SystemSetting::GROUP_SECURITY => 'danger',
                SystemSetting::GROUP_FEATURES => 'info',
            ]);
            $grid->column('key', '配置键')->copyable();
            $grid->column('label', '显示标签');
            $grid->column('value', '数值')->display(function ($value) {
                // 根据类型格式化显示
                if ($this->type === SystemSetting::TYPE_BOOLEAN) {
                    return $value ? '是' : '否';
                }
                if ($this->type === SystemSetting::TYPE_JSON || $this->type === SystemSetting::TYPE_ARRAY) {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    }
                }
                // 限制显示长度
                $display = (string) $value;
                if (mb_strlen($display) > 50) {
                    return mb_substr($display, 0, 50).'...';
                }

                return $display;
            })->limit(50);
            $grid->column('type', '类型')->display(function ($value) {
                return SystemSetting::getTypes()[$value] ?? $value;
            })->label([
                SystemSetting::TYPE_STRING => 'default',
                SystemSetting::TYPE_INTEGER => 'info',
                SystemSetting::TYPE_FLOAT => 'info',
                SystemSetting::TYPE_BOOLEAN => 'warning',
                SystemSetting::TYPE_JSON => 'success',
                SystemSetting::TYPE_ARRAY => 'success',
            ]);
            $grid->column('is_public', '是否公开')->switch();
            $grid->column('sort_order', '排序')->sortable();

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                // 不使用抽屉模式，直接展开
                $filter->panel();
                $filter->expand(true);

                $filter->equal('id', 'ID');
                $filter->equal('group', '分组')->select(SystemSetting::getGroups());
                $filter->equal('type', '类型')->select(SystemSetting::getTypes());
                $filter->equal('is_public', '是否公开')->select([
                    1 => '是',
                    0 => '否',
                ]);
                $filter->like('key', '配置键');
                $filter->like('label', '显示标签');
            });

            // 快捷搜索
            $grid->quickSearch(['id', 'key', 'label', 'description']);

            // 启用导出
            $grid->export();
        });
    }

    /**
     * 详情页面
     */
    protected function detail($id)
    {
        return Show::make($id, SystemSetting::class, function (Show $show) {
            $show->field('id', 'ID');
            $show->field('group', '分组')->as(function ($value) {
                return SystemSetting::getGroups()[$value] ?? $value;
            });
            $show->field('key', '配置键');
            $show->field('value', '配置值');
            $show->field('type', '类型')->as(function ($value) {
                return SystemSetting::getTypes()[$value] ?? $value;
            });
            $show->field('label', '显示标签');
            $show->field('description', '描述');
            $show->field('is_public', '是否公开')->as(function ($value) {
                return $value ? '是' : '否';
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
        return Form::make(SystemSetting::class, function (Form $form) {
            // 基本信息
            $form->select('group', '分组')
                ->options(SystemSetting::getGroups())
                ->required()
                ->default(SystemSetting::GROUP_SYSTEM);

            $form->text('key', '配置键')
                ->required()
                ->maxLength(100)
                ->help('配置项的唯一标识符，如: site_name, max_quota 等');

            $form->text('label', '显示标签')
                ->required()
                ->maxLength(100)
                ->help('在界面上显示的友好名称');

            $form->textarea('description', '描述')
                ->rows(2)
                ->help('配置项的详细说明');

            $form->select('type', '数据类型')
                ->options(SystemSetting::getTypes())
                ->required()
                ->default(SystemSetting::TYPE_STRING)
                ->help('选择配置值的数据类型');

            // 根据类型动态显示不同的值输入框
            $form->textarea('value', '配置值')
                ->rows(3)
                ->help('根据数据类型输入相应的值：<br>
                    - 字符串：直接输入文本<br>
                    - 整数：输入数字<br>
                    - 浮点数：输入小数<br>
                    - 布尔值：输入 true 或 false<br>
                    - JSON对象：输入JSON格式字符串<br>
                    - 数组：输入JSON数组格式');

            $form->switch('is_public', '是否公开')
                ->default(false)
                ->help('公开的配置项可以通过API获取，非公开配置仅后台可见');

            $form->number('sort_order', '排序')
                ->default(0)
                ->min(0)
                ->max(9999)
                ->help('数字越小排序越靠前');

            // 保存前验证
            $form->saving(function (Form $form) {
                $type = $form->type;
                $value = $form->value;

                // 根据类型验证值格式
                if ($value !== null && $value !== '') {
                    switch ($type) {
                        case SystemSetting::TYPE_INTEGER:
                            if (! is_numeric($value) || (int) $value != $value) {
                                return $form->response()->error('整数值格式不正确');
                            }
                            break;
                        case SystemSetting::TYPE_FLOAT:
                            if (! is_numeric($value)) {
                                return $form->response()->error('浮点数值格式不正确');
                            }
                            break;
                        case SystemSetting::TYPE_BOOLEAN:
                            if (! in_array(strtolower($value), ['true', 'false', '1', '0', 'yes', 'no'])) {
                                return $form->response()->error('布尔值必须是 true/false 或 1/0');
                            }
                            break;
                        case SystemSetting::TYPE_JSON:
                            json_decode($value);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                return $form->response()->error('JSON格式不正确: '.json_last_error_msg());
                            }
                            break;
                        case SystemSetting::TYPE_ARRAY:
                            $decoded = json_decode($value, true);
                            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                                return $form->response()->error('数组格式不正确，请输入JSON数组格式');
                            }
                            break;
                    }
                }
            });
        });
    }
}
