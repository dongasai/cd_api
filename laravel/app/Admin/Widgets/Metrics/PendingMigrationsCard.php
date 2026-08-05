<?php

namespace App\Admin\Widgets\Metrics;

use Dcat\Admin\Widgets\Metrics\Card;
use Illuminate\Support\Facades\Artisan;

/**
 * 待执行迁移提醒卡片
 */
class PendingMigrationsCard extends Card
{
    /**
     * 初始化
     */
    protected function init()
    {
        parent::init();
        $this->title('待执行迁移');
        $this->style('danger');
    }

    /**
     * 渲染卡片
     */
    public function render()
    {
        $this->fill();

        return parent::render();
    }

    /**
     * 填充数据
     */
    public function fill()
    {
        $pendingCount = $this->getPendingCount();
        $this->withContent($pendingCount);
    }

    /**
     * 是否有待执行迁移
     */
    public function hasPending(): bool
    {
        return $this->getPendingCount() > 0;
    }

    /**
     * 获取待执行迁移数量
     */
    protected function getPendingCount(): int
    {
        Artisan::call('migrate:status');
        $output = Artisan::output();

        return substr_count($output, 'Pending');
    }

    /**
     * 设置卡片内容
     */
    public function withContent(int $count)
    {
        $url = admin_url('migrations');

        $html = <<<HTML
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h2 class="font-weight-bold text-danger">{$count}</h2>
        <p class="mb-0 text-muted">个迁移待执行</p>
    </div>
    <a href="{$url}" class="btn btn-danger btn-sm">
        <i class="fa fa-play"></i> 执行迁移
    </a>
</div>
HTML;

        return $this->content($html);
    }
}
