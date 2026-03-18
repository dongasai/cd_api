<?php

namespace App\Admin\Widgets\DemoCharts;

use Dcat\Admin\Widgets\ApexCharts\Chart;

/**
 * 范围条形图组件
 */
class RangeBarChartWidget extends Chart
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
                'type' => 'rangeBar',
                'height' => 350,
            ],
            'plotOptions' => [
                'bar' => [
                    'horizontal' => true,
                ],
            ],
            'dataLabels' => [
                'enabled' => true,
            ],
            'legend' => [
                'position' => 'top',
            ],
            'colors' => ['#5c6bc0', '#ef5350'],
            'xaxis' => [
                'type' => 'datetime',
            ],
        ]);
    }

    /**
     * 处理图表数据
     */
    protected function buildData()
    {
        // 范围条形图数据格式: [[timestamp, start, end], ...]
        $data = [
            [
                'name' => '项目 A',
                'data' => [
                    [
                        'x' => '分析',
                        'y' => [
                            strtotime('-7 days') * 1000,
                            strtotime('-5 days') * 1000,
                        ],
                    ],
                    [
                        'x' => '设计',
                        'y' => [
                            strtotime('-5 days') * 1000,
                            strtotime('-3 days') * 1000,
                        ],
                    ],
                    [
                        'x' => '开发',
                        'y' => [
                            strtotime('-3 days') * 1000,
                            strtotime('+1 days') * 1000,
                        ],
                    ],
                    [
                        'x' => '测试',
                        'y' => [
                            strtotime('+1 days') * 1000,
                            strtotime('+3 days') * 1000,
                        ],
                    ],
                    [
                        'x' => '部署',
                        'y' => [
                            strtotime('+3 days') * 1000,
                            strtotime('+4 days') * 1000,
                        ],
                    ],
                ],
            ],
            [
                'name' => '项目 B',
                'data' => [
                    [
                        'x' => '分析',
                        'y' => [
                            strtotime('-8 days') * 1000,
                            strtotime('-6 days') * 1000,
                        ],
                    ],
                    [
                        'x' => '设计',
                        'y' => [
                            strtotime('-6 days') * 1000,
                            strtotime('-4 days') * 1000,
                        ],
                    ],
                    [
                        'x' => '开发',
                        'y' => [
                            strtotime('-4 days') * 1000,
                            strtotime('+2 days') * 1000,
                        ],
                    ],
                    [
                        'x' => '测试',
                        'y' => [
                            strtotime('+2 days') * 1000,
                            strtotime('+4 days') * 1000,
                        ],
                    ],
                    [
                        'x' => '部署',
                        'y' => [
                            strtotime('+4 days') * 1000,
                            strtotime('+5 days') * 1000,
                        ],
                    ],
                ],
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
