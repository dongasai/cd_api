<?php

namespace App\Admin\Widgets\Metrics\Demo;

use Dcat\Admin\Widgets\Metrics\Line;
use Illuminate\Http\Request;

/**
 * 新用户数折线图卡片（Demo）
 */
class NewUsers extends Line
{
    protected function init()
    {
        parent::init();

        $this->title('New Users');
        $this->dropdown([
            '7' => 'Last 7 Days',
            '28' => 'Last 28 Days',
            '30' => 'Last Month',
            '365' => 'Last Year',
        ]);
    }

    public function handle(Request $request)
    {
        $generator = function ($len, $min = 10, $max = 300) {
            for ($i = 0; $i <= $len; $i++) {
                yield mt_rand($min, $max);
            }
        };

        switch ($request->get('option')) {
            case '365':
                $this->withContent(mt_rand(1000, 5000).'k');
                $this->withChart(collect($generator(30))->toArray());
                break;
            case '30':
                $this->withContent(mt_rand(400, 1000).'k');
                $this->withChart(collect($generator(30))->toArray());
                break;
            case '28':
                $this->withContent(mt_rand(400, 1000).'k');
                $this->withChart(collect($generator(28))->toArray());
                break;
            case '7':
            default:
                $this->withContent('89.2k');
                $this->withChart([28, 40, 36, 52, 38, 60, 55]);
        }
    }

    public function withChart(array $data)
    {
        return $this->chart([
            'series' => [
                [
                    'name' => $this->title,
                    'data' => $data,
                ],
            ],
        ]);
    }

    public function withContent($content)
    {
        return $this->content(
            <<<HTML
<div class="d-flex justify-content-between align-items-center mt-1" style="margin-bottom: 2px">
    <h2 class="ml-1 font-lg-1">{$content}</h2>
    <span class="mb-0 mr-1 text-80">{$this->title}</span>
</div>
HTML
        );
    }
}
