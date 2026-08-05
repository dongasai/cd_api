<?php

namespace App\Admin\Widgets\Metrics\Demo;

use Dcat\Admin\Widgets\Metrics\Card;
use Illuminate\Http\Request;

/**
 * 总用户数卡片（Demo）
 */
class TotalUsers extends Card
{
    protected $footer;

    protected function init()
    {
        parent::init();

        $this->title('Total Users');
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
                $this->content(mt_rand(600, 1500));
                $this->down(mt_rand(1, 30));
                break;
            case '30':
                $this->content(mt_rand(170, 250));
                $this->up(mt_rand(12, 50));
                break;
            case '28':
                $this->content(mt_rand(155, 200));
                $this->up(mt_rand(5, 50));
                break;
            case '7':
            default:
                $this->content(143);
                $this->up(15);
        }
    }

    public function up($percent)
    {
        return $this->footer(
            "<i class=\"feather icon-trending-up text-success\"></i> {$percent}% Increase"
        );
    }

    public function down($percent)
    {
        return $this->footer(
            "<i class=\"feather icon-trending-down text-danger\"></i> {$percent}% Decrease"
        );
    }

    public function footer($footer)
    {
        $this->footer = $footer;

        return $this;
    }

    public function renderContent()
    {
        $content = parent::renderContent();

        return <<<HTML
<div class="d-flex justify-content-between align-items-center mt-1" style="margin-bottom: 2px">
    <h2 class="ml-1 font-lg-1">{$content}</h2>
</div>
<div class="ml-1 mt-1 font-weight-bold text-80">
    {$this->renderFooter()}
</div>
HTML;
    }

    public function renderFooter()
    {
        return $this->toString($this->footer);
    }
}
