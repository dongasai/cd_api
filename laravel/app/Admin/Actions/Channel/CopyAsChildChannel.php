<?php

namespace App\Admin\Actions\Channel;

use App\Models\Channel;
use App\Services\ChannelInheritance\ChannelInheritanceResolver;
use Dcat\Admin\Grid\RowAction;

/**
 * 复制为子渠道操作
 *
 * 将当前渠道复制为子渠道，自动设置 parent_id 为当前渠道 ID，
 * inherit_mode 默认为 merge（合并继承）。
 */
class CopyAsChildChannel extends RowAction
{
    public function title()
    {
        return '<i class="fa fa-code-branch"></i> '.admin_trans_action('copy_as_child_channel');
    }

    /**
     * 处理复制为子渠道逻辑
     */
    public function handle()
    {
        $parentId = $this->getKey();

        // 查找父渠道
        $parentChannel = Channel::with('channelModels')->find($parentId);
        if (! $parentChannel) {
            return $this->response()->error(admin_trans_action('channel_not_found'));
        }

        // 检查父渠道继承深度是否超限
        if ($parentChannel->getInheritanceDepth() >= ChannelInheritanceResolver::MAX_DEPTH - 1) {
            return $this->response()->error('父渠道继承深度已达上限，无法创建子渠道');
        }

        // 检查是否已有子渠道
        $existingChildren = Channel::where('parent_id', $parentId)->count();
        $copySuffix = $existingChildren > 0 ? '_child_'.($existingChildren + 1) : '_child';

        // 复制渠道数据
        $newChannel = $parentChannel->replicate();
        $newChannel->parent_id = $parentId;  // 设置父渠道 ID
        $newChannel->inherit_mode = 'merge'; // 默认使用合并继承
        $newChannel->name = $parentChannel->name.' ('.admin_trans_action('child_channel').')';
        $newChannel->slug = $parentChannel->slug.$copySuffix.'_'.time();

        // 重置统计信息
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

        // 重置健康状态
        $newChannel->status2 = 'normal';
        $newChannel->status2_remark = null;

        $newChannel->save();

        // 复制渠道模型关联数据
        foreach ($parentChannel->channelModels as $channelModel) {
            $newChannelModel = $channelModel->replicate();
            $newChannelModel->channel_id = $newChannel->id;
            // 确保 multiplier 有默认值
            if (empty($newChannelModel->multiplier)) {
                $newChannelModel->multiplier = 1.0000;
            }
            $newChannelModel->save();
        }

        return $this->response()
            ->success(admin_trans_action('child_channel_copy_success', [
                'parent' => $parentChannel->name,
                'child' => $newChannel->name,
            ]))
            ->refresh();
    }

    /**
     * 确认对话框
     */
    public function confirm()
    {
        return [
            admin_trans_action('copy_as_child_channel_confirm'),
            admin_trans_action('copy_as_child_channel_desc'),
        ];
    }
}
