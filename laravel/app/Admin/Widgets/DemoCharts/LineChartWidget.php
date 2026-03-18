<?php

namespace App\Admin\Widgets\DemoCharts;

use Dcat\Admin\Widgets\ApexCharts\Chart;

/**
 * 折线图组件
 */
class LineChartWidget extends Chart
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
                'type' => 'line',
                'height' => 350,
                'zoom' => [
                    'enabled' => true,
                ],
            ],
            'stroke' => [
                'curve' => 'smooth',
                'width' => 2,
            ],
            'dataLabels' => [
                'enabled' => false,
            ],
            'legend' => [
                'position' => 'top',
            ],
            'colors' => ['#5c6bc0', '#42a5f5'],
            'xaxis' => [
                'categories' => [],
            ],
            'yaxis' => [
                'title' => ['text' => '访问量'],
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
                'name' => '桌面',
                'data' => [10, 41, 35, 51, 49, 62, 69, 91, 148],
            ],
            [
                'name' => '移动端',
                'data' => [20, 35, 41, 62, 65, 45, 55, 78, 95],
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
