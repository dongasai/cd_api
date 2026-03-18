<?php

namespace App\Admin\Controllers;

use App\Models\Channel;
use App\Models\UserAgent;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;

class UserAgentController extends AdminController
{
    protected $title = 'User-Agent规则管理';

    protected function grid()
    {
        return Grid::make(UserAgent::withCount('channels'), function (Grid $grid) {
            $grid->column('id', 'ID')->sortable();
            $grid->column('name', '规则名称');
            $grid->column('patterns', '正则表达式')->display(function ($patterns) {
                if (empty($patterns)) {
                    return '-';
                }

                return implode('<br>', array_map(function ($pattern, $index) {
                    return '<span class="label label-primary">'.($index + 1).'</span> '.e($pattern);
                }, $patterns, array_keys($patterns)));
            });
            $grid->column('channels_count', '关联渠道数');
            $grid->column('hit_count', '命中次数');
            $grid->column('last_hit_at', '最后命中时间');
            $grid->column('is_enabled', '状态')->switch();
            $grid->column('created_at', '创建时间');

            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('id');
                $filter->like('name', '规则名称');
                $filter->equal('is_enabled', '状态')->select([0 => '禁用', 1 => '启用']);
            });

            $grid->actions(function (Grid\Displayers\Actions $actions) {
                $actions->disableView();
            });
        });
    }

    protected function form()
    {
        return Form::make(UserAgent::with('channels'), function (Form $form) {
            $form->display('id', 'ID');
            $form->text('name', '规则名称')->required();

            // 多条正则表达式（listField）
            $form->listField('patterns', '正则表达式列表')
                ->required()
                ->help('每行一个正则表达式，如：Chrome\\/[0-9]+（不需要添加分隔符）');

            $form->textarea('description', '描述');
            $form->switch('is_enabled', '是否启用')->default(true);

            // 关联渠道（多选）
            $form->multipleSelect('channels', '关联渠道')
                ->options(Channel::pluck('name', 'id'))
                ->customFormat(function ($v) {
                    if (! $v) {
                        return [];
                    }

                    return array_column($v, 'id');
                })
                ->saving(function ($value) {
                    return $value;
                });

            $form->display('hit_count', '命中次数');
            $form->display('last_hit_at', '最后命中时间');
            $form->display('created_at', '创建时间');
            $form->display('updated_at', '更新时间');

            // 保存后更新渠道的has_user_agent_restriction标志
            $form->saved(function (Form $form) {
                $userAgent = $form->model();
                $channelIds = $userAgent->channels()->pluck('channels.id');

                // 更新所有关联渠道的标志
                Channel::whereIn('id', $channelIds)->update(['has_user_agent_restriction' => true]);

                // 检查渠道是否还有其他User-Agent规则
                Channel::whereNotIn('id', $channelIds)
                    ->where('has_user_agent_restriction', true)
                    ->get()
                    ->each(function ($channel) {
                        if (! $channel->allowedUserAgents()->exists()) {
                            $channel->update(['has_user_agent_restriction' => false]);
                        }
                    });
            });
        });
    }
}
