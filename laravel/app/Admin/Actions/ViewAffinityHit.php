<?php

namespace App\Admin\Actions;

use App\Models\ChannelAffinityCache;
use Dcat\Admin\Grid\RowAction;

/**
 * 查看亲和性命中详情操作
 */
class ViewAffinityHit extends RowAction
{
    protected $title = '亲和性命中 &nbsp;';

    public function href()
    {
        $affinity = $this->row->channel_affinity;

        // 如果没有亲和性信息或没有命中，返回空链接
        if (empty($affinity) || empty($affinity['is_affinity_hit'])) {
            return '#';
        }

        // 获取规则ID和Key哈希
        $ruleId = $affinity['rule_id'] ?? null;
        $keyHash = $affinity['key_hash'] ?? null;

        if (empty($ruleId) || empty($keyHash)) {
            // 如果没有规则ID或Key哈希，回退到JSON预览
            return admin_url("json-preview/audit-logs/{$this->row->id}/channel_affinity");
        }

        // 查找对应的缓存记录
        $cache = ChannelAffinityCache::where('rule_id', $ruleId)
            ->where('key_hash', $keyHash)
            ->first();

        if ($cache) {
            // 找到缓存记录，跳转到缓存详情页
            return admin_url("channel-affinity-cache/{$cache->id}");
        } else {
            // 没有找到缓存记录，回退到JSON预览
            return admin_url("json-preview/audit-logs/{$this->row->id}/channel_affinity");
        }
    }

    public function render()
    {
        $affinity = $this->row->channel_affinity;
        if (empty($affinity) || empty($affinity['is_affinity_hit'])) {
            return '';
        }

        return parent::render();
    }
}
