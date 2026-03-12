<?php

namespace App\Admin\Actions;

use Dcat\Admin\Grid\RowAction;

/**
 * 查看请求日志操作
 */
class ViewRequestLog extends RowAction
{
    protected $title = '请求日志 &nbsp;';

    public function href()
    {
        $requestLog = $this->row->requestLog;
        if (! $requestLog) {
            return '#';
        }

        return admin_url("request-logs/{$requestLog->id}");
    }

    public function render()
    {
        if (! $this->row->requestLog) {
            return '';
        }

        return parent::render();
    }
}
