<?php

namespace App\Admin\Controllers;

use App\Models\Channel;
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
        return Grid::make(Channel::class, function (Grid $grid) {
            // 默认排序
            $grid->model()->orderBy('id', 'desc');

            // 列字段配置
            $grid->column('id', 'ID')->sortable();
            $grid->column('name', '渠道名称')->link(function () {
                return admin_url('channels/'.$this->id);
            });
            $grid->column('slug', '标识符');
            $grid->column('provider', '提供商');
            $grid->column('status', '状态')->using([
                'active' => '正常',
                'disabled' => '禁用',
                'maintenance' => '维护中',
            ])->label([
                'active' => 'success',
                'disabled' => 'default',
                'maintenance' => 'warning',
            ]);
            $grid->column('success_rate', '成功率')->display(function ($value) {
                return $value ? number_format($value * 100, 2).'%' : '-';
            });
            $grid->column('total_requests', '总请求数')->sortable();
            $grid->column('last_check_at', '最后检查时间')->sortable();

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('id', 'ID');
                $filter->like('name', '渠道名称');
                $filter->equal('status', '状态')->select([
                    'active' => '正常',
                    'disabled' => '禁用',
                    'maintenance' => '维护中',
                ]);
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
        return Show::make($id, Channel::class, function (Show $show) {
            $show->field('id', 'ID');
            $show->field('name', '渠道名称');
            $show->field('slug', '标识符');
            $show->field('provider', '提供商');
            $show->field('base_url', 'API地址');
            $show->field('description', '描述');
            $show->field('status', '状态')->using([
                'active' => '正常',
                'disabled' => '禁用',
                'maintenance' => '维护中',
            ]);
            $show->field('weight', '权重');
            $show->field('priority', '优先级');
            $show->field('success_count', '成功次数');
            $show->field('failure_count', '失败次数');
            $show->field('total_requests', '总请求数');
            $show->field('total_cost', '总成本');
            $show->field('success_rate', '成功率')->as(function ($value) {
                return $value ? number_format($value * 100, 2).'%' : '-';
            });
            $show->field('last_check_at', '最后检查时间');
            $show->field('last_success_at', '最后成功时间');
            $show->field('last_failure_at', '最后失败时间');
            $show->field('created_at', '创建时间');
            $show->field('updated_at', '更新时间');
        });
    }

    /**
     * 表单页面
     */
    protected function form()
    {
        return Form::make(Channel::class, function (Form $form) {
            // 基本信息
            $form->tab('基本信息', function (Form $form) {
                $form->text('name', '渠道名称')->required()->maxLength(100);
                $form->text('slug', '标识符')->required()->maxLength(50)
                    ->help('唯一标识符，用于API调用时的渠道识别');
                $form->text('provider', '提供商')->required()->maxLength(50)
                    ->help('如: openai, anthropic, google 等');
                $form->url('base_url', 'API地址')->required()
                    ->help('上游API的基础URL地址');
                $form->textarea('description', '描述')->rows(3);
            });

            // API配置
            $form->tab('API配置', function (Form $form) {
                $form->password('api_key', 'API Key')
                    ->help('上游API的密钥，将安全存储');
                $form->keyValue('models', '模型列表')
                    ->help('支持的模型映射，格式: 显示名称 => 实际模型名');
                $form->text('default_model', '默认模型')
                    ->help('当请求未指定模型时使用的默认模型');
            });

            // 状态设置
            $form->tab('状态设置', function (Form $form) {
                $form->select('status', '状态')->options([
                    'active' => '正常',
                    'disabled' => '禁用',
                    'maintenance' => '维护中',
                ])->default('active')->required();
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

            // 高级配置
            $form->tab('高级配置', function (Form $form) {
                $form->keyValue('config', '扩展配置')
                    ->help('渠道特定的配置项');
                $form->keyValue('forward_headers', '转发Headers')
                    ->help('需要转发到上游的请求头配置');
                $form->number('parent_id', '父渠道ID')->min(0)
                    ->help('继承配置的父渠道ID');
                $form->select('inherit_mode', '继承模式')->options([
                    'none' => '不继承',
                    'partial' => '部分继承',
                    'full' => '完全继承',
                ])->default('none');
            });

            // 保存时处理API Key
            $form->saving(function (Form $form) {
                // 如果设置了新的API Key，生成hash
                if ($form->api_key && $form->api_key !== $form->model()->api_key) {
                    $form->api_key_hash = substr(hash('sha256', $form->api_key), 0, 8);
                }
            });

            // 删除时确认
            $form->deleting(function (Form $form) {
                // 可以添加删除前的检查逻辑
            });
        });
    }
}
