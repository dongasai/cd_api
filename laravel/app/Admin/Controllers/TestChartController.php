<?php

namespace App\Admin\Controllers;

use App\Admin\Widgets\DemoCharts\AreaChartWidget;
use App\Admin\Widgets\DemoCharts\BarChartWidget;
use App\Admin\Widgets\DemoCharts\ColumnChartWidget;
use App\Admin\Widgets\DemoCharts\DonutChartWidget;
use App\Admin\Widgets\DemoCharts\HeatmapChartWidget;
use App\Admin\Widgets\DemoCharts\HorizontalBarChartWidget;
use App\Admin\Widgets\DemoCharts\LineChartWidget;
use App\Admin\Widgets\DemoCharts\PieChartWidget;
use App\Admin\Widgets\DemoCharts\RadarChartWidget;
use App\Admin\Widgets\DemoCharts\RangeBarChartWidget;
use App\Admin\Widgets\DemoCharts\ScatterChartWidget;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;

/**
 * 测试图表控制器
 *
 * 展示各种 ApexCharts 图表类型
 */
class TestChartController
{
    public function index(Content $content)
    {
        return $content
            ->header('图表测试')
            ->description('ApexCharts 各种图表类型示例')
            ->body(function (Row $row) {
                // 第一行：饼图、环形图、折线图
                $row->column(4, Card::make('饼图', PieChartWidget::make()));
                $row->column(4, Card::make('环形图', DonutChartWidget::make()));
                $row->column(4, Card::make('折线图', LineChartWidget::make()));

                // 第二行：柱状图、面积图、雷达图
                $row->column(4, Card::make('柱状图', BarChartWidget::make()));
                $row->column(4, Card::make('面积图', AreaChartWidget::make()));
                $row->column(4, Card::make('雷达图', RadarChartWidget::make()));

                // 第三行：散点图、堆叠柱状图、热力图
                $row->column(4, Card::make('散点图', ScatterChartWidget::make()));
                $row->column(4, Card::make('堆叠柱状图', ColumnChartWidget::make()));
                $row->column(4, Card::make('热力图', HeatmapChartWidget::make()));

                // 第四行：横向柱状图、范围条形图
                $row->column(4, Card::make('横向柱状图', HorizontalBarChartWidget::make()));
                $row->column(4, Card::make('范围条形图', RangeBarChartWidget::make()));
            });
    }
}
