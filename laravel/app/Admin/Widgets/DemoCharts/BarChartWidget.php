<?php

namespace App\Admin\Widgets\DemoCharts;

use Dcat\Admin\Widgets\ApexCharts\Chart;

/**
 * 柱状图组件
 */
class BarChartWidget extends Chart
{
    public function __construct($containerSelector = null, $options = [])
    {
        parent::__construct($containerSelector, $options);

        $this->setUpOptions();
    }

    /**
     * 初始化图表配置
     */
    protected function setUpOptions()
    {
        $this->options([
            'chart' => [
                'type' => 'bar',
                'height' => 350,
            ],
            'plotOptions' => [
                'bar' => [
                    'horizontal' => false,
                    'columnWidth' => '55%',
                    'endingShape' => 'rounded',
                ],
            ],
            'dataLabels' => [
                'enabled' => false,
            ],
            'legend' => [
                'position' => 'top',
            ],
            'colors' => ['#5c6bc0', '#ef5350'],
            'xaxis' => [
                'categories' => [],
            ],
            'yaxis' => [
                'title' => ['text' => '金额 (元)'],
            ],
        ]);
    }

    /**
     * 处理图表数据
     */
    protected function buildData()
    {
        $data = [
            [
                'name' => '收入',
                'data' => [44, 55, 57, 56, 61, 58, 63, 60, 66],
            ],
            [
                'name' => '支出',
                'data' => [76, 85, 101, 98, 87, 105, 91, 114, 94],
            ],
        ];
        $categories = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月'];

        $this->withData($data);
        $this->withCategories($categories);
    }

    /**
     * 设置图表数据
     *
     * @return $this
     */
    public function withData(array $data)
    {
        return $this->option('series', $data);
    }

    /**
     * 设置图表类别
     *
     * @return $this
     */
    public function withCategories(array $data)
    {
        return $this->option('xaxis.categories', $data);
    }

    /**
     * 渲染图表
     *
     * @return string
     */
    public function render()
    {
        $this->buildData();

        return parent::render();
    }
}
