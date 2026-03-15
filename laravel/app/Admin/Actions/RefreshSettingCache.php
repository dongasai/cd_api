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
     * 按钮标题
     *
     * @var string
     */
    protected $title = '刷新缓存';

    /**
     * 确认提示信息
     *
     * @var string
     */
    protected $confirmMessage = '确定要刷新系统设置缓存吗？这将清除所有设置缓存并重新加载。';

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

            return $this->response()->success('系统设置缓存已刷新')->refresh();
        } catch (\Exception $e) {
            return $this->response()->error('刷新失败: '.$e->getMessage());
        }
    }

    /**
     * 确认对话框
     *
     * @return array
     */
    public function confirm()
    {
        return [$this->confirmMessage];
    }

    /**
     * 设置按钮样式
     *
     * @return string
     */
    public function html()
    {
        return <<<HTML
<a class="{$this->getElementClass()} btn btn-primary btn-sm" href="javascript:void(0);">
    <i class="feather icon-refresh-cw"></i> {$this->title}
</a>
HTML;
    }
}
