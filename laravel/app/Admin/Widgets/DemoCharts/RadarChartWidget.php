<?php

namespace App\Admin\Widgets\DemoCharts;

use Dcat\Admin\Widgets\ApexCharts\Chart;

/**
 * 雷达图组件
 */
class RadarChartWidget extends Chart
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
                'type' => 'radar',
                'height' => 350,
            ],
            'legend' => [
                'position' => 'top',
            ],
            'colors' => ['#5c6bc0', '#ef5350'],
            'xaxis' => [
                'categories' => [],
            ],
            'yaxis' => [
                'show' => false,
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
                'data' => [80, 50, 30, 40, 100, 20],
            ],
            [
                'name' => '产品 B',
                'data' => [20, 30, 40, 80, 20, 80],
            ],
        ];
        $categories = ['性能', '可靠性', '舒适度', '安全性', '效率', '价格'];

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
