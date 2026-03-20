<?php

namespace App\Admin\Actions;

use Dcat\Admin\Grid\RowAction;

/**
 * 查看渠道请求日志操作
 */
class ViewChannelRequestLog extends RowAction
{
    public function title()
    {
        return admin_trans_action('view_channel_request_log').' &nbsp;';
    }

    public function href()
    {
        $channelRequestLogs = $this->row->channelRequestLogs;
        if (! $channelRequestLogs || $channelRequestLogs->isEmpty()) {
            return '#';
        }

        // 如果只有一个，跳转到详情页；否则跳转到列表页
        if ($channelRequestLogs->count() === 1) {
            return admin_url("channel-request-logs/{$channelRequestLogs->first()->id}");
        }

        return admin_url("channel-request-logs?audit_log_id={$this->getKey()}");
    }

    public function render()
    {
        if (! $this->row->channelRequestLogs || $this->row->channelRequestLogs->isEmpty()) {
            return '';
        }

        return parent::render();
    }
}
