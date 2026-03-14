<?php

namespace App\Admin\Actions;

use App\Models\ChannelAffinityRule;
use Dcat\Admin\Grid\RowAction;

/**
 * 复制渠道亲和性规则操作
 */
class CopyChannelAffinityRule extends RowAction
{
    protected $title = '<i class="fa fa-copy"></i> 复制';

    /**
     * 处理复制逻辑
     */
    public function handle()
    {
        $id = $this->getKey();

        // 查找原规则
        $originalRule = ChannelAffinityRule::find($id);
        if (! $originalRule) {
            return $this->response()->error('规则不存在');
        }

        // 复制规则数据
        $newRule = $originalRule->replicate();
        $newRule->name = $originalRule->name.' (复制)';
        $newRule->hit_count = 0;
        $newRule->last_hit_at = null;
        $newRule->save();

        return $this->response()->success('规则复制成功')->refresh();
    }

    /**
     * 确认对话框
     */
    public function confirm()
    {
        return ['确认复制此规则?', '将创建一个新的规则副本，命中统计将重置为零。'];
    }
}