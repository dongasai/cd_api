<?php

namespace App\Admin\Actions;

use Dcat\Admin\Grid\RowAction;

/**
 * 查看返回日志操作
 */
class ViewResponseLog extends RowAction
{
    protected $title = '返回日志 &nbsp;';

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
