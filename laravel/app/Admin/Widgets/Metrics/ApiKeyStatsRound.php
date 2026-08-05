<?php

namespace App\Admin\Widgets\Metrics;

use App\Models\ApiKey;
use Dcat\Admin\Widgets\Metrics\Round;

/**
 * API密钥统计环形图卡片
 */
class ApiKeyStatsRound extends Round
{
    protected function init()
    {
        parent::init();

        $this->title('API密钥统计');
        $this->chartLabels(['活跃', '过期']);
    }

    public function render()
    {
        $this->fill();

        return parent::render();
    }

    public function fill()
    {
        $total = ApiKey::count();
        $active = ApiKey::where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();
        $expired = ApiKey::where('expires_at', '<', now())->count();

        $activePercent = $total > 0 ? round($active / $total * 100) : 0;
        $expiredPercent = $total > 0 ? round($expired / $total * 100) : 0;

        $this->withContent($active, $expired);
        $this->withChart([$activePercent, $expiredPercent]);
        $this->chartTotal('密钥总数', $total);
    }

    public function withChart(array $data)
    {
        return $this->chart(['series' => $data]);
    }

    public function withContent(int $active, int $expired)
    {
        return $this->content(
            <<<HTML
<div class="col-12 d-flex flex-column flex-wrap text-center" style="max-width: 220px">
    <div class="chart-info d-flex justify-content-between mb-1 mt-2">
        <div class="series-info d-flex align-items-center">
            <i class="fa fa-circle-o text-bold-700 text-primary"></i>
            <span class="text-bold-600 ml-50">活跃</span>
        </div>
        <div class="product-result">
            <span>{$active}</span>
        </div>
    </div>
    <div class="chart-info d-flex justify-content-between mb-1">
        <div class="series-info d-flex align-items-center">
            <i class="fa fa-circle-o text-bold-700 text-danger"></i>
            <span class="text-bold-600 ml-50">过期</span>
        </div>
        <div class="product-result">
            <span>{$expired}</span>
        </div>
    </div>
</div>
HTML
        );
    }
}
