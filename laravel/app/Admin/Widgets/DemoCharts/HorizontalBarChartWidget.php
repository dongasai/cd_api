<?php

namespace App\Admin\Widgets\DemoCharts;

use Dcat\Admin\Widgets\ApexCharts\Chart;

/**
 * 横向柱状图组件
 */
class HorizontalBarChartWidget extends Chart
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
                    'horizontal' => true,
                    'barHeight' => '60%',
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
                'title' => ['text' => '产品类别'],
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
                'name' => '2025年',
                'data' => [44, 55, 41, 64, 22, 43, 21],
            ],
            [
                'name' => '2026年',
                'data' => [53, 32, 33, 52, 13, 44, 32],
            ],
        ];
        $categories = ['笔记本电脑', '台式电脑', '手机', '平板', '智能手表', '耳机', '键盘'];

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
