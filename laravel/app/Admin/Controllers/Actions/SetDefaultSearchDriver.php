<?php

namespace App\Admin\Controllers\Actions;

use App\Models\SearchDriver;
use Dcat\Admin\Actions\Response;
use Dcat\Admin\Grid\RowAction;

/**
 * 设置默认搜索驱动
 */
class SetDefaultSearchDriver extends RowAction
{
    /**
     * 按钮标题
     *
     * @return string
     */
    public function title()
    {
        return '设为默认';
    }

    /**
     * 处理请求
     *
     * @return Response
     */
    public function handle()
    {
        $id = $this->getKey();

        $driver = SearchDriver::findOrFail($id);

        // 清除其他默认
        SearchDriver::where('is_default', true)->update(['is_default' => false]);

        // 设置当前为默认
        $driver->is_default = true;
        $driver->status = SearchDriver::STATUS_ACTIVE;
        $driver->save();

        return $this->response()
            ->success('已设置 '.$driver->name.' 为默认驱动')
            ->refresh();
    }

    /**
     * 确认对话框
     *
     * @return array|string
     */
    public function confirm()
    {
        return ['确认设置 '.$this->row->name.' 为默认驱动？', '将清除其他默认设置'];
    }

    /**
     * 仅对非默认驱动显示
     *
     * @return bool
     */
    public function allowed()
    {
        return ! $this->row->is_default;
    }
}
