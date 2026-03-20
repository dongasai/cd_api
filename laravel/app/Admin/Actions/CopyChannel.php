<?php

namespace App\Admin\Actions;

use App\Models\Channel;
use Dcat\Admin\Grid\RowAction;

/**
 * 复制渠道操作
 */
class CopyChannel extends RowAction
{
    public function title()
    {
        return '<i class="fa fa-copy"></i> '.admin_trans_action('copy_channel');
    }

    /**
     * 处理复制逻辑
     */
    public function handle()
    {
        $id = $this->getKey();

        // 查找原渠道
        $originalChannel = Channel::with('channelModels')->find($id);
        if (! $originalChannel) {
            return $this->response()->error(admin_trans_action('channel_not_found'));
        }

        // 复制渠道数据
        $newChannel = $originalChannel->replicate();
        $newChannel->name = $originalChannel->name.' ('.admin_trans_action('copy_channel').')';
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

        return $this->response()->success(admin_trans_action('channel_copy_success'))->refresh();
    }

    /**
     * 确认对话框
     */
    public function confirm()
    {
        return [admin_trans_action('channel_copy_confirm'), admin_trans_action('channel_copy_confirm_desc')];
    }
}
