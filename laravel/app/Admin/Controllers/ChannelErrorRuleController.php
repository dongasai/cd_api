<?php

namespace App\Admin\Controllers;

use App\Models\ChannelErrorRule;
use App\Models\CodingAccount;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

/**
 * 渠道错误处理规则管理控制器
 */
class ChannelErrorRuleController extends AdminController
{
    /**
     * 获取模型标题
     */
    protected function title(): string
    {
        return '错误处理规则';
    }

    /**
     * 列表页面
     */
    protected function grid(): Grid
    {
        return Grid::make(ChannelErrorRule::class, function (Grid $grid) {
            $grid->model()->orderByDesc('priority')->orderByDesc('id');

            $grid->column('id', 'ID')->sortable();
            $grid->column('name', '规则名称')->limit(30);
            $grid->column('coding_account_id', '账户')->display(function ($value) {
                if ($value) {
                    $account = CodingAccount::find($value);

                    return $account ? $account->name : '-';
                }

                return '全局规则';
            });
            $grid->column('driver_class', '驱动类')->display(function ($value) {
                if ($value) {
                    $parts = explode('\\', $value);

                    return end($parts);
                }

                return '-';
            });
            $grid->column('pattern_type', '匹配类型')->using(ChannelErrorRule::getPatternTypeOptions());
            $grid->column('pattern_value', '匹配值')->limit(20);
            $grid->column('action', '处理动作')->using(ChannelErrorRule::getActionOptions())->label([
                ChannelErrorRule::ACTION_PAUSE_ACCOUNT => 'warning',
                ChannelErrorRule::ACTION_ALERT_ONLY => 'info',
            ]);
            $grid->column('pause_duration_minutes', '暂停时长(分钟)');
            $grid->column('priority', '优先级')->sortable();
            $grid->column('is_enabled', '状态')->switch();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->panel();
                $filter->expand(true);

                $filter->like('name', '规则名称');
                $filter->equal('pattern_type', '匹配类型')->select(ChannelErrorRule::getPatternTypeOptions());
                $filter->equal('action', '处理动作')->select(ChannelErrorRule::getActionOptions());
                $filter->equal('is_enabled', '状态')->select([0 => '禁用', 1 => '启用']);
            });

            $grid->quickSearch(['id', 'name', 'pattern_value']);
        });
    }

    /**
     * 详情页面
     */
    protected function detail($id): Show
    {
        return Show::make(ChannelErrorRule::findOrFail($id), function (Show $show) {
            $show->field('id', 'ID');
            $show->field('name', '规则名称');
            $show->field('coding_account_id', '账户')->as(function ($value) {
                if ($value) {
                    $account = CodingAccount::find($value);

                    return $account ? $account->name : '-';
                }

                return '全局规则';
            });
            $show->field('driver_class', '驱动类');
            $show->field('pattern_type', '匹配类型')->using(ChannelErrorRule::getPatternTypeOptions());
            $show->field('pattern_value', '匹配值');
            $show->field('pattern_operator', '匹配方式')->using(ChannelErrorRule::getOperatorOptions());
            $show->field('action', '处理动作')->using(ChannelErrorRule::getActionOptions());
            $show->field('pause_duration_minutes', '暂停时长(分钟)');
            $show->field('priority', '优先级');
            $show->field('is_enabled', '状态')->using([0 => '禁用', 1 => '启用']);
            $show->field('metadata', '扩展配置')->json();
            $show->field('created_at', '创建时间');
            $show->field('updated_at', '更新时间');
        });
    }

    /**
     * 表单页面
     */
    protected function form(): Form
    {
        return Form::make(ChannelErrorRule::class, function (Form $form) {
            $form->display('id', 'ID');

            $form->text('name', '规则名称')->required()->maxLength(100);

            $form->select('coding_account_id', '绑定账户')
                ->options(CodingAccount::pluck('name', 'id'))
                ->help('留空则为全局规则，适用于所有账户');

            $form->text('driver_class', '驱动类')
                ->help('留空则为通用规则，或填写驱动类名如：App\\Services\\CodingStatus\\Drivers\\TokenCodingStatusDriver');

            $form->select('pattern_type', '匹配类型')
                ->options(ChannelErrorRule::getPatternTypeOptions())
                ->default(ChannelErrorRule::PATTERN_TYPE_STATUS_CODE)
                ->required();

            $form->text('pattern_value', '匹配值')
                ->required()
                ->help('如：429 或 "rate_limit" 或 "达到.*限额"');

            $form->select('pattern_operator', '匹配方式')
                ->options(ChannelErrorRule::getOperatorOptions())
                ->default(ChannelErrorRule::OPERATOR_EXACT);

            $form->select('action', '处理动作')
                ->options(ChannelErrorRule::getActionOptions())
                ->default(ChannelErrorRule::ACTION_PAUSE_ACCOUNT)
                ->required();

            $form->number('pause_duration_minutes', '暂停时长(分钟)')
                ->default(10)
                ->min(1)
                ->max(10080) // 最长7天
                ->help('账户暂停的时长，到达后自动恢复');

            $form->number('priority', '优先级')
                ->default(0)
                ->help('数字越大优先级越高，相同条件优先匹配高优先级规则');

            $form->switch('is_enabled', '是否启用')->default(true);

            $form->textarea('metadata', '扩展配置')
                ->rows(3)
                ->help('JSON格式的扩展配置');

            $form->display('created_at', '创建时间');
            $form->display('updated_at', '更新时间');
        });
    }
}
