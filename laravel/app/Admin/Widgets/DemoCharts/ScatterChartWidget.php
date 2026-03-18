<?php

namespace App\Admin\Widgets\DemoCharts;

use Dcat\Admin\Widgets\ApexCharts\Chart;

/**
 * 散点图组件
 */
class ScatterChartWidget extends Chart
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
                'type' => 'scatter',
                'height' => 350,
                'zoom' => [
                    'enabled' => true,
                    'type' => 'xy',
                ],
            ],
            'legend' => [
                'position' => 'top',
            ],
            'colors' => ['#5c6bc0', '#ef5350'],
            'xaxis' => [
                'tickAmount' => 10,
            ],
            'yaxis' => [
                'tickAmount' => 7,
            ],
        ]);
    }

    /**
     * 处理图表数据
     */
    protected function buildData()
    {
        // 生成随机散点数据
        $data1 = [];
        $data2 = [];

        for ($i = 0; $i < 20; $i++) {
            $data1[] = [
                round(rand(0, 100) + rand(0, 100) / 100, 2),
                round(rand(0, 100) + rand(0, 100) / 100, 2),
            ];
            $data2[] = [
                round(rand(0, 100) + rand(0, 100) / 100, 2),
                round(rand(0, 100) + rand(0, 100) / 100, 2),
            ];
        }

        $data = [
            [
                'name' => '样本 A',
                'data' => $data1,
            ],
            [
                'name' => '样本 B',
                'data' => $data2,
            ],
        ];

        $this->withData($data);
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
