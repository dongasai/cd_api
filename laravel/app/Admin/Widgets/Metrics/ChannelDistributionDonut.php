<?php

namespace App\Admin\Widgets\Metrics;

use App\Models\AuditLog;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Metrics\Donut;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 渠道请求分布环形图卡片
 */
class ChannelDistributionDonut extends Donut
{
    protected function init()
    {
        parent::init();

        $color = Admin::color();

        $this->title('渠道请求分布');
        $this->subTitle('今日');
        $this->chartLabels([]);
        $this->chartColors([$color->primary(), $color->alpha('blue2', 0.5), $color->orange2()]);
    }

    public function render()
    {
        $this->fill();

        return parent::render();
    }

    public function fill()
    {
        $today = Carbon::today();
        $channelStats = AuditLog::whereDate('created_at', $today)
            ->select('channel_name', DB::raw('count(*) as request_count'))
            ->whereNotNull('channel_name')
            ->groupBy('channel_name')
            ->orderBy('request_count', 'desc')
            ->limit(8)
            ->get();

        $labels = $channelStats->pluck('channel_name')->toArray();
        $counts = $channelStats->pluck('request_count')->toArray();

        $this->chartLabels($labels);

        $color = Admin::color();
        $palette = [
            $color->primary(), $color->alpha('blue2', 0.5), $color->orange2(),
            $color->success(), $color->danger(), $color->info(),
            $color->warning(), $color->dark35(),
        ];
        $this->chartColors(array_slice($palette, 0, count($labels)));

        $this->withContent($labels, $counts);
        $this->withChart($counts);
    }

    public function withChart(array $data)
    {
        return $this->chart(['series' => $data]);
    }

    public function withContent(array $labels, array $values)
    {
        $color = Admin::color();
        $palette = [
            $color->primary(), $color->alpha('blue2', 0.5), $color->orange2(),
            $color->success(), $color->danger(), $color->info(),
            $color->warning(), $color->dark35(),
        ];

        $items = '';
        $labelWidth = 120;
        $style = 'margin-bottom: 8px';

        foreach ($labels as $i => $label) {
            $c = $palette[$i % count($palette)];
            $items .= <<<HTML
<div class="d-flex pl-1 pr-1" style="{$style}">
    <div style="width: {$labelWidth}px">
        <i class="fa fa-circle" style="color: {$c}"></i> {$label}
    </div>
    <div>{$values[$i]}</div>
</div>
HTML;
        }

        return $this->content($items);
    }
}
