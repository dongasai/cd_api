<?php

namespace App\Admin\Actions;

use App\Models\ChannelAffinityRule;
use Dcat\Admin\Grid\RowAction;

/**
 * 复制渠道亲和性规则操作
 */
class CopyChannelAffinityRule extends RowAction
{
    public function title()
    {
        return '<i class="fa fa-copy"></i> '.admin_trans_action('copy_channel_affinity_rule');
    }

    /**
     * 处理复制逻辑
     */
    public function handle()
    {
        $id = $this->getKey();

        // 查找原规则
        $originalRule = ChannelAffinityRule::find($id);
        if (! $originalRule) {
            return $this->response()->error(admin_trans_action('rule_not_found'));
        }

        // 复制规则数据
        $newRule = $originalRule->replicate();
        $newRule->name = $originalRule->name.' ('.admin_trans_action('copy_channel_affinity_rule').')';
        $newRule->hit_count = 0;
        $newRule->last_hit_at = null;
        $newRule->save();

        return $this->response()->success(admin_trans_action('rule_copy_success'))->refresh();
    }

    /**
     * 确认对话框
     */
    public function confirm()
    {
        return [admin_trans_action('rule_copy_confirm'), admin_trans_action('rule_copy_confirm_desc')];
    }
}
