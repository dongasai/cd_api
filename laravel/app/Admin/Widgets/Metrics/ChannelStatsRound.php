<?php

namespace App\Admin\Widgets\Metrics;

use App\Enums\ChannelStatus;
use App\Models\Channel;
use Dcat\Admin\Widgets\Metrics\Round;

/**
 * 渠道统计环形图卡片
 */
class ChannelStatsRound extends Round
{
    protected function init()
    {
        parent::init();

        $this->title('渠道统计');
        $this->chartLabels(['启用', '禁用']);
    }

    public function render()
    {
        $this->fill();

        return parent::render();
    }

    public function fill()
    {
        $total = Channel::count();
        $active = Channel::where('status', ChannelStatus::ACTIVE)->count();
        $disabled = Channel::where('status', ChannelStatus::DISABLED)->count();

        $activePercent = $total > 0 ? round($active / $total * 100) : 0;
        $disabledPercent = $total > 0 ? round($disabled / $total * 100) : 0;

        $this->withContent($active, $disabled);
        $this->withChart([$activePercent, $disabledPercent]);
        $this->chartTotal('渠道总数', $total);
    }

    public function withChart(array $data)
    {
        return $this->chart(['series' => $data]);
    }

    public function withContent(int $active, int $disabled)
    {
        return $this->content(
            <<<HTML
<div class="col-12 d-flex flex-column flex-wrap text-center" style="max-width: 220px">
    <div class="chart-info d-flex justify-content-between mb-1 mt-2">
        <div class="series-info d-flex align-items-center">
            <i class="fa fa-circle-o text-bold-700 text-primary"></i>
            <span class="text-bold-600 ml-50">启用</span>
        </div>
        <div class="product-result">
            <span>{$active}</span>
        </div>
    </div>
    <div class="chart-info d-flex justify-content-between mb-1">
        <div class="series-info d-flex align-items-center">
            <i class="fa fa-circle-o text-bold-700 text-warning"></i>
            <span class="text-bold-600 ml-50">禁用</span>
        </div>
        <div class="product-result">
            <span>{$disabled}</span>
        </div>
    </div>
</div>
HTML
        );
    }
}
