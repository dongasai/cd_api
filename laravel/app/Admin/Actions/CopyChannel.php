<?php

namespace App\Admin\Actions;

use App\Models\Channel;
use Dcat\Admin\Grid\RowAction;

/**
 * 复制渠道操作
 */
class CopyChannel extends RowAction
{
    protected $title = '<i class="fa fa-copy"></i> 复制';

    /**
     * 处理复制逻辑
     */
    public function handle()
    {
        $id = $this->getKey();

        // 查找原渠道
        $originalChannel = Channel::with('channelModels')->find($id);
        if (! $originalChannel) {
            return $this->response()->error('渠道不存在');
        }

        // 复制渠道数据
        $newChannel = $originalChannel->replicate();
        $newChannel->name = $originalChannel->name.' (复制)';
        $newChannel->slug = $originalChannel->slug.'_copy_'.time();
        $newChannel->success_count = 0;
        $newChannel->failure_count = 0;
        $newChannel->total_requests = 0;
        $newChannel->total_tokens = 0;
        $newChannel->total_cost = 0;
        $newChannel->avg_latency_ms = 0;
        $newChannel->success_rate = 1.0;
        $newChannel->last_check_at = null;
        $newChannel->last_success_at = null;
        $newChannel->last_failure_at = null;
        $newChannel->save();

        // 复制渠道模型关联数据
        foreach ($originalChannel->channelModels as $channelModel) {
            $newChannelModel = $channelModel->replicate();
            $newChannelModel->channel_id = $newChannel->id;
            // 确保 multiplier 有默认值（处理 null、空字符串、0 等情况）
            if (empty($newChannelModel->multiplier)) {
                $newChannelModel->multiplier = 1.0000;
            }
            $newChannelModel->save();
        }

        return $this->response()->success('渠道复制成功')->refresh();
    }

    /**
     * 确认对话框
     */
    public function confirm()
    {
        return ['确认复制此渠道?', '将创建一个新的渠道副本，统计信息将重置为零。'];
    }
}
