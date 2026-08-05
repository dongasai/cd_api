<?php

namespace App\Admin\Widgets\Metrics\Demo;

use Dcat\Admin\Widgets\Metrics\RadialBar;
use Illuminate\Http\Request;

/**
 * 工单圆形图卡片（Demo）
 */
class Tickets extends RadialBar
{
    protected function init()
    {
        parent::init();

        $this->title('Tickets');
        $this->height(400);
        $this->chartHeight(300);
        $this->chartLabels('Completed Tickets');
        $this->dropdown([
            '7' => 'Last 7 Days',
            '28' => 'Last 28 Days',
            '30' => 'Last Month',
            '365' => 'Last Year',
        ]);
    }

    public function handle(Request $request)
    {
        switch ($request->get('option')) {
            case '365':
            case '30':
            case '28':
            case '7':
            default:
                $this->withContent(162);
                $this->withFooter(29, 63, '1d');
                $this->withChart(83);
        }
    }

    public function withChart(int $data)
    {
        return $this->chart([
            'series' => [$data],
        ]);
    }

    public function withContent($content)
    {
        return $this->content(
            <<<HTML
<div class="d-flex flex-column flex-wrap text-center">
    <h1 class="font-lg-2 mt-2 mb-0">{$content}</h1>
    <small>Tickets</small>
</div>
HTML
        );
    }

    public function withFooter($new, $open, $response)
    {
        return $this->footer(
            <<<HTML
<div class="d-flex justify-content-between p-1" style="padding-top: 0!important;">
    <div class="text-center">
        <p>New Tickets</p>
        <span class="font-lg-1">{$new}</span>
    </div>
    <div class="text-center">
        <p>Open Tickets</p>
        <span class="font-lg-1">{$open}</span>
    </div>
    <div class="text-center">
        <p>Response Time</p>
        <span class="font-lg-1">{$response}</span>
    </div>
</div>
HTML
        );
    }
}
