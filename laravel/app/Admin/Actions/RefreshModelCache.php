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
     * 按钮标题
     *
     * @var string
     */
    protected $title = '刷新模型缓存';

    /**
     * 确认提示信息
     *
     * @var string
     */
    protected $confirmMessage = '确定要刷新模型缓存吗？这将清除当前API密钥的所有模型相关缓存。';

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

            return $this->response()->success('模型缓存已刷新')->refresh();
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
<a class="{$this->getElementClass()}" href="javascript:void(0);">
    <i class="feather icon-refresh-cw"></i> {$this->title}
</a>
HTML;
    }
}
