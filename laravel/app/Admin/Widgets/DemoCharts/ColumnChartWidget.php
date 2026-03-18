<?php

namespace App\Admin\Widgets\DemoCharts;

use Dcat\Admin\Widgets\ApexCharts\Chart;

/**
 * 列表图组件（堆叠柱状图）
 */
class ColumnChartWidget extends Chart
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
                'stacked' => true,
                'toolbar' => [
                    'show' => true,
                ],
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
            'colors' => ['#5c6bc0', '#42a5f5', '#66bb6a', '#ffa726'],
            'xaxis' => [
                'categories' => [],
            ],
            'yaxis' => [
                'title' => ['text' => '数量'],
            ],
            'fill' => [
                'opacity' => 1,
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
                'name' => '产品 A',
                'data' => [44, 55, 41, 67, 22, 43],
            ],
            [
                'name' => '产品 B',
                'data' => [13, 23, 20, 8, 13, 27],
            ],
            [
                'name' => '产品 C',
                'data' => [11, 17, 15, 15, 21, 14],
            ],
            [
                'name' => '产品 D',
                'data' => [21, 7, 25, 13, 22, 8],
            ],
        ];
        $categories = ['北京', '上海', '广州', '深圳', '杭州', '成都'];

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
