<?php

namespace App\Admin\Actions\Channel;

use App\Models\ChannelModel;
use Dcat\Admin\Grid\RowAction;

/**
 * 复制渠道模型操作
 */
class CopyChannelModel extends RowAction
{
    /**
     * 显示按钮内容
     */
    public function title()
    {
        return '<i class="fa fa-copy"></i> '.admin_trans_action('copy_channel_model');
    }

    /**
     * 处理复制逻辑
     */
    public function handle()
    {
        $id = $this->getKey();

        // 查找原渠道模型
        $originalModel = ChannelModel::find($id);
        if (! $originalModel) {
            return $this->response()->error(admin_trans_action('channel_model_not_found'));
        }

        // 复制模型数据
        $newModel = $originalModel->replicate();

        // 处理 model_name 唯一性
        $baseName = $originalModel->model_name.'_copy';
        $newName = $baseName;
        $counter = 1;

        // 检查是否存在同名模型，如果存在则递增后缀
        while (ChannelModel::where('channel_id', $newModel->channel_id)
            ->where('model_name', $newName)
            ->exists()) {
            $newName = $baseName.'_'.$counter;
            $counter++;
        }

        $newModel->model_name = $newName;

        // 处理 display_name
        $newModel->display_name = ($originalModel->display_name ?? $originalModel->model_name).' (复制)';

        // 处理 is_default：如果原记录是默认模型，副本设为 false
        if ($originalModel->is_default) {
            $newModel->is_default = false;
        }

        // 确保 multiplier 有默认值
        if (empty($newModel->multiplier)) {
            $newModel->multiplier = 1.0000;
        }

        $newModel->save();

        return $this->response()->success(admin_trans_action('channel_model_copy_success'))->refresh();
    }

    /**
     * 确认对话框
     */
    public function confirm()
    {
        return [admin_trans_action('channel_model_copy_confirm'), admin_trans_action('channel_model_copy_confirm_desc')];
    }
}
