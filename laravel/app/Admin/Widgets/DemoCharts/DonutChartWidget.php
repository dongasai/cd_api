<?php

namespace App\Admin\Widgets\DemoCharts;

use Dcat\Admin\Widgets\ApexCharts\Chart;

/**
 * 环形图组件
 */
class DonutChartWidget extends Chart
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
                'type' => 'donut',
                'height' => 350,
            ],
            'colors' => ['#ef5350', '#ff7043', '#ffa726', '#66bb6a', '#42a5f5'],
            'plotOptions' => [
                'pie' => [
                    'donut' => [
                        'size' => '65%',
                        'labels' => [
                            'show' => true,
                        ],
                    ],
                ],
            ],
            'legend' => [
                'position' => 'bottom',
            ],
        ]);
    }

    /**
     * 处理图表数据
     */
    protected function buildData()
    {
        $data = [44, 55, 41, 17, 15];
        $labels = ['苹果', '芒果', '橙子', '西瓜', '葡萄'];

        $this->withData($data);
        $this->withLabels($labels);
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
     * 设置图表标签
     *
     * @return $this
     */
    public function withLabels(array $labels)
    {
        return $this->option('labels', $labels);
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
