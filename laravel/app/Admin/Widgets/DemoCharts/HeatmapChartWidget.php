<?php

namespace App\Admin\Widgets\DemoCharts;

use Dcat\Admin\Widgets\ApexCharts\Chart;

/**
 * 热力图组件
 */
class HeatmapChartWidget extends Chart
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
                'type' => 'heatmap',
                'height' => 350,
            ],
            'plotOptions' => [
                'heatmap' => [
                    'enableShades' => false,
                    'colorScale' => [
                        'ranges' => [
                            ['from' => 0, 'to' => 25, 'name' => '低', 'color' => '#b3e5fc'],
                            ['from' => 26, 'to' => 50, 'name' => '中', 'color' => '#4fc3f7'],
                            ['from' => 51, 'to' => 75, 'name' => '高', 'color' => '#0288d1'],
                            ['from' => 76, 'to' => 100, 'name' => '极高', 'color' => '#01579b'],
                        ],
                    ],
                ],
            ],
            'dataLabels' => [
                'enabled' => false,
            ],
            'xaxis' => [
                'categories' => ['周一', '周二', '周三', '周四', '周五', '周六', '周日'],
            ],
        ]);
    }

    /**
     * 处理图表数据
     */
    protected function buildData()
    {
        $data = [];

        for ($i = 1; $i <= 4; $i++) {
            $row = [
                'name' => "第{$i}周",
                'data' => [],
            ];

            for ($j = 0; $j < 7; $j++) {
                $row['data'][] = rand(0, 100);
            }

            $data[] = $row;
        }

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
