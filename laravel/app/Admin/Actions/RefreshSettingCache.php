<?php

namespace App\Admin\Actions;

use App\Services\SettingService;
use Dcat\Admin\Actions\Response;
use Dcat\Admin\Grid\Tools\AbstractTool;

/**
 * 刷新设置缓存操作
 */
class RefreshSettingCache extends AbstractTool
{
    /**
     * 处理请求
     *
     * @return Response
     */
    public function handle()
    {
        try {
            // 清除系统设置缓存
            app(SettingService::class)->clearCache();

            return $this->response()->success(admin_trans_action('setting_cache_refreshed'))->refresh();
        } catch (\Exception $e) {
            return $this->response()->error(admin_trans_action('refresh_failed').': '.$e->getMessage());
        }
    }

    /**
     * 确认对话框
     *
     * @return array
     */
    public function confirm()
    {
        return [admin_trans_action('refresh_setting_cache_confirm')];
    }

    /**
     * 设置按钮样式
     *
     * @return string
     */
    public function html()
    {
        $title = admin_trans_action('refresh_setting_cache');

        return <<<HTML
<a class="{$this->getElementClass()} btn btn-primary btn-sm" href="javascript:void(0);">
    <i class="feather icon-refresh-cw"></i> {$title}
</a>
HTML;
    }
}
