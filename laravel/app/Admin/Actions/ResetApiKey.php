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
    protected $title;

    public function __construct()
    {
        $this->title = '<i class="feather icon-key"></i> '.trans('admin-actions.reset_api_key');
    }

    /**
     * 处理请求
     */
    public function handle()
    {
        $id = $this->getKey();

        try {
            $apiKey = ApiKey::find($id);
            if (! $apiKey) {
                return $this->response()->error(trans('admin-actions.api_key_not_found'));
            }

            // 生成新密钥
            $newKey = $this->generateApiKey();
            $apiKey->key = $newKey;
            $apiKey->save();

            // 清除模型缓存
            ModelService::clearCache((int) $id);

            // 返回成功响应，并显示新密钥
            return $this->response()
                ->success(trans('admin-actions.reset_api_key_success')."\n\n{$newKey}\n\n".trans('admin-actions.reset_api_key_warning'))
                ->refresh();
        } catch (\Exception $e) {
            return $this->response()->error(trans('admin-actions.reset_api_key_error').': '.$e->getMessage());
        }
    }

    /**
     * 确认对话框
     */
    public function confirm()
    {
        return [
            trans('admin-actions.reset_api_key_confirm'),
            trans('admin-actions.reset_api_key_confirm_desc'),
        ];
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
