<?php

namespace App\Admin\Actions;

use App\Models\ApiKey;
use App\Services\ModelService;
use App\Services\SettingService;
use Dcat\Admin\Grid\RowAction;
use Illuminate\Support\Str;

/**
 * 重置API密钥操作
 */
class ResetApiKey extends RowAction
{
    /**
     * 按钮标题
     *
     * @var string
     */
    protected $title = '<i class="feather icon-key"></i> 重置密钥';

    /**
     * 处理请求
     */
    public function handle()
    {
        $id = $this->getKey();

        try {
            $apiKey = ApiKey::find($id);
            if (! $apiKey) {
                return $this->response()->error('API密钥不存在');
            }

            // 生成新密钥
            $newKey = $this->generateApiKey();
            $apiKey->key = $newKey;
            $apiKey->save();

            // 清除模型缓存
            ModelService::clearCache((int) $id);

            // 返回成功响应，并显示新密钥
            return $this->response()
                ->success("密钥重置成功！新密钥：\n\n{$newKey}\n\n请妥善保存，此密钥只会显示一次！")
                ->refresh();
        } catch (\Exception $e) {
            return $this->response()->error('重置失败: '.$e->getMessage());
        }
    }

    /**
     * 确认对话框
     */
    public function confirm()
    {
        return ['确认重置此API密钥?', '重置后原密钥将立即失效，新密钥将显示一次，请妥善保存。'];
    }

    /**
     * 生成API密钥
     */
    protected function generateApiKey(): string
    {
        // 获取系统配置的密钥前缀，默认为 cdapi-
        $prefix = app(SettingService::class)->get('security.api_key_prefix', 'cdapi-');

        return $prefix.Str::random(48);
    }
}
