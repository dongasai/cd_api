---
name: dcat-admin-metrics
description: |
  Dcat Admin Metrics Widget 开发技能。用于创建仪表盘统计卡片和图表 Widget。

  TRIGGER 当用户：
  - 创建仪表盘页面或统计卡片
  - 使用 Metrics\Card、Line、Donut、Round、RadialBar、Bar、SingleRound
  - 询问如何展示统计数据、KPI、趋势图
  - 需要新建 Dashboard Widget
  - 改造 HomeController 仪表盘
  - 任何涉及 Metrics Widget 的开发任务

  自动应用 Metrics Widget 开发规范，生成符合官方风格的代码。
---

# Dcat Admin Metrics Widget 开发规范

## 一、类继承层次

```
Widget (抽象基类)
 └── Card (核心基类，使用 InteractsWithApi trait)
      ├── Line        — 折线/面积图（sparkline 迷你图）
      ├── Donut       — 环形图（左右布局）
      └── RadialBar   — 径向条形图（有 footer）
           ├── Round       — 多环形图
           │    └── SingleRound — 单环形进度
           └── Bar         — 柱状图
```

## 二、选型指南

| 类型 | 适用场景 | 特点 |
|------|----------|------|
| **Card** | 纯数字 + 增长趋势 | 无图表，最简洁 |
| **Line** | 趋势展示 | sparkline 内嵌在内容下方，高度57px |
| **Donut** | 占比分布 | 左右布局（6:6），右侧环形图 |
| **Round** | 多项占比对比 | 多环形 + 中心总数，左右布局（5:7） |
| **SingleRound** | 单项进度百分比 | 单环 + 渐变 + 阴影 |
| **RadialBar** | 单项百分比 + 底部统计 | 径向进度 + footer 三列 |
| **Bar** | 柱状对比 | sparkline 模式，左右布局（4:8） |

## 三、Widget 实现模板

### 3.1 基本结构（所有类型通用）

```php
<?php

namespace App\Admin\Widgets\Metrics;

use Dcat\Admin\Widgets\Metrics\Xxx; // 选择基类
use Illuminate\Http\Request;

class MyWidget extends Xxx
{
    protected function init()
    {
        parent::init();
        $this->title('标题');
        // 不要加 icon()，官方示例都不加，加了反而丑
    }

    // 方式一：静态数据（简单场景）
    public function render()
    {
        $this->fill();
        return parent::render();
    }

    public function fill()
    {
        // 查询数据
        $this->withContent(...);
        $this->withChart(...);
    }

    // 方式二：异步数据（下拉菜单切换时刷新）
    public function handle(Request $request)
    {
        switch ($request->get('option')) {
            case '7':
            default:
                $this->withContent(...);
                $this->withChart(...);
        }
    }
}
```

### 3.2 Card — 纯数字 + 趋势

```php
class TotalUsers extends Card
{
    protected $footer;

    protected function init()
    {
        parent::init();
        $this->title('Total Users');
        $this->dropdown(['7' => 'Last 7 Days', '30' => 'Last Month']);
    }

    public function handle(Request $request)
    {
        $this->content(143);
        $this->up(15); // 或 $this->down(10)
    }

    public function up($percent)
    {
        return $this->footer(
            "<i class=\"feather icon-trending-up text-success\"></i> {$percent}% Increase"
        );
    }

    public function down($percent)
    {
        return $this->footer(
            "<i class=\"feather icon-trending-down text-danger\"></i> {$percent}% Decrease"
        );
    }

    public function footer($footer)
    {
        $this->footer = $footer;
        return $this;
    }

    public function renderContent()
    {
        $content = parent::renderContent();
        return <<<HTML
<div class="d-flex justify-content-between align-items-center mt-1" style="margin-bottom: 2px">
    <h2 class="ml-1 font-lg-1">{$content}</h2>
</div>
<div class="ml-1 mt-1 font-weight-bold text-80">
    {$this->renderFooter()}
</div>
HTML;
    }

    public function renderFooter()
    {
        return $this->toString($this->footer);
    }
}
```

### 3.3 Line — 折线/面积图

```php
class NewUsers extends Line
{
    protected function init()
    {
        parent::init();
        $this->title('New Users');
        $this->dropdown(['7' => 'Last 7 Days', '30' => 'Last Month']);
    }

    public function handle(Request $request)
    {
        $this->withContent('89.2k');
        $this->withChart([28, 40, 36, 52, 38, 60, 55]);
    }

    public function withChart(array $data)
    {
        return $this->chart([
            'series' => [['name' => $this->title, 'data' => $data]],
        ]);
    }

    public function withContent($content)
    {
        return $this->content(<<<HTML
<div class="d-flex justify-content-between align-items-center mt-1" style="margin-bottom: 2px">
    <h2 class="ml-1 font-lg-1">{$content}</h2>
    <span class="mb-0 mr-1 text-80">{$this->title}</span>
</div>
HTML);
    }
}
```

### 3.4 Donut — 环形图

```php
class NewDevices extends Donut
{
    protected function init()
    {
        parent::init();
        $color = Admin::color();
        $this->title('New Devices');
        $this->subTitle('Last 30 days');
        $this->chartLabels(['Desktop', 'Mobile']);
        $this->chartColors([$color->primary(), $color->alpha('blue2', 0.5)]);
    }

    public function fill()
    {
        $this->withContent(44.9, 28.6);
        $this->withChart([44.9, 28.6]);
    }

    public function withChart(array $data)
    {
        return $this->chart(['series' => $data]);
    }

    public function withContent($desktop, $mobile)
    {
        $blue = Admin::color()->alpha('blue2', 0.5);
        $style = 'margin-bottom: 8px';
        $labelWidth = 120;
        return $this->content(<<<HTML
<div class="d-flex pl-1 pr-1 pt-1" style="{$style}">
    <div style="width: {$labelWidth}px">
        <i class="fa fa-circle text-primary"></i> Desktop
    </div>
    <div>{$desktop}</div>
</div>
<div class="d-flex pl-1 pr-1" style="{$style}">
    <div style="width: {$labelWidth}px">
        <i class="fa fa-circle" style="color: $blue"></i> Mobile
    </div>
    <div>{$mobile}</div>
</div>
HTML);
    }
}
```

### 3.5 Round — 多环形图

```php
class ProductOrders extends Round
{
    protected function init()
    {
        parent::init();
        $this->title('Product Orders');
        $this->chartLabels(['Finished', 'Pending', 'Rejected']);
    }

    public function fill()
    {
        $this->withContent(23043, 14658, 4758);
        $this->withChart([70, 52, 26]); // 百分比 0-100
        $this->chartTotal('Total', 344);
    }

    public function withChart(array $data)
    {
        return $this->chart(['series' => $data]);
    }

    public function withContent($finished, $pending, $rejected)
    {
        return $this->content(<<<HTML
<div class="col-12 d-flex flex-column flex-wrap text-center" style="max-width: 220px">
    <div class="chart-info d-flex justify-content-between mb-1 mt-2">
        <div class="series-info d-flex align-items-center">
            <i class="fa fa-circle-o text-bold-700 text-primary"></i>
            <span class="text-bold-600 ml-50">Finished</span>
        </div>
        <div class="product-result"><span>{$finished}</span></div>
    </div>
    <div class="chart-info d-flex justify-content-between mb-1">
        <div class="series-info d-flex align-items-center">
            <i class="fa fa-circle-o text-bold-700 text-warning"></i>
            <span class="text-bold-600 ml-50">Pending</span>
        </div>
        <div class="product-result"><span>{$pending}</span></div>
    </div>
    <div class="chart-info d-flex justify-content-between mb-1">
        <div class="series-info d-flex align-items-center">
            <i class="fa fa-circle-o text-bold-700 text-danger"></i>
            <span class="text-bold-600 ml-50">Rejected</span>
        </div>
        <div class="product-result"><span>{$rejected}</span></div>
    </div>
</div>
HTML);
    }
}
```

### 3.6 RadialBar — 径向进度 + Footer

```php
class Tickets extends RadialBar
{
    protected function init()
    {
        parent::init();
        $this->title('Tickets');
        $this->height(400);
        $this->chartHeight(300);
        $this->chartLabels('Completed Tickets');
    }

    public function fill()
    {
        $this->withContent(162);
        $this->withFooter(29, 63, '1d');
        $this->withChart(83); // 百分比 0-100
    }

    public function withChart(int $data)
    {
        return $this->chart(['series' => [$data]]);
    }

    public function withContent($content)
    {
        return $this->content(<<<HTML
<div class="d-flex flex-column flex-wrap text-center">
    <h1 class="font-lg-2 mt-2 mb-0">{$content}</h1>
    <small>Tickets</small>
</div>
HTML);
    }

    public function withFooter($new, $open, $response)
    {
        return $this->footer(<<<HTML
<div class="d-flex justify-content-between p-1" style="padding-top: 0!important;">
    <div class="text-center"><p>New Tickets</p><span class="font-lg-1">{$new}</span></div>
    <div class="text-center"><p>Open Tickets</p><span class="font-lg-1">{$open}</span></div>
    <div class="text-center"><p>Response Time</p><span class="font-lg-1">{$response}</span></div>
</div>
HTML);
    }
}
```

### 3.7 Bar — 柱状图

```php
class Sessions extends Bar
{
    protected function init()
    {
        parent::init();
        $color = Admin::color();
        $this->contentWidth(5, 7);
        $this->title('Avg Sessions');
        $this->chartColors([$color->dark35(), $color->dark35(), $color->primary(), $color->dark35(), $color->dark35(), $color->dark35()]);
    }

    public function fill()
    {
        $this->withContent('2.7k', '+5.2%');
        $this->withChart([['name' => 'Sessions', 'data' => [75, 125, 225, 175, 125, 75, 25]]]);
    }

    public function withChart(array $data)
    {
        return $this->chart(['series' => $data]);
    }

    public function withContent($title, $value, $style = 'success')
    {
        $minHeight = '183px';
        return $this->content(<<<HTML
<div class="d-flex p-1 flex-column justify-content-between" style="padding-top: 0;width: 100%;height: 100%;min-height: {$minHeight}">
    <div class="text-left">
        <h1 class="font-lg-2 mt-2 mb-0">{$title}</h1>
        <h5 class="font-medium-2" style="margin-top: 10px;">
            <span class="text-{$style}">{$value} </span>
        </h5>
    </div>
    <a href="#" class="btn btn-primary shadow waves-effect waves-light">View Details <i class="feather icon-chevrons-right"></i></a>
</div>
HTML);
    }
}
```

## 四、在 Dashboard 中使用

```php
// HomeController
public function index(Content $content)
{
    return $content
        ->header('仪表盘')
        ->body(function (Row $row) {
            $row->column(6, function (Column $column) {
                $column->row(new Tickets);
                $column->row(new ProductOrders);
            });
            $row->column(6, function (Column $column) {
                $column->row(function (Row $row) {
                    $row->column(6, new NewUsers);
                    $row->column(6, new NewDevices);
                });
                $column->row(new Sessions);
                $column->row(new TotalUsers);
            });
        });
}
```

## 五、核心规则

### 5.1 不要加 icon

官方示例都不在 title 上加 icon。`$this->icon('fa fa-xxx')` 会在标题旁生成带背景色的图标块，视觉上很突兀。保持 title 纯文字即可。

### 5.2 复用官方 HTML 结构

每个类型的 `withContent()` 方法中的 HTML 结构是精心调优的，直接复用：
- **Card**: `d-flex justify-content-between` + `font-lg-1` + `text-80`
- **Line**: `d-flex justify-content-between` + `font-lg-1` + `text-80`
- **Donut**: `d-flex pl-1 pr-1 pt-1` + `fa-circle` + 固定 `labelWidth`
- **Round**: `chart-info d-flex justify-content-between` + `fa-circle-o` + `text-bold-700`
- **RadialBar**: `d-flex flex-column text-center` + `font-lg-2`
- **Bar**: `d-flex p-1 flex-column justify-content-between` + `font-lg-2`

### 5.3 图表数据格式

| 类型 | withChart 数据格式 |
|------|-------------------|
| Card | 无图表 |
| Line | `['series' => [['name' => title, 'data' => [...]]]]` |
| Donut | `['series' => [数值1, 数值2, ...]]` |
| Round | `['series' => [百分比1, 百分比2, ...]]`，0-100 |
| RadialBar | `['series' => [百分比]]`，0-100 |
| Bar | `['series' => [['name' => title, 'data' => [...]]]]` |

### 5.4 颜色获取

使用 `Admin::color()` 获取主题色：
```php
$color = Admin::color();
$color->primary();       // 主色
$color->success();       // 成功绿
$color->danger();        // 危险红
$color->warning();       // 警告黄
$color->info();          // 信息蓝
$color->dark35();        // 深灰
$color->blue2();         // 蓝色2
$color->alpha('blue2', 0.5); // 半透明
$color->orange2();       // 橙色2
```

### 5.5 异步刷新 vs 静态数据

- **静态数据**：实现 `render()` + `fill()`，页面加载时直接渲染
- **异步刷新**：实现 `handle(Request $request)`，支持下拉菜单切换数据
- 异步模式需要配合 `dropdown()` 使用，`handle()` 中通过 `$request->get('option')` 获取选中项

### 5.6 查询优化

7天趋势数据使用单次分组查询，不要循环7次：
```php
// 正确
$data = Model::whereDate('created_at', '>=', Carbon::today()->subDays(6))
    ->selectRaw('DATE(created_at) as date, count(*) as count')
    ->groupByRaw('DATE(created_at)')
    ->pluck('count', 'date')
    ->toArray();

$sevenDaysData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = Carbon::today()->subDays($i)->format('Y-m-d');
    $sevenDaysData[] = $data[$date] ?? 0;
}
```

### 5.7 枚举字段查询

项目中的枚举字段（如 ChannelStatus）是 int 类型，查询时必须用枚举值：
```php
// 正确
Channel::where('status', ChannelStatus::ACTIVE)->count();
// 错误 — 查不到数据
Channel::where('status', 'active')->count();
```

## 六、项目文件位置

- Widget 类：`app/Admin/Widgets/Metrics/`
- Demo 示例：`app/Admin/Widgets/Metrics/Demo/`
- 官方 stub 参考：`vendor/dongasai/dcat-admin2/src/Console/stubs/metrics/`
- 官方源码：`vendor/dongasai/dcat-admin2/src/Widgets/Metrics/`
