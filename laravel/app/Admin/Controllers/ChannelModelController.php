<?php

namespace App\Admin\Controllers;

use App\Models\Channel;
use App\Models\ChannelModel;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

/**
 * 渠道模型配置控制器
 */
class ChannelModelController extends AdminController
{
    /**
     * 页面标题
     *
     * @var string
     */
    protected $title = '渠道模型配置';

    /**
     * 列表页面
     */
    protected function grid()
    {
        return Grid::make(ChannelModel::with(['channel']), function (Grid $grid) {
            // 默认排序
            $grid->model()->orderBy('id', 'desc');

            // 列字段
            $grid->column('id', 'ID')->sortable();
            $grid->column('channel.name', '所属渠道')->link(function () {
                return admin_url('channels/'.$this->channel_id);
            });
            $grid->column('model_name', '模型名称');
            $grid->column('display_name', '显示名称');
            $grid->column('mapped_model', '映射模型');
            $grid->column('is_enabled', '状态')->switch();
            $grid->column('is_default', '默认模型')->switch();
            $grid->column('multiplier', '倍率')->display(function ($value) {
                return $value ? number_format($value, 4) : '-';
            });
            $grid->column('rpm_limit', 'RPM限制');
            $grid->column('context_length', '上下文长度')->display(function ($value) {
                return $value ? number_format($value) : '-';
            });
            $grid->column('created_at', '创建时间');

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('id', 'ID');
                $filter->equal('channel_id', '所属渠道')->select(
                    Channel::pluck('name', 'id')->toArray()
                );
                $filter->equal('is_enabled', '状态')->select([
                    1 => '启用',
                    0 => '禁用',
                ]);
                $filter->like('model_name', '模型名称');
                $filter->like('display_name', '显示名称');
            });

            // 快速搜索
            $grid->quickSearch(['id', 'model_name', 'display_name', 'mapped_model']);

            // 操作按钮
            $grid->actions(function (Grid\Displayers\Actions $actions) {
                // 查看详情
                $actions->append(new Grid\Actions\Show);
            });

            // 批量操作
            $grid->batchActions(function (Grid\Tools\BatchActions $batch) {
                $batch->enableDelete();
            });
        });
    }

    /**
     * 详情页面
     */
    protected function detail($id)
    {
        return Show::make($id, ChannelModel::with(['channel']), function (Show $show) {
            $show->field('id', 'ID');
            $show->field('channel.name', '所属渠道');
            $show->field('model_name', '模型名称');
            $show->field('display_name', '显示名称');
            $show->field('mapped_model', '映射模型');
            $show->field('is_enabled', '状态')->using([1 => '启用', 0 => '禁用']);
            $show->field('is_default', '默认模型')->using([1 => '是', 0 => '否']);
            $show->field('rpm_limit', 'RPM限制');
            $show->field('context_length', '上下文长度');
            $show->field('multiplier', '倍率');
            $show->field('config', '配置')->json();
            $show->field('created_at', '创建时间');
            $show->field('updated_at', '更新时间');
        });
    }

    /**
     * 表单页面
     */
    protected function form()
    {
        return Form::make(new ChannelModel, function (Form $form) {
            // 基础信息
            $form->display('id', 'ID');

            // 所属渠道
            $form->select('channel_id', '所属渠道')
                ->options(Channel::pluck('name', 'id')->toArray())
                ->required()
                ->help('选择所属渠道');

            // 模型名称
            $form->text('model_name', '模型名称')
                ->required()
                ->help('API请求时使用的模型名称，如 gpt-4, claude-3-opus 等');

            // 显示名称
            $form->text('display_name', '显示名称')
                ->help('用于显示的友好名称，如不填写则使用模型名称');

            // 映射模型
            $form->text('mapped_model', '映射模型')
                ->help('实际调用上游API时使用的模型名称，留空则使用模型名称');

            // 状态开关
            $form->switch('is_enabled', '启用状态')
                ->default(true);

            // 默认模型开关
            $form->switch('is_default', '默认模型')
                ->default(false)
                ->help('每个渠道只能有一个默认模型');

            // RPM限制
            $form->number('rpm_limit', 'RPM限制')
                ->min(0)
                ->help('每分钟请求数限制，0表示不限制');

            // 上下文长度
            $form->number('context_length', '上下文长度')
                ->min(0)
                ->help('模型支持的最大上下文token数');

            // 倍率
            $form->decimal('multiplier', '倍率')
                ->default(1.0)
                ->precision(4)
                ->help('计费倍率，用于计算实际消耗');

            // JSON配置
            $form->textarea('config', '扩展配置')
                ->help('JSON格式的扩展配置，如 {"max_tokens": 4096}');

            // 时间显示
            $form->display('created_at', '创建时间');
            $form->display('updated_at', '更新时间');

            // 保存前回调
            $form->saving(function (Form $form) {
                // 如果设置为默认模型，取消该渠道其他模型的默认状态
                if ($form->is_default) {
                    ChannelModel::where('channel_id', $form->channel_id)
                        ->where('id', '!=', $form->model()->id)
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }
            });

            // 验证规则
            $form->rules([
                'channel_id' => 'required|exists:channels,id',
                'model_name' => 'required|string|max:255',
                'display_name' => 'nullable|string|max:255',
                'mapped_model' => 'nullable|string|max:255',
                'rpm_limit' => 'nullable|integer|min:0',
                'context_length' => 'nullable|integer|min:0',
                'multiplier' => 'nullable|numeric|min:0',
                'config' => 'nullable|json',
            ], [
                'channel_id.required' => '请选择所属渠道',
                'channel_id.exists' => '所选渠道不存在',
                'model_name.required' => '请输入模型名称',
                'config.json' => '扩展配置必须是有效的JSON格式',
            ]);
        });
    }
}
