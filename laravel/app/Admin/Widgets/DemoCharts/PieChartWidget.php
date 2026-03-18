<?php

namespace App\Admin\Widgets\DemoCharts;

use Dcat\Admin\Widgets\ApexCharts\Chart;

/**
 * 饼图组件
 */
class PieChartWidget extends Chart
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
                'type' => 'pie',
                'height' => 350,
            ],
            'colors' => ['#5c6bc0', '#42a5f5', '#26a69a', '#66bb6a', '#ffa726'],
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
        $data = [44, 55, 13, 43, 22];
        $labels = ['团队 A', '团队 B', '团队 C', '团队 D', '团队 E'];

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
