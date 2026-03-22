<?php

namespace App\Admin\Actions;

use Dcat\Admin\Grid\RowAction;

/**
 * 比对请求差异操作
 */
class CompareRequestDiff extends RowAction
{
    public function title()
    {
        return admin_trans_action('compare_request_diff').' &nbsp;';
    }

    public function href()
    {
        // 检查是否存在 channel_request_logs
        $channelLogs = $this->row->channelRequestLogs;
        if (! $channelLogs || $channelLogs->isEmpty()) {
            return '#';
        }

        return admin_url("request-diff/{$this->getKey()}");
    }

    public function render()
    {
        // 检查是否存在 channel_request_logs
        $channelLogs = $this->row->channelRequestLogs;
        if (! $channelLogs || $channelLogs->isEmpty()) {
            return '';
        }

        // 使用 target="_blank" 在新标签页打开
        $href = $this->href();

        return "<a href=\"{$href}\" target=\"_blank\">{$this->title()}</a>";
    }
}
