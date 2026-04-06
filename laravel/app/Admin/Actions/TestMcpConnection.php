<?php

namespace App\Admin\Actions;

use App\Models\McpClient;
use App\Services\McpClientService;
use Dcat\Admin\Actions\Response;
use Dcat\Admin\Grid\RowAction;

/**
 * 测试 MCP 连接动作
 */
class TestMcpConnection extends RowAction
{
    /**
     * 动作标题
     *
     * @return string
     */
    public function title()
    {
        return '<i class="fa fa-plug"></i> 测试连接';
    }

    /**
     * 确认对话框
     *
     * @return string
     */
    public function confirm()
    {
        return ['确认测试连接?', '将尝试连接到 MCP Server 并获取能力信息'];
    }

    /**
     * 处理动作
     *
     * @return Response
     */
    public function handle()
    {
        $id = $this->getKey();
        $client = McpClient::find($id);

        if (! $client) {
            return $this->response()->error('客户端不存在');
        }

        try {
            $service = app(McpClientService::class);
            $result = $service->testConnection($client);

            if ($result['success']) {
                $capabilities = $result['capabilities'];
                $toolCount = count($capabilities['tools'] ?? []);
                $resourceCount = count($capabilities['resources'] ?? []);
                $promptCount = count($capabilities['prompts'] ?? []);

                return $this->response()
                    ->success("连接成功！发现 {$toolCount} 个工具, {$resourceCount} 个资源, {$promptCount} 个提示")
                    ->refresh();
            } else {
                return $this->response()->error('连接失败: '.$result['message']);
            }
        } catch (\Exception $e) {
            return $this->response()->error('连接异常: '.$e->getMessage());
        }
    }

    /**
     * 设置动作按钮样式
     *
     * @return string
     */
    public function html()
    {
        return <<<HTML
<a href="javascript:void(0);" class="{$this->getElementClass()}" style="margin-right: 5px;">
    {$this->title()}
</a>
HTML;
    }
}
