<?php

namespace App\Admin\Actions;

use Dcat\Admin\Grid\RowAction;

/**
 * 查看返回日志操作
 */
class ViewResponseLog extends RowAction
{
    public function title()
    {
        return admin_trans_action('view_response_log').' &nbsp;';
    }

    public function href()
    {
        $responseLog = $this->row->responseLog;
        if (! $responseLog) {
            return '#';
        }

        return admin_url("response-logs/{$responseLog->id}");
    }

    public function render()
    {
        if (! $this->row->responseLog) {
            return '';
        }

        return parent::render();
    }
}
