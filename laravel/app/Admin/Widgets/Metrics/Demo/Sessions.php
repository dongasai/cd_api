<?php

namespace App\Admin\Widgets\Metrics\Demo;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Metrics\Bar;
use Illuminate\Http\Request;

/**
 * 平均会话柱状图卡片（Demo）
 */
class Sessions extends Bar
{
    protected function init()
    {
        parent::init();

        $color = Admin::color();
        $dark35 = $color->dark35();

        $this->contentWidth(5, 7);
        $this->title('Avg Sessions');
        $this->dropdown([
            '7' => 'Last 7 Days',
            '28' => 'Last 28 Days',
            '30' => 'Last Month',
            '365' => 'Last Year',
        ]);
        $this->chartColors([
            $dark35, $dark35, $color->primary(), $dark35, $dark35, $dark35,
        ]);
    }

    public function handle(Request $request)
    {
        switch ($request->get('option')) {
            case '7':
            default:
                $this->withContent('2.7k', '+5.2%');
                $this->withChart([
                    [
                        'name' => 'Sessions',
                        'data' => [75, 125, 225, 175, 125, 75, 25],
                    ],
                ]);
        }
    }

    public function withChart(array $data)
    {
        return $this->chart(['series' => $data]);
    }

    public function withContent($title, $value, $style = 'success')
    {
        $label = strtolower(
            $this->dropdown[request()->option] ?? 'last 7 days'
        );

        $minHeight = '183px';

        return $this->content(
            <<<HTML
<div class="d-flex p-1 flex-column justify-content-between" style="padding-top: 0;width: 100%;height: 100%;min-height: {$minHeight}">
    <div class="text-left">
        <h1 class="font-lg-2 mt-2 mb-0">{$title}</h1>
        <h5 class="font-medium-2" style="margin-top: 10px;">
            <span class="text-{$style}">{$value} </span>
            <span>vs {$label}</span>
        </h5>
    </div>

    <a href="#" class="btn btn-primary shadow waves-effect waves-light">View Details <i class="feather icon-chevrons-right"></i></a>
</div>
HTML
        );
    }
}
