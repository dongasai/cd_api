<?php

namespace App\Admin\Actions;

use App\Services\ModelService;
use Dcat\Admin\Actions\Response;
use Dcat\Admin\Show\AbstractTool;

/**
 * 刷新模型缓存操作
 */
class RefreshModelCache extends AbstractTool
{
    /**
     * 处理请求
     *
     * @return Response
     */
    public function handle()
    {
        $id = $this->getKey();

        try {
            // 清除指定 API Key 的模型缓存
            ModelService::clearCache((int) $id);

            return $this->response()->success(admin_trans_action('model_cache_refreshed'))->refresh();
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
        return [admin_trans_action('refresh_model_cache_confirm')];
    }

    /**
     * 设置按钮样式
     *
     * @return string
     */
    public function html()
    {
        $title = admin_trans_action('refresh_model_cache');

        return <<<HTML
<a class="{$this->getElementClass()}" href="javascript:void(0);">
    <i class="feather icon-refresh-cw"></i> {$title}
</a>
HTML;
    }
}
