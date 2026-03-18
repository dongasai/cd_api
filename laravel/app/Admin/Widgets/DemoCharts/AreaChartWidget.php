<?php

namespace App\Admin\Widgets\DemoCharts;

use Dcat\Admin\Widgets\ApexCharts\Chart;

/**
 * 面积图组件
 */
class AreaChartWidget extends Chart
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
                'type' => 'area',
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
            'colors' => ['#66bb6a', '#ffa726'],
            'xaxis' => [
                'categories' => [],
            ],
            'yaxis' => [
                'title' => ['text' => '销售额 (万元)'],
            ],
            'fill' => [
                'opacity' => 0.3,
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
                'name' => '线上销售',
                'data' => [31, 40, 28, 51, 42, 109, 100],
            ],
            [
                'name' => '线下销售',
                'data' => [11, 32, 45, 32, 34, 52, 41],
            ],
        ];
        $categories = ['1月', '2月', '3月', '4月', '5月', '6月', '7月'];

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
